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
                'branchesTitle' => __( 'Identified Branches', 'gm2-category-sort' ),
                'parentLabel'   => __( 'Parent', 'gm2-category-sort' ),
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
                    <?php esc_html_e( 'Study Category Tree Structure', 'gm2-category-sort' ); ?>
                </button>
            </p>
            <p><progress id="gm2-one-click-progress" value="0" max="100" style="display:none;width:100%;"></progress></p>
            <div id="gm2-one-click-message"></div>
            <div id="gm2-one-click-branches"></div>
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

        $offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
        $reset  = ! empty( $_POST['reset'] );

        $upload = wp_upload_dir();
        $dir    = trailingslashit( $upload['basedir'] ) . 'gm2-category-sort/categories-structure';

        if ( $offset === 0 ) {
            Gm2_Category_Sort_Product_Category_Generator::export_category_tree_csv( $dir );
        }

        $batch    = 100;
        $progress = self::export_branch_csvs_step( $dir, $offset, $batch, $reset );

        if ( $progress['done'] ) {
            $progress['branches'] = self::get_branch_paths( $dir );
        }

        wp_send_json_success( $progress );
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
    protected static function export_branch_csvs_step( $dir, $offset, $batch, $reset = false ) {
        $tree_file = rtrim( $dir, '/' ) . '/category-tree.csv';
        if ( ! file_exists( $tree_file ) ) {
            return [ 'offset' => 0, 'total' => 0, 'done' => true ];
        }

        if ( $reset && $offset === 0 ) {
            foreach ( glob( rtrim( $dir, '/' ) . '/*.csv' ) as $file ) {
                if ( basename( $file ) !== 'category-tree.csv' ) {
                    @unlink( $file );
                }
            }
        }

        $rows  = array_map( 'str_getcsv', file( $tree_file ) );
        $total = count( $rows );

        // Determine which category path prefixes have children.
        $has_children = [];
        foreach ( $rows as $row ) {
            if ( empty( $row ) ) {
                continue;
            }
            $path_slugs = [];
            $last_index = count( $row ) - 1;
            foreach ( $row as $index => $segment ) {
                $segment = trim( $segment );
                if ( $segment === '' ) {
                    continue;
                }
                $path_slugs[] = sanitize_title( $segment );
                $slug         = implode( '-', $path_slugs );
                if ( $index < $last_index ) {
                    $has_children[ $slug ] = true;
                }
            }
        }

        $slice   = array_slice( $rows, $offset, $batch );
        $handles = [];
        foreach ( $slice as $row ) {
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
                if ( ! isset( $has_children[ $slug ] ) ) {
                    continue;
                }
                $file = rtrim( $dir, '/' ) . '/' . $slug . '.csv';
                if ( ! isset( $handles[ $slug ] ) ) {
                    $handles[ $slug ] = fopen( $file, $offset === 0 ? 'w' : 'a' );
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

        $new_offset = $offset + count( $slice );
        $done       = $new_offset >= $total;

        return [
            'offset' => $new_offset,
            'total'  => $total,
            'done'   => $done,
        ];
    }

    /**
     * Retrieve readable branch paths from generated CSV files.
     *
     * @param string $dir Directory containing branch CSVs.
     * @return array[] List of branch info arrays with path and parent name.
     */
    protected static function get_branch_paths( $dir ) {
        $tree_file = rtrim( $dir, '/' ) . '/category-tree.csv';
        if ( ! file_exists( $tree_file ) ) {
            return [];
        }

        $rows  = array_map( 'str_getcsv', file( $tree_file ) );
        $prefix_map = [];

        foreach ( $rows as $row ) {
            if ( empty( $row ) ) {
                continue;
            }

            $path_slugs = [];
            $path_names = [];
            foreach ( $row as $segment ) {
                $segment = trim( $segment );
                if ( $segment === '' ) {
                    continue;
                }
                $path_slugs[] = sanitize_title( $segment );
                $path_names[]  = $segment;
                $slug          = implode( '-', $path_slugs );
                if ( ! isset( $prefix_map[ $slug ] ) ) {
                    $prefix_map[ $slug ] = implode( ' > ', $path_names );
                }
            }
        }

        $branches = [];
        foreach ( glob( rtrim( $dir, '/' ) . '/*.csv' ) as $file ) {
            $slug = basename( $file, '.csv' );
            if ( $slug === 'category-tree' ) {
                continue;
            }

            $path        = $prefix_map[ $slug ] ?? '';
            if ( $path === '' ) {
                $fh  = fopen( $file, 'r' );
                $row = $fh ? fgetcsv( $fh ) : false;
                if ( $fh ) {
                    fclose( $fh );
                }
                if ( $row ) {
                    $segments = array_slice( $row, 0, count( explode( '-', $slug ) ) );
                    $path     = implode( ' > ', array_filter( array_map( 'trim', $segments ) ) );
                }
            }

            $parent_slug = preg_replace( '/-[^-]+$/', '', $slug );
            $parent      = $prefix_map[ $parent_slug ] ?? '';

            $branches[] = [
                'path'   => $path,
                'parent' => $parent,
            ];
        }

        usort( $branches, function( $a, $b ) {
            return strcmp( $a['path'], $b['path'] );
        } );

        return $branches;
    }
}
