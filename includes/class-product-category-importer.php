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
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
        add_action( 'wp_ajax_gm2_product_category_import_step', [ __CLASS__, 'ajax_import_step' ] );
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
        $total     = self::count_rows( $file );
        $progress  = null;
        if ( class_exists( '\\WP_CLI\Utils' ) ) {
            $progress = \WP_CLI\Utils\make_progress_bar( 'Assigning categories', $total );
        }

        $offset = 0;
        do {
            $result = self::import_from_csv_step( $file, $overwrite, $offset, 50, $progress );
            if ( is_wp_error( $result ) ) {
                if ( $progress ) {
                    $progress->finish();
                }
                \WP_CLI::error( $result->get_error_message() );
            }
            $offset = $result['offset'];
        } while ( ! $result['done'] );

        if ( $progress ) {
            $progress->finish();
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
            <form id="gm2-product-category-import-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'gm2_product_category_import', 'gm2_product_category_import_nonce' ); ?>
                <input type="file" name="gm2_product_category_file" accept=".csv">
                <label>
                    <input type="checkbox" name="gm2_overwrite" value="1" <?php checked( $overwrite ); ?>>
                    <?php esc_html_e( 'Overwrite existing categories', 'gm2-category-sort' ); ?>
                </label>
                <?php submit_button( __( 'Assign', 'gm2-category-sort' ) ); ?>
                <progress id="gm2-import-progress" value="0" max="100" style="display:none"></progress>
                <span class="gm2-progress-text"></span>
                <div id="gm2-import-message"></div>
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
        $offset = 0;
        do {
            $result = self::import_from_csv_step( $file, $overwrite, $offset, 0 );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            $offset = $result['offset'];
        } while ( ! $result['done'] );

        return true;
    }

    /**
     * Process a chunk of rows from a CSV file.
     *
     * @param string                        $file      Path to the CSV file.
     * @param bool                          $overwrite Whether to overwrite existing terms.
     * @param int                           $offset    Starting row offset.
     * @param int                           $limit     Number of rows to process, 0 for all.
     * @param \WP_CLI\ProgressBar|null $progress  Optional progress bar for CLI.
     * @return array|WP_Error                       New offset and completion flag or error.
     */
    public static function import_from_csv_step( $file, $overwrite, $offset = 0, $limit = 50, $progress = null ) {
        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            return new WP_Error( 'gm2_invalid_file', __( 'Invalid CSV file.', 'gm2-category-sort' ) );
        }

        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'gm2_unreadable', __( 'Unable to read file.', 'gm2-category-sort' ) );
        }

        for ( $i = 0; $i < $offset; $i++ ) {
            if ( false === fgetcsv( $handle ) ) {
                fclose( $handle );
                return [
                    'offset' => $offset,
                    'done'   => true,
                ];
            }
        }

        $count = 0;
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            if ( $limit && $count >= $limit ) {
                break;
            }

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

            $count++;
            if ( $progress ) {
                $progress->tick();
            }
        }

        $new_offset = $offset + $count;
        $done       = feof( $handle );
        fclose( $handle );

        return [
            'offset' => $new_offset,
            'done'   => $done,
        ];
    }

    /**
     * Count rows in a CSV file.
     *
     * @param string $file CSV file path.
     * @return int
     */
    public static function count_rows( $file ) {
        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            return 0;
        }
        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            return 0;
        }
        $count = 0;
        while ( false !== fgetcsv( $handle ) ) {
            $count++;
        }
        fclose( $handle );
        return $count;
    }

    /**
     * Enqueue admin assets for the import page.
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'tools_page_gm2-product-category-import' ) {
            return;
        }

        $ver = filemtime( GM2_CAT_SORT_PATH . 'assets/js/product-category-import.js' );
        wp_enqueue_script(
            'gm2-product-category-import',
            GM2_CAT_SORT_URL . 'assets/js/product-category-import.js',
            [ 'jquery' ],
            $ver,
            true
        );

        wp_localize_script(
            'gm2-product-category-import',
            'gm2ProductCategoryImport',
            [
                'nonce'     => wp_create_nonce( 'gm2_product_category_import' ),
                'completed' => __( 'Categories assigned successfully.', 'gm2-category-sort' ),
                'error'     => __( 'Error importing categories.', 'gm2-category-sort' ),
                'limit'     => 50,
            ]
        );
    }

    /**
     * Handle AJAX import steps.
     */
    public static function ajax_import_step() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized' );
        }

        check_ajax_referer( 'gm2_product_category_import', 'nonce' );

        $overwrite = ! empty( $_POST['overwrite'] );
        $offset    = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
        $limit     = 50;

        if ( $offset === 0 ) {
            if ( empty( $_FILES['file']['tmp_name'] ) ) {
                wp_send_json_error( __( 'Missing file.', 'gm2-category-sort' ) );
            }
            $path = wp_tempnam( $_FILES['file']['name'] );
            if ( ! $path || ! move_uploaded_file( $_FILES['file']['tmp_name'], $path ) ) {
                wp_send_json_error( __( 'Upload failed.', 'gm2-category-sort' ) );
            }
            $total = self::count_rows( $path );
        } else {
            $path  = sanitize_text_field( $_POST['path'] ?? '' );
            $total = isset( $_POST['total'] ) ? absint( $_POST['total'] ) : 0;
            if ( ! $path || ! file_exists( $path ) ) {
                wp_send_json_error( __( 'Invalid file.', 'gm2-category-sort' ) );
            }
        }

        $result = self::import_from_csv_step( $path, $overwrite, $offset, $limit );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        if ( $result['done'] ) {
            unlink( $path );
        }

        wp_send_json_success(
            [
                'offset' => $result['offset'],
                'done'   => $result['done'],
                'path'   => $path,
                'total'  => $total,
            ]
        );
    }
}

