<?php
/**
 * Import product categories from CSV files.
 */
class Gm2_Category_Sort_Category_Importer {

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
            \WP_CLI::add_command( 'gm2-category-sort import', [ __CLASS__, 'cli_import' ] );
        }
    }

    /**
     * Handle WP-CLI import.
     *
     * @param array $args Command arguments.
     */
    public static function cli_import( $args ) {
        $file = $args[0] ?? '';
        if ( ! $file ) {
            \WP_CLI::error( 'Please provide a CSV file path.' );
        }

        $result = self::import_from_csv( $file );
        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }

        \WP_CLI::success( 'Categories imported successfully.' );
    }

    /**
     * Register the admin import page under Tools.
     */
    public static function register_admin_page() {
        add_submenu_page(
            GM2_CAT_SORT_MENU_SLUG,
            __( 'Import Categories', 'gm2-category-sort' ),
            __( 'Import Categories', 'gm2-category-sort' ),
            'manage_options',
            'gm2-category-import',
            [ __CLASS__, 'admin_page' ]
        );
    }

    /**
     * Render the admin page and handle form submission.
     */
    public static function admin_page() {
        $message = '';
        $error   = '';

        if ( isset( $_POST['gm2_category_import_nonce'] ) ) {
            check_admin_referer( 'gm2_category_import', 'gm2_category_import_nonce' );

            if ( ! empty( $_FILES['gm2_category_file']['tmp_name'] ) ) {
                $file   = $_FILES['gm2_category_file']['tmp_name'];
                $result = self::import_from_csv( $file );
                if ( is_wp_error( $result ) ) {
                    $error = $result->get_error_message();
                } else {
                    $message = __( 'Categories imported successfully.', 'gm2-category-sort' );
                }
            } else {
                $error = __( 'Please select a CSV file.', 'gm2-category-sort' );
            }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Import Categories', 'gm2-category-sort' ); ?></h1>
            <?php if ( $message ) : ?>
                <div class="notice notice-success"><p><?php echo esc_html( $message ); ?></p></div>
            <?php elseif ( $error ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'gm2_category_import', 'gm2_category_import_nonce' ); ?>
                <input type="file" name="gm2_category_file" accept=".csv">
                <?php submit_button( __( 'Import', 'gm2-category-sort' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Import categories from a CSV file.
     *
     * Each line should list categories from top to bottom.
     *
     * @param string $file Path to the CSV file.
     * @return true|WP_Error
     */
    public static function import_from_csv( $file ) {
        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            return new WP_Error( 'gm2_invalid_file', __( 'Invalid CSV file.', 'gm2-category-sort' ) );
        }

        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'gm2_unreadable', __( 'Unable to read file.', 'gm2-category-sort' ) );
        }

        $first_row = true;
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            if ( empty( $row ) ) {
                continue;
            }

            if ( $first_row && isset( $row[0] ) ) {
                $row[0]  = preg_replace( "/^\xEF\xBB\xBF/", '', $row[0] );
                $first_row = false;
            }

            $parent = 0;
            foreach ( $row as $name ) {
                $name = trim( $name );
                if ( $name === '' ) {
                    continue;
                }

                $synonyms = '';
                if ( preg_match( '/^(.*?)\s*\(([^)]*)\)$/', $name, $m ) ) {
                    $name     = trim( $m[1] );
                    $synonyms = trim( $m[2] );
                }

                $existing = term_exists( $name, 'product_cat', $parent );
                if ( is_array( $existing ) ) {
                    $parent = (int) $existing['term_id'];
                } else {
                    $result = wp_insert_term( $name, 'product_cat', [ 'parent' => $parent ] );
                    if ( is_wp_error( $result ) ) {
                        fclose( $handle );
                        return $result;
                    }
                    $parent = (int) $result['term_id'];
                }

                if ( $synonyms !== '' ) {
                    update_term_meta( $parent, 'gm2_synonyms', $synonyms );
                }
            }
        }

        fclose( $handle );
        return true;
    }
}
