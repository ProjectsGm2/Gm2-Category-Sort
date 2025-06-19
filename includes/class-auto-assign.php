<?php
/**
 * Automatically assign product categories by analyzing product text.
 */
class Gm2_Category_Sort_Auto_Assign {

    /**
     * Initialize hooks.
     */
    public static function init() {
        self::register_cli();
        add_action( 'admin_menu', [ __CLASS__, 'register_admin_page' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
        add_action( 'wp_ajax_gm2_auto_assign_step', [ __CLASS__, 'ajax_step' ] );
        add_action( 'wp_ajax_gm2_auto_assign_search', [ __CLASS__, 'ajax_search_products' ] );
        add_action( 'wp_ajax_gm2_auto_assign_selected', [ __CLASS__, 'ajax_assign_selected' ] );
        add_action( 'wp_ajax_gm2_reset_product_categories', [ __CLASS__, 'ajax_reset_product_categories' ] );
    }

    /**
     * Register WP-CLI command.
     */
    public static function register_cli() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'gm2-category-sort auto-assign', [ __CLASS__, 'cli_run' ] );
        }
    }

    /**
     * Register the Tools page.
     */
    public static function register_admin_page() {
        add_management_page(
            __( 'Auto Assign Categories', 'gm2-category-sort' ),
            __( 'Auto Assign Categories', 'gm2-category-sort' ),
            'manage_options',
            'gm2-auto-assign',
            [ __CLASS__, 'admin_page' ]
        );
    }

    /**
     * Enqueue admin JavaScript.
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'tools_page_gm2-auto-assign' ) {
            return;
        }

        $ver = file_exists( GM2_CAT_SORT_PATH . 'assets/js/auto-assign.js' ) ? filemtime( GM2_CAT_SORT_PATH . 'assets/js/auto-assign.js' ) : GM2_CAT_SORT_VERSION;
        wp_enqueue_script(
            'gm2-auto-assign',
            GM2_CAT_SORT_URL . 'assets/js/auto-assign.js',
            [ 'jquery' ],
            $ver,
            true
        );

        wp_localize_script(
            'gm2-auto-assign',
            'gm2AutoAssign',
            [
                'nonce'     => wp_create_nonce( 'gm2_auto_assign' ),
                'completed' => __( 'Auto assign complete.', 'gm2-category-sort' ),
                'error'     => __( 'Error assigning categories.', 'gm2-category-sort' ),
                'resetDone' => __( 'All categories reset.', 'gm2-category-sort' ),
            ]
        );
    }

    /**
     * Render the admin page.
     */
    public static function admin_page() {
        $log = get_option( 'gm2_auto_assign_log', [] );
        if ( ! is_array( $log ) ) {
            $log = [];
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Auto Assign Categories', 'gm2-category-sort' ); ?></h1>
            <p><?php esc_html_e( 'Analyze products and assign categories based on product text and attribute values.', 'gm2-category-sort' ); ?></p>
            <p>
                <label>
                    <input type="radio" name="gm2_overwrite" value="0" checked>
                    <?php esc_html_e( 'Add categories', 'gm2-category-sort' ); ?>
                </label>
                &nbsp;
                <label>
                    <input type="radio" name="gm2_overwrite" value="1">
                    <?php esc_html_e( 'Overwrite categories', 'gm2-category-sort' ); ?>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" id="gm2_fuzzy" value="1">
                    <?php esc_html_e( 'Use fuzzy matching', 'gm2-category-sort' ); ?>
                </label>
            </p>
            <p>
                <button id="gm2-auto-assign-start" class="button button-primary">
                    <?php esc_html_e( 'Start Auto Assign', 'gm2-category-sort' ); ?>
                </button>
                &nbsp;
                <button id="gm2-reset-categories" class="button">
                    <?php esc_html_e( 'Reset All Categories', 'gm2-category-sort' ); ?>
                </button>
            </p>
            <p><progress id="gm2-reset-progress" value="0" max="100" style="display:none;width:100%;"></progress></p>
            <div id="gm2-auto-assign-log" style="background:#fff;border:1px solid #ccc;padding:10px;max-height:400px;overflow:auto;">
                <?php foreach ( $log as $line ) : ?>
                    <div><?php echo esc_html( $line ); ?></div>
                <?php endforeach; ?>
            </div>

            <hr />
            <h2><?php esc_html_e( 'Search and Assign', 'gm2-category-sort' ); ?></h2>
            <p>
                <select id="gm2-search-fields" multiple style="min-width:220px;">
                    <option value="title"><?php esc_html_e( 'Product Title', 'gm2-category-sort' ); ?></option>
                    <option value="description"><?php esc_html_e( 'Product Description', 'gm2-category-sort' ); ?></option>
                    <option value="attributes"><?php esc_html_e( 'Attributes', 'gm2-category-sort' ); ?></option>
                </select>
                <input type="text" id="gm2-search-terms" style="width:200px;" />
                <button id="gm2-search-btn" class="button"><?php esc_html_e( 'Search', 'gm2-category-sort' ); ?></button>
                &nbsp;
                <button id="gm2-reset-search-btn" class="button"><?php esc_html_e( 'Reset Search', 'gm2-category-sort' ); ?></button>
            </p>
            <p><progress id="gm2-search-progress" value="0" max="100" style="display:none;width:100%;"></progress></p>
            <p style="position:relative;">
                <input type="text" id="gm2-product-search" placeholder="<?php esc_attr_e( 'Search by SKU or title', 'gm2-category-sort' ); ?>" style="width:260px;" autocomplete="off" />
                <span id="gm2-live-spinner" class="spinner" style="float:none;margin-top:4px;display:none;"></span>
                <ul id="gm2-search-dropdown" style="display:none;position:absolute;left:0;right:0;background:#fff;border:1px solid #ccc;padding:5px;max-height:150px;overflow:auto;list-style:none;margin:2px 0 0;"></ul>
            </p>
            <ul id="gm2-product-list" style="background:#fff;border:1px solid #ccc;padding:5px;max-height:200px;overflow:auto;"></ul>
            <p>
                <label for="gm2-category-select"><?php esc_html_e( 'Categories', 'gm2-category-sort' ); ?></label><br>
                <select id="gm2-category-select" multiple style="width:800px;height:300px;">
                    <?php echo self::get_category_option_tree(); ?>
                </select>
            </p>
            <p><button id="gm2-assign-btn" class="button button-primary"><?php esc_html_e( 'Assign', 'gm2-category-sort' ); ?></button></p>
        </div>
        <?php
    }

    /**
     * Generate option elements for the product category dropdown in a tree structure.
     *
     * @param int $parent Parent term ID.
     * @param int $depth  Current tree depth.
     * @return string
     */
    protected static function get_category_option_tree( $parent = 0, $depth = 0 ) {
        $terms = get_terms(
            [
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'parent'     => $parent,
                'orderby'    => 'name',
            ]
        );

        if ( is_wp_error( $terms ) ) {
            return '';
        }

        $html = '';
        foreach ( $terms as $term ) {
            $indent = $depth > 0 ? str_repeat( '&nbsp;', $depth * 3 ) . '&#8211; ' : '';
            $html  .= '<option value="' . esc_attr( $term->term_id ) . '">' . $indent . esc_html( $term->name ) . '</option>';
            $html  .= self::get_category_option_tree( $term->term_id, $depth + 1 );
        }

        return $html;
    }

    /**
     * Build mapping of synonyms to category hierarchy.
     *
     * @return array<string,array>
     */
    protected static function build_mapping() {
        $terms = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ] );

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
     * Handle AJAX processing of products.
     */
    public static function ajax_step() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized' );
        }

        if ( ! check_ajax_referer( 'gm2_auto_assign', 'nonce', false ) ) {
            check_ajax_referer( 'gm2_one_click_assign', 'nonce' );
        }

        $reset     = ! empty( $_POST['reset'] );
        $overwrite = ! empty( $_POST['overwrite'] );
        $fuzzy     = ! empty( $_POST['fuzzy'] );
        $progress  = get_option( 'gm2_auto_assign_progress', [ 'offset' => 0 ] );
        $log       = get_option( 'gm2_auto_assign_log', [] );
        if ( $reset ) {
            $progress = [ 'offset' => 0 ];
            $log      = [];
        }
        $offset = (int) ( $progress['offset'] ?? 0 );
        $log    = (array) $log;

        $mapping = self::build_mapping();
        $upload = function_exists( 'wp_upload_dir' ) ? wp_upload_dir() : [ 'basedir' => dirname( __DIR__ ) ];
        $export_dir = trailingslashit( $upload['basedir'] ) . 'gm2-category-sort/mapping-logs';
        Gm2_Category_Sort_Product_Category_Generator::export_brand_model_csv( $mapping, $export_dir );
        Gm2_Category_Sort_Product_Category_Generator::export_category_tree_csv( $export_dir );

        $query = new WP_Query( [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ] );

        $items = [];
        foreach ( $query->posts as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }

            $text  = $product->get_name();

            $cats      = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping, $fuzzy, 85, $export_dir );
            $term_ids  = [];
            foreach ( $cats as $name ) {
                $term = get_term_by( 'name', $name, 'product_cat' );
                if ( $term && ! is_wp_error( $term ) ) {
                    $term_ids[] = (int) $term->term_id;
                }
            }
            if ( $term_ids ) {
                wp_set_object_terms( $product_id, $term_ids, 'product_cat', ! $overwrite );
            }

            $items[] = [
                'sku'   => $product->get_sku(),
                'title' => $product->get_name(),
                'cats'  => $cats,
            ];
            $log[] = $product->get_sku() . ' - ' . $product->get_name() . ' => ' . implode( ', ', $cats );
        }

        $new_offset = $offset + count( $query->posts );
        $done       = $new_offset >= $query->found_posts || empty( $query->posts );

        update_option( 'gm2_auto_assign_log', $log );

        if ( $done ) {
            delete_option( 'gm2_auto_assign_progress' );
        } else {
            update_option( 'gm2_auto_assign_progress', [ 'offset' => $new_offset ] );
        }

        wp_send_json_success( [
            'offset' => $new_offset,
            'done'   => $done,
            'items'  => $items,
        ] );
    }

    /**
     * Search products by fields for manual assignment.
     */
    public static function ajax_search_products() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized' );
        }

        if ( ! check_ajax_referer( 'gm2_auto_assign', 'nonce', false ) ) {
            check_ajax_referer( 'gm2_one_click_assign', 'nonce' );
        }

        $fields = array_map( 'sanitize_key', (array) ( $_POST['fields'] ?? [] ) );
        $search = sanitize_text_field( $_POST['search'] ?? '' );
        $offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
        $batch  = isset( $_POST['batch'] ) ? max( 1, (int) $_POST['batch'] ) : 100;

        if ( $search === '' ) {
            wp_send_json_success( [ 'items' => [] ] );
        }

        $query = new WP_Query( [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $batch,
            'offset'         => $offset,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ] );

        $items = [];
        foreach ( $query->posts as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }

            $text = '';
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
                        $text .= ' ' . implode( ' ', $names );
                    } else {
                        $text .= ' ' . implode( ' ', array_map( 'sanitize_text_field', $attr->get_options() ) );
                    }
                }
            }

            if ( stripos( $text, $search ) !== false ) {
                $items[] = [
                    'id'    => $product_id,
                    'sku'   => $product->get_sku(),
                    'title' => $product->get_name(),
                ];
            }
        }

        $new_offset = $offset + count( $query->posts );
        $processed  = min( $new_offset, $query->found_posts );

        wp_send_json_success(
            [
                'items'     => $items,
                'processed' => $processed,
                'total'     => (int) $query->found_posts,
                'done'      => $processed >= $query->found_posts,
            ]
        );
    }

    /**
     * Assign selected categories to given products.
     */
    public static function ajax_assign_selected() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized' );
        }

        if ( ! check_ajax_referer( 'gm2_auto_assign', 'nonce', false ) ) {
            check_ajax_referer( 'gm2_one_click_assign', 'nonce' );
        }

        $products  = array_map( 'intval', (array) ( $_POST['products'] ?? [] ) );
        $categories = array_map( 'intval', (array) ( $_POST['categories'] ?? [] ) );
        $overwrite = ! empty( $_POST['overwrite'] );

        if ( empty( $products ) || empty( $categories ) ) {
            wp_send_json_error( 'missing' );
        }

        foreach ( $products as $id ) {
            wp_set_object_terms( $id, $categories, 'product_cat', ! $overwrite );
        }

        wp_send_json_success();
    }

    /**
     * Remove all categories from products in batches.
     */
    public static function ajax_reset_product_categories() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized' );
        }

        if ( ! check_ajax_referer( 'gm2_auto_assign', 'nonce', false ) ) {
            check_ajax_referer( 'gm2_one_click_assign', 'nonce' );
        }

        $reset    = ! empty( $_POST['reset'] );
        $progress = get_option( 'gm2_reset_progress', [ 'offset' => 0 ] );
        if ( $reset ) {
            $progress = [ 'offset' => 0 ];
            delete_option( 'gm2_auto_assign_progress' );
            delete_option( 'gm2_auto_assign_log' );
            delete_option( 'gm2_one_click_log' );
            wp_defer_term_counting( true );
        }

        $offset     = (int) $progress['offset'];
        $total      = (int) wp_count_posts( 'product' )->publish;
        $batch_size = 500;

        global $wpdb;

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' ORDER BY ID ASC LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            )
        );

        if ( $ids ) {
            $tax_ids = $wpdb->get_col( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy='product_cat'" );
            if ( $tax_ids ) {
                $wpdb->query(
                    "DELETE FROM {$wpdb->term_relationships} WHERE object_id IN (" . implode( ',', array_map( 'absint', $ids ) ) . ") AND term_taxonomy_id IN (" . implode( ',', array_map( 'absint', $tax_ids ) ) . ")"
                );
            }
            if ( function_exists( "clean_object_term_cache" ) ) {
                clean_object_term_cache( $ids, "product" );
            }
        }

        $new_offset = $offset + count( $ids );
        $done       = $new_offset >= $total || empty( $ids );

        if ( $done ) {
            delete_option( 'gm2_reset_progress' );
            delete_option( 'gm2_auto_assign_progress' );
            delete_option( 'gm2_auto_assign_log' );
            delete_option( 'gm2_one_click_log' );
            wp_defer_term_counting( false );
        } else {
            update_option( 'gm2_reset_progress', [ 'offset' => $new_offset ] );
        }

        wp_send_json_success(
            [
                'offset' => $new_offset,
                'total'  => $total,
                'done'   => $done,
            ]
        );
    }


    /**
     * Handle WP-CLI auto assignment.
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public static function cli_run( $args, $assoc_args ) {
        $overwrite = ! empty( $assoc_args['overwrite'] );
        $fuzzy     = ! empty( $assoc_args['fuzzy'] );
        $mapping   = self::build_mapping();
        $upload    = function_exists( 'wp_upload_dir' ) ? wp_upload_dir() : [ 'basedir' => dirname( __DIR__ ) ];
        $export_dir = trailingslashit( $upload['basedir'] ) . 'gm2-category-sort/mapping-logs';
        Gm2_Category_Sort_Product_Category_Generator::export_brand_model_csv( $mapping, $export_dir );
        Gm2_Category_Sort_Product_Category_Generator::export_category_tree_csv( $export_dir );

        $total    = wp_count_posts( 'product' )->publish;
        $progress = null;
        if ( class_exists( '\WP_CLI\Utils' ) ) {
            $progress = \WP_CLI\Utils\make_progress_bar( 'Assigning categories', $total );
        }

        $offset     = 0;
        $batch_size = 500;

        while ( true ) {
            $query = new WP_Query(
                [
                    'post_type'      => 'product',
                    'post_status'    => 'publish',
                    'fields'         => 'ids',
                    'posts_per_page' => $batch_size,
                    'offset'         => $offset,
                    'orderby'        => 'ID',
                    'order'          => 'ASC',
                ]
            );

            if ( empty( $query->posts ) ) {
                break;
            }

            foreach ( $query->posts as $product_id ) {
                $product = wc_get_product( $product_id );
                if ( ! $product ) {
                    if ( $progress ) {
                        $progress->tick();
                    }
                    continue;
                }

                $text = $product->get_name();

                $cats     = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping, $fuzzy, 85, $export_dir );
                $term_ids = [];
                foreach ( $cats as $name ) {
                    $term = get_term_by( 'name', $name, 'product_cat' );
                    if ( $term && ! is_wp_error( $term ) ) {
                        $term_ids[] = (int) $term->term_id;
                    }
                }
                if ( $term_ids ) {
                    wp_set_object_terms( $product_id, $term_ids, 'product_cat', ! $overwrite );
                }

                if ( $progress ) {
                    $progress->tick();
                }
            }

            $offset += $batch_size;
        }
        if ( $progress ) {
            $progress->finish();
        }

        \WP_CLI::success( 'Auto assign complete.' );
    }
}
