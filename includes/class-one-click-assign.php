<?php
/**
 * One Click Categories Assignment admin page.
 */
class Gm2_Category_Sort_One_Click_Assign {

    /**
     * Initialize hooks.
     */
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_admin_page' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
        add_action( 'wp_ajax_gm2_one_click_assign', [ __CLASS__, 'ajax_assign' ] );
    }

    /**
     * Register the Tools page.
     */
    public static function register_admin_page() {
        add_management_page(
            __( 'One Click Categories Assignment', 'gm2-category-sort' ),
            __( 'One Click Categories Assignment', 'gm2-category-sort' ),
            'manage_options',
            'gm2-one-click-assign',
            [ __CLASS__, 'admin_page' ]
        );
    }

    /**
     * Enqueue admin JavaScript.
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'tools_page_gm2-one-click-assign' ) {
            return;
        }

        $ver = file_exists( GM2_CAT_SORT_PATH . 'assets/js/one-click-assign.js' ) ? filemtime( GM2_CAT_SORT_PATH . 'assets/js/one-click-assign.js' ) : GM2_CAT_SORT_VERSION;
        wp_enqueue_script(
            'gm2-one-click-assign',
            GM2_CAT_SORT_URL . 'assets/js/one-click-assign.js',
            [ 'jquery' ],
            $ver,
            true
        );

        wp_localize_script(
            'gm2-one-click-assign',
            'gm2OneClickAssign',
            [
                'nonce'     => wp_create_nonce( 'gm2_one_click_assign' ),
                'running'   => __( 'Processing...', 'gm2-category-sort' ),
                'completed' => __( 'Category CSV files generated.', 'gm2-category-sort' ),
                'error'     => __( 'Error generating files.', 'gm2-category-sort' ),
            ]
        );
    }

    /**
     * Render the admin page.
     */
    public static function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'One Click Categories Assignment', 'gm2-category-sort' ); ?></h1>
            <p>
                <button id="gm2-one-click-btn" class="button button-primary">
                    <?php esc_html_e( 'Assign Categories', 'gm2-category-sort' ); ?>
                </button>
            </p>
            <div id="gm2-one-click-message"></div>
        </div>
        <?php
    }

    /**
     * Handle AJAX request to generate category CSV files.
     */
    public static function ajax_assign() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized' );
        }

        check_ajax_referer( 'gm2_one_click_assign', 'nonce' );

        $upload = wp_upload_dir();
        $dir    = trailingslashit( $upload['basedir'] ) . 'gm2-category-sort/categories-structure';

        Gm2_Category_Sort_Product_Category_Generator::export_category_tree_csv( $dir );
        self::export_branch_csvs( $dir );

        wp_send_json_success();
    }

    /**
     * Split category-tree.csv into separate branch files for every level.
     *
     * Each category path prefix gets its own CSV containing all rows under
     * that branch, ensuring child categories receive branch files too.
     *
     * @param string $dir Directory containing category-tree.csv.
     * @return void
     */
    protected static function export_branch_csvs( $dir ) {
        $tree_file = rtrim( $dir, '/' ) . '/category-tree.csv';
        if ( ! file_exists( $tree_file ) ) {
            return;
        }

        $rows    = array_map( 'str_getcsv', file( $tree_file ) );
        $handles = [];
        foreach ( $rows as $row ) {
            if ( empty( $row ) ) {
                continue;
            }

            $path_slugs = [];
            foreach ( $row as $segment ) {
                $segment = trim( $segment );
                if ( $segment === '' ) {
                    continue;
                }

                $path_slugs[] = sanitize_title( $segment );
                $slug         = implode( '-', $path_slugs );
                $file         = rtrim( $dir, '/' ) . '/' . $slug . '.csv';

                if ( ! isset( $handles[ $slug ] ) ) {
                    $handles[ $slug ] = fopen( $file, 'w' );
                    if ( ! $handles[ $slug ] ) {
                        unset( $handles[ $slug ] );
                        continue;
                    }
                }

                fputcsv( $handles[ $slug ], $row );
            }
        }

        foreach ( $handles as $fh ) {
            fclose( $fh );
        }
    }
}
