<?php
/**
 * Assign product categories to products from CSV files.
 */
class Gm2_Category_Sort_Product_Category_Importer {

    /**
     * Initialize importer by registering CLI and admin page.
     */
    public static function init() {
        self::register_cli();
        add_action( 'admin_menu', [ __CLASS__, 'register_admin_page' ] );
    }

    /**
     * Register WP-CLI command.
     */
    public static function register_cli() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'gm2-category-sort assign-categories', [ __CLASS__, 'cli_import' ] );
        }
    }

    /**
     * Handle WP-CLI import.
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative args.
     */
    public static function cli_import( $args, $assoc_args ) {
        $file = $args[0] ?? '';
        if ( ! $file ) {
            \WP_CLI::error( 'Please provide a CSV file path.' );
        }

        $overwrite = isset( $assoc_args['overwrite'] );
        $result    = self::import_from_csv( $file, $overwrite );
        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }

        \WP_CLI::success( 'Categories assigned successfully.' );
    }

    /**
     * Register the admin import page under Tools.
     */
    public static function register_admin_page() {
        add_management_page(
            __( 'Assign Product Categories', 'gm2-category-sort' ),
            __( 'Assign Product Categories', 'gm2-category-sort' ),
            'manage_options',
            'gm2-product-category-import',
            [ __CLASS__, 'admin_page' ]
        );
    }

    /**
     * Render the admin page and handle form submission.
     */
    public static function admin_page() {
        $message  = '';
        $error    = '';
        $overwrite = false;

        if ( isset( $_POST['gm2_product_category_import_nonce'] ) ) {
            check_admin_referer( 'gm2_product_category_import', 'gm2_product_category_import_nonce' );
            $overwrite = ! empty( $_POST['gm2_overwrite'] );

            if ( ! empty( $_FILES['gm2_product_category_file']['tmp_name'] ) ) {
                $file   = $_FILES['gm2_product_category_file']['tmp_name'];
                $result = self::import_from_csv( $file, $overwrite );
                if ( is_wp_error( $result ) ) {
                    $error = $result->get_error_message();
                } else {
                    $message = __( 'Categories assigned successfully.', 'gm2-category-sort' );
                }
            } else {
                $error = __( 'Please select a CSV file.', 'gm2-category-sort' );
            }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Assign Product Categories', 'gm2-category-sort' ); ?></h1>
            <?php if ( $message ) : ?>
                <div class="notice notice-success"><p><?php echo esc_html( $message ); ?></p></div>
            <?php elseif ( $error ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'gm2_product_category_import', 'gm2_product_category_import_nonce' ); ?>
                <input type="file" name="gm2_product_category_file" accept=".csv">
                <label>
                    <input type="checkbox" name="gm2_overwrite" value="1" <?php checked( $overwrite ); ?>>
                    <?php esc_html_e( 'Overwrite existing categories', 'gm2-category-sort' ); ?>
                </label>
                <?php submit_button( __( 'Assign', 'gm2-category-sort' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Assign categories from a CSV file.
     *
     * Each row starts with a product SKU followed by category names.
     *
     * @param string $file      CSV file path.
     * @param bool   $overwrite Whether to overwrite existing categories.
     * @return true|WP_Error
     */
    public static function import_from_csv( $file, $overwrite ) {
        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            return new WP_Error( 'gm2_invalid_file', __( 'Invalid CSV file.', 'gm2-category-sort' ) );
        }

        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'gm2_unreadable', __( 'Unable to read file.', 'gm2-category-sort' ) );
        }

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            if ( empty( $row ) ) {
                continue;
            }

            $sku = trim( array_shift( $row ) );
            if ( $sku === '' ) {
                continue;
            }

            $product_id = wc_get_product_id_by_sku( $sku );
            if ( ! $product_id ) {
                continue;
            }

            $term_ids = [];
            foreach ( $row as $name ) {
                $name = trim( $name );
                if ( $name === '' ) {
                    continue;
                }
                $term = get_term_by( 'name', $name, 'product_cat' );
                if ( $term && ! is_wp_error( $term ) ) {
                    $term_ids[] = (int) $term->term_id;
                }
            }

            if ( ! empty( $term_ids ) ) {
                wp_set_object_terms( $product_id, $term_ids, 'product_cat', ! $overwrite );
            }
        }

        fclose( $handle );
        return true;
    }
}
