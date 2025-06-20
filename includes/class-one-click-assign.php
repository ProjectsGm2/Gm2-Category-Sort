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
        add_action( 'wp_ajax_gm2_one_click_branches', [ __CLASS__, 'ajax_branches' ] );
        add_action( 'wp_ajax_gm2_one_click_assign_categories', [ __CLASS__, 'ajax_assign_categories' ] );
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
                'nonce'           => wp_create_nonce( 'gm2_one_click_assign' ),
                'running'         => __( 'Processing...', 'gm2-category-sort' ),
                'completed'       => __( 'Category CSV files generated.', 'gm2-category-sort' ),
                'loadingBranches' => __( 'Loading categories...', 'gm2-category-sort' ),
                'assigning'       => __( 'Assigning categories...', 'gm2-category-sort' ),
                'assignDone'      => __( 'Category assignment complete.', 'gm2-category-sort' ),
                'resetDone'       => __( 'All categories reset.', 'gm2-category-sort' ),
                'error'           => __( 'Error generating files.', 'gm2-category-sort' ),
            ]
        );
    }

    /**
     * Render the admin page.
     */
    public static function admin_page() {
        $log = get_option( 'gm2_one_click_log', [] );
        if ( ! is_array( $log ) ) {
            $log = [];
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'One Click Categories Assignment', 'gm2-category-sort' ); ?></h1>
            <p>
                <button id="gm2-one-click-btn" class="button button-primary">
                    <?php esc_html_e( 'Study Category Tree', 'gm2-category-sort' ); ?>
                </button>
            </p>
            <p>
                <select id="gm2-oca-fields" multiple style="min-width:220px;">
                    <option value="title"><?php esc_html_e( 'Product Title', 'gm2-category-sort' ); ?></option>
                    <option value="description"><?php esc_html_e( 'Product Description', 'gm2-category-sort' ); ?></option>
                    <option value="attributes"><?php esc_html_e( 'Product Attributes', 'gm2-category-sort' ); ?></option>
                </select>
                <button id="gm2-oca-assign" class="button button-secondary" style="margin-left:6px;">
                    <?php esc_html_e( 'Assign Categories', 'gm2-category-sort' ); ?>
                </button>
            </p>
            <p>
                <label>
                    <input type="radio" name="gm2_oca_overwrite" value="0" checked>
                    <?php esc_html_e( 'Add to existing categories', 'gm2-category-sort' ); ?>
                </label>
                &nbsp;
                <label>
                    <input type="radio" name="gm2_oca_overwrite" value="1">
                    <?php esc_html_e( 'Overwrite existing categories', 'gm2-category-sort' ); ?>
                </label>
            </p>
            <p>
                <button id="gm2-oca-reset" class="button">
                    <?php esc_html_e( 'Reset All Categories', 'gm2-category-sort' ); ?>
                </button>
            </p>
            <p><progress id="gm2-oca-progress" value="0" max="100" style="display:none;width:100%;"></progress></p>
            <p><progress id="gm2-oca-reset-progress" value="0" max="100" style="display:none;width:100%;"></progress></p>
            <div id="gm2-one-click-message"></div>
            <div id="gm2-branch-results" style="margin-top:15px;"></div>
            <ul id="gm2-oca-list" style="background:#fff;border:1px solid #ccc;padding:5px;max-height:300px;overflow:auto;">
                <?php foreach ( $log as $line ) : ?>
                    <li><?php echo esc_html( $line ); ?></li>
                <?php endforeach; ?>
            </ul>
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
     * Return branch categories and their direct children as HTML.
     */
    public static function ajax_branches() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized' );
        }

        check_ajax_referer( 'gm2_one_click_assign', 'nonce' );

        $upload = wp_upload_dir();
        $dir    = trailingslashit( $upload['basedir'] ) . 'gm2-category-sort/categories-structure';
        $file   = rtrim( $dir, '/' ) . '/category-tree.csv';
        if ( ! file_exists( $file ) ) {
            wp_send_json_error( __( 'CSV files not found.', 'gm2-category-sort' ) );
        }

        $branches = self::build_branch_map( $file );

        ob_start();
        echo '<ul>';
        foreach ( $branches as $parent => $children ) {
            $clean_parent   = preg_replace( '/\s*\([^\)]*\)/', '', $parent );
            $clean_children = array_map(
                function( $child ) {
                    return preg_replace( '/\s*\([^\)]*\)/', '', $child );
                },
                array_keys( $children )
            );
            echo '<li><strong>' . esc_html( $clean_parent ) . '</strong>: ' . esc_html( implode( ', ', $clean_children ) ) . '</li>';
        }
        echo '</ul>';
        $html = ob_get_clean();

        wp_send_json_success( [ 'html' => $html ] );
    }

    /**
     * Build mapping of parent categories to their children from a CSV file.
     *
     * @param string $file Path to category-tree.csv.
     * @return array<string,array>
     */
    public static function build_branch_map( $file ) {
        $rows     = array_map( 'str_getcsv', file( $file ) );
        $branches = [];
        foreach ( $rows as $row ) {
            $prev       = null;
            $path_slugs = [];
            foreach ( $row as $segment ) {
                $segment = trim( $segment );
                if ( $segment === '' ) {
                    continue;
                }
                $path_slugs[] = Gm2_Category_Sort_Product_Category_Generator::slugify_segment( $segment );
                if ( $prev !== null ) {
                    if ( ! isset( $branches[ $prev ] ) ) {
                        $branches[ $prev ] = [];
                    }
                    $slug = implode( '-', $path_slugs );
                    if ( ! isset( $branches[ $prev ][ $segment ] ) ) {
                        $branches[ $prev ][ $segment ] = $slug;
                    }
                }
                $prev = $segment;
            }
        }
        return $branches;
    }

    /**
     * Assign categories to products using selected fields.
     */
    public static function ajax_assign_categories() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized' );
        }

        check_ajax_referer( 'gm2_one_click_assign', 'nonce' );

        $fields = array_map( 'sanitize_key', (array) ( $_POST['fields'] ?? [] ) );
        if ( empty( $fields ) ) {
            $fields = [ 'title' ];
        }

        $overwrite = ! empty( $_POST['overwrite'] );
        $offset    = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
        $limit     = 50;

        $mapping = self::build_mapping();

        $total_query = wp_count_posts( 'product' )->publish;

        $query = new WP_Query(
            [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'posts_per_page' => $limit,
                'offset'         => $offset,
                'orderby'        => 'ID',
                'order'          => 'ASC',
            ]
        );

        $items = [];
        $log   = get_option( 'gm2_one_click_log', [] );
        if ( $offset === 0 ) {
            $log = [];
        }
        $log = (array) $log;
        foreach ( $query->posts as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }

            $text       = '';
            $attr_slugs = [];
            if ( in_array( 'title', $fields, true ) ) {
                $text .= ' ' . $product->get_name();
            }
            if ( in_array( 'description', $fields, true ) ) {
                $text .= ' ' . $product->get_description() . ' ' . $product->get_short_description();
            }
            if ( in_array( 'attributes', $fields, true ) ) {
                foreach ( $product->get_attributes() as $attr ) {
                    if ( $attr->is_taxonomy() ) {
                        $names = wc_get_product_terms( $product_id, $attr->get_name(), [ 'fields' => 'names' ] );
                        $slugs = wc_get_product_terms( $product_id, $attr->get_name(), [ 'fields' => 'slugs' ] );
                        $text  .= ' ' . implode( ' ', $names );
                        $key    = sanitize_key( $attr->get_name() );
                        if ( ! isset( $attr_slugs[ $key ] ) ) {
                            $attr_slugs[ $key ] = [];
                        }
                        $attr_slugs[ $key ] = array_merge( $attr_slugs[ $key ], $slugs );
                    } else {
                        $opts = array_map( 'sanitize_text_field', $attr->get_options() );
                        $text .= ' ' . implode( ' ', $opts );
                        $key  = sanitize_key( $attr->get_name() );
                        if ( ! isset( $attr_slugs[ $key ] ) ) {
                            $attr_slugs[ $key ] = [];
                        }
                        $attr_slugs[ $key ] = array_merge( $attr_slugs[ $key ], array_map( 'sanitize_title', $opts ) );
                    }
                }
            }

            if ( in_array( 'attributes', $fields, true ) && count( $fields ) === 1 ) {
                $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories_from_attributes( $attr_slugs );
            } else {
                $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping, false, 85, null, $attr_slugs );
            }
            $term_ids = [];
            foreach ( $cats as $name ) {
                $term = get_term_by( 'name', $name, 'product_cat' );
                if ( $term && ! is_wp_error( $term ) ) {
                    $term_ids[] = (int) $term->term_id;
                }
            }
            wp_set_object_terms( $product_id, $term_ids ?: [], 'product_cat', ! $overwrite );

            $items[] = [
                'sku'   => $product->get_sku(),
                'title' => $product->get_name(),
                'cats'  => array_values( $cats ),
            ];
            $log[] = $product->get_sku() . ' - ' . $product->get_name() . ' => ' . implode( ', ', $cats );
        }

        $new_offset = $offset + count( $query->posts );
        $done       = $new_offset >= $total_query || empty( $query->posts );

        update_option( 'gm2_one_click_log', $log );

        wp_send_json_success(
            [
                'offset' => $new_offset,
                'total'  => (int) $total_query,
                'done'   => $done,
                'items'  => $items,
            ]
        );
    }

    /**
     * Build a mapping of category terms to their hierarchy.
     *
     * @return array<string,array>
     */
    protected static function build_mapping() {
        $terms = get_terms(
            [
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
            ]
        );

        if ( is_wp_error( $terms ) ) {
            return [];
        }

        $id_to_parent = [];
        $id_to_name   = [];
        $synonyms     = [];
        foreach ( $terms as $term ) {
            $id_to_parent[ $term->term_id ] = (int) $term->parent;
            $id_to_name[ $term->term_id ]   = $term->name;
            $syn = get_term_meta( $term->term_id, 'gm2_synonyms', true );
            if ( $syn ) {
                $synonyms[ $term->term_id ] = $syn;
            }
        }

        $mapping = [];
        foreach ( $id_to_name as $id => $name ) {
            $path = [];
            $curr = $id;
            while ( $curr && isset( $id_to_name[ $curr ] ) ) {
                array_unshift( $path, $id_to_name[ $curr ] );
                $curr = $id_to_parent[ $curr ] ?? 0;
            }
            $terms_list = array_merge( [ $name ], array_filter( array_map( 'trim', explode( ',', $synonyms[ $id ] ?? '' ) ) ) );
            foreach ( $terms_list as $term ) {
                $variants = [ $term ];
                if ( substr( $term, -1 ) !== 's' ) {
                    $variants[] = $term . 's';
                } else {
                    $variants[] = substr( $term, 0, -1 );
                }
                if ( $term === 'hole' ) {
                    $variants[] = 'hh';
                    $variants[] = 'holes';
                }
                if ( $term === 'lug' ) {
                    $variants[] = 'lugs';
                }
                foreach ( $variants as $v ) {
                    $key = Gm2_Category_Sort_Product_Category_Generator::normalize_text( $v );
                    if ( ! isset( $mapping[ $key ] ) ) {
                        $mapping[ $key ] = [];
                    }
                    $exists = false;
                    foreach ( $mapping[ $key ] as $existing ) {
                        if ( $existing === $path ) {
                            $exists = true;
                            break;
                        }
                    }
                    if ( ! $exists ) {
                        $mapping[ $key ][] = $path;
                    }
                }
            }
        }
        return $mapping;
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

        // Remove any existing CSV files from previous runs to avoid stale data.
        foreach ( glob( rtrim( $dir, '/' ) . '/*.csv' ) as $csv ) {
            $base = basename( $csv );
            if ( $base === 'category-tree.csv' ) {
                continue;
            }
            if ( strtolower( substr( $base, -4 ) ) !== '.csv' ) {
                continue;
            }
            unlink( $csv );
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

                $path_slugs[] = Gm2_Category_Sort_Product_Category_Generator::slugify_segment( $segment );
                $slug         = implode( '-', $path_slugs );

                // Previously branch CSVs were only written for categories that
                // have children.  This meant branch rules could not target
                // leaf categories.  Write a file for every slug so branch rules
                // work consistently regardless of depth.

                $file = rtrim( $dir, '/' ) . '/' . $slug . '.csv';

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
