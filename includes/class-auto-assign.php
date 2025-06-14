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
            ]
        );
    }

    /**
     * Render the admin page.
     */
    public static function admin_page() {
        $progress = get_option( 'gm2_auto_assign_progress', [ 'offset' => 0, 'log' => [] ] );
        $log      = (array) ( $progress['log'] ?? [] );
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
            <p><button id="gm2-auto-assign-start" class="button button-primary"><?php esc_html_e( 'Start Auto Assign', 'gm2-category-sort' ); ?></button></p>
            <div id="gm2-auto-assign-log" style="background:#fff;border:1px solid #ccc;padding:10px;max-height:400px;overflow:auto;">
                <?php foreach ( $log as $line ) : ?>
                    <div><?php echo esc_html( $line ); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
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
                        $mapping[ $key ] = $path;
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

        check_ajax_referer( 'gm2_auto_assign', 'nonce' );

        $reset     = ! empty( $_POST['reset'] );
        $overwrite = ! empty( $_POST['overwrite'] );
        $progress = get_option( 'gm2_auto_assign_progress', [ 'offset' => 0, 'log' => [] ] );
        if ( $reset ) {
            $progress = [ 'offset' => 0, 'log' => [] ];
        }
        $offset = (int) $progress['offset'];
        $log    = (array) $progress['log'];

        $mapping = self::build_mapping();

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

            $text  = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();
            foreach ( $product->get_attributes() as $attr ) {
                if ( $attr->is_taxonomy() ) {
                    $names = wc_get_product_terms( $product_id, $attr->get_name(), [ 'fields' => 'names' ] );
                    $text .= ' ' . implode( ' ', $names );
                } else {
                    $text .= ' ' . implode( ' ', array_map( 'sanitize_text_field', $attr->get_options() ) );
                }
            }

            $cats      = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );
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

        update_option( 'gm2_auto_assign_progress', [
            'offset' => $done ? 0 : $new_offset,
            'log'    => $log,
        ] );

        wp_send_json_success( [
            'offset' => $new_offset,
            'done'   => $done,
            'items'  => $items,
        ] );
    }

    /**
     * Handle WP-CLI auto assignment.
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public static function cli_run( $args, $assoc_args ) {
        $overwrite = ! empty( $assoc_args['overwrite'] );
        $mapping   = self::build_mapping();

        $query = new WP_Query(
            [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'posts_per_page' => -1,
                'orderby'        => 'ID',
                'order'          => 'ASC',
            ]
        );

        $progress = null;
        if ( class_exists( '\\WP_CLI\Utils' ) ) {
            $progress = \WP_CLI\Utils\make_progress_bar( 'Assigning categories', count( $query->posts ) );
        }

        foreach ( $query->posts as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                if ( $progress ) {
                    $progress->tick();
                }
                continue;
            }

            $text = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();
            foreach ( $product->get_attributes() as $attr ) {
                if ( $attr->is_taxonomy() ) {
                    $names = wc_get_product_terms( $product_id, $attr->get_name(), [ 'fields' => 'names' ] );
                    $text .= ' ' . implode( ' ', $names );
                } else {
                    $text .= ' ' . implode( ' ', array_map( 'sanitize_text_field', $attr->get_options() ) );
                }
            }

            $cats     = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );
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

        if ( $progress ) {
            $progress->finish();
        }

        \WP_CLI::success( 'Auto assign complete.' );
    }
}
