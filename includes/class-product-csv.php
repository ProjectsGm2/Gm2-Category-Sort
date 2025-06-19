<?php
/**
 * Export and import WooCommerce products via CSV.
 */
class Gm2_Category_Sort_Product_CSV {

    /**
     * Register hooks.
     */
    public static function init() {
        self::register_cli();
        add_action( 'admin_menu', [ __CLASS__, 'register_admin_pages' ] );
        add_action( 'admin_post_gm2_export_products', [ __CLASS__, 'handle_export_request' ] );
    }

    /**
     * Register WP-CLI commands.
     */
    public static function register_cli() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'gm2-category-sort export-products', [ __CLASS__, 'cli_export' ] );
            \WP_CLI::add_command( 'gm2-category-sort import-products', [ __CLASS__, 'cli_import' ] );
        }
    }

    /**
     * Add pages under Tools.
     */
    public static function register_admin_pages() {
        add_management_page(
            __( 'Export Products', 'gm2-category-sort' ),
            __( 'Export Products', 'gm2-category-sort' ),
            'manage_options',
            'gm2-product-export',
            [ __CLASS__, 'export_page' ]
        );
        add_management_page(
            __( 'Import Products', 'gm2-category-sort' ),
            __( 'Import Products', 'gm2-category-sort' ),
            'manage_options',
            'gm2-product-import',
            [ __CLASS__, 'import_page' ]
        );
    }

    /**
     * Render export page.
     */
    public static function export_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Export Products', 'gm2-category-sort' ); ?></h1>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'gm2_export_products', 'gm2_export_nonce' ); ?>
                <input type="hidden" name="action" value="gm2_export_products">
                <?php submit_button( __( 'Download CSV', 'gm2-category-sort' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle product export form submission.
     */
    public static function handle_export_request() {
        check_admin_referer( 'gm2_export_products', 'gm2_export_nonce' );

        $file   = wp_tempnam( 'products.csv' );
        $result = self::export_to_csv( $file );
        if ( is_wp_error( $result ) ) {
            wp_die( esc_html( $result->get_error_message() ) );
        }

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="products.csv"' );
        readfile( $file );
        unlink( $file );
        exit;
    }

    /**
     * Render import page.
     */
    public static function import_page() {
        $message = '';
        $error   = '';
        if ( isset( $_POST['gm2_import_nonce'] ) ) {
            check_admin_referer( 'gm2_import_products', 'gm2_import_nonce' );
            if ( ! empty( $_FILES['gm2_import_file']['tmp_name'] ) ) {
                $file   = $_FILES['gm2_import_file']['tmp_name'];
                $result = self::import_from_csv( $file );
                if ( is_wp_error( $result ) ) {
                    $error = $result->get_error_message();
                } else {
                    $message = __( 'Products imported successfully.', 'gm2-category-sort' );
                }
            } else {
                $error = __( 'Please select a CSV file.', 'gm2-category-sort' );
            }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Import Products', 'gm2-category-sort' ); ?></h1>
            <?php if ( $message ) : ?>
                <div class="notice notice-success"><p><?php echo esc_html( $message ); ?></p></div>
            <?php elseif ( $error ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'gm2_import_products', 'gm2_import_nonce' ); ?>
                <input type="file" name="gm2_import_file" accept=".csv">
                <?php submit_button( __( 'Import', 'gm2-category-sort' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * WP-CLI export handler.
     *
     * @param array $args Command args.
     */
    public static function cli_export( $args ) {
        $file = $args[0] ?? '';
        if ( ! $file ) {
            \WP_CLI::error( 'Please provide a CSV file path.' );
        }
        $result = self::export_to_csv( $file );
        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }
        \WP_CLI::success( 'Products exported successfully.' );
    }

    /**
     * WP-CLI import handler.
     *
     * @param array $args Command args.
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
        \WP_CLI::success( 'Products imported successfully.' );
    }

    /**
     * Export products to a CSV file.
     *
     * @param string $file Destination path.
     * @return true|WP_Error
     */
    public static function export_to_csv( $file ) {
        if ( ! function_exists( 'WC' ) ) {
            return new WP_Error( 'gm2_missing_wc', __( 'WooCommerce not installed.', 'gm2-category-sort' ) );
        }

        $wc_path = defined( 'WC_ABSPATH' ) ? WC_ABSPATH : trailingslashit( WC()->plugin_path() );
        include_once $wc_path . 'includes/export/class-wc-product-csv-exporter.php';

        $exporter = new WC_Product_CSV_Exporter();
        $exporter->set_columns_to_export( array_keys( $exporter->get_default_column_names() ) );
        $exporter->set_filename( basename( $file ) );
        $exporter->generate_file();

        $upload_dir = wp_upload_dir();
        $source     = trailingslashit( $upload_dir['basedir'] ) . $exporter->get_filename();
        if ( ! @copy( $source, $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            return new WP_Error( 'gm2_copy_failed', __( 'Unable to copy export file.', 'gm2-category-sort' ) );
        }

        @unlink( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        return true;
    }

    /**
     * Import products from a CSV file.
     *
     * @param string $file CSV path.
     * @return true|WP_Error
     */
    public static function import_from_csv( $file ) {
        if ( ! function_exists( 'WC' ) ) {
            return new WP_Error( 'gm2_missing_wc', __( 'WooCommerce not installed.', 'gm2-category-sort' ) );
        }

        $wc_path = defined( 'WC_ABSPATH' ) ? WC_ABSPATH : trailingslashit( WC()->plugin_path() );
        include_once $wc_path . 'includes/import/class-wc-product-csv-importer.php';

        $importer = new WC_Product_CSV_Importer( $file, [ 'update_existing' => true ] );
        $importer->import();

        return true;
    }
}
