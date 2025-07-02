<?php
class Gm2_Category_Sort_Ajax {
    public static function init() {
        add_action('wp_ajax_gm2_filter_products', [__CLASS__, 'filter_products']);
        add_action('wp_ajax_nopriv_gm2_filter_products', [__CLASS__, 'filter_products']);
    }

    /**
     * Handle AJAX requests to filter and sort products.
     *
     * Expected POST parameters:
     * - gm2_cat: comma-separated list of category IDs.
     * - gm2_filter_type: "simple" or "advanced" filtering mode.
     * - gm2_simple_operator: tax query operator for simple mode.
     * - gm2_paged: current page number.
     * - gm2_per_page: number of products per page.
     * - orderby: orderby string for sorting products.
     * - gm2_columns: number of columns in the product loop.
     * - gm2_rows:    number of rows in the product loop (optional).
     *
     * Sends a JSON response with the rendered product HTML, result count and
     * pagination for the filtered and sorted products.
     *
     * @return void
     */
    public static function filter_products() {
        check_ajax_referer('gm2_filter_products', 'gm2_nonce');

        $term_ids = [];
        if (!empty($_POST['gm2_cat'])) {
            $term_ids = array_map('intval', explode(',', $_POST['gm2_cat']));
        }

        // When no categories are selected on a product category page,
        // default to the current category so the results match the
        // archive context instead of returning all products.
        if (empty($term_ids) && function_exists('is_product_category') && is_product_category()) {
            $current = get_queried_object();
            if ($current && isset($current->term_id)) {
                $term_ids = [ (int) $current->term_id ];
            }
        }
        $filter_type = sanitize_key($_POST['gm2_filter_type'] ?? 'simple');
        $simple_operator = sanitize_key($_POST['gm2_simple_operator'] ?? 'IN');

        $tax_query = [];
        if (!empty($term_ids)) {
            if ($filter_type === 'advanced' && class_exists('Gm2_Category_Sort_Query_Handler')) {
                $category_query = Gm2_Category_Sort_Query_Handler::build_advanced_query($term_ids);
            } else {
                $category_query = [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $term_ids,
                    'operator' => $simple_operator,
                    'include_children' => true,
                ];
            }
            $tax_query[] = $category_query;
        }

        $paged = isset($_POST['gm2_paged']) ? absint($_POST['gm2_paged']) : 1;

        $columns  = isset($_POST['gm2_columns']) ? absint($_POST['gm2_columns']) : 0;
        $rows     = isset($_POST['gm2_rows']) ? absint($_POST['gm2_rows']) : 0;
        $per_page = isset($_POST['gm2_per_page']) ? absint($_POST['gm2_per_page']) : 0;
        $widget_type = isset($_POST['gm2_widget_type']) ? sanitize_key($_POST['gm2_widget_type']) : '';
        $settings = [];
        if (isset($_POST['gm2_widget_settings'])) {
            $json = wp_unslash($_POST['gm2_widget_settings']);
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }
        if (!$per_page) {
            if ($columns && $rows) {
                $per_page = $columns * $rows;
            } else {
                // Fall back to the loop's per_page setting when rows are unknown.
                $per_page = wc_get_loop_prop('per_page');
            }
        }

        if (!$rows && $columns && $per_page) {
            $rows = (int) ceil($per_page / $columns);
        }

        // If still zero, use the shop default via the woocommerce_products_per_page filter.
        if ( ! $per_page ) {
            $per_page = apply_filters( 'woocommerce_products_per_page', 12 );
        }

        $orderby = isset($_POST['orderby']) ? wc_clean($_POST['orderby']) : '';

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => max(1, $paged),
            'tax_query'      => $tax_query,
        ];

        if ( $orderby ) {
            $ordering_args = gm2_get_orderby_args( $orderby );
            $args          = array_merge( $args, $ordering_args );
        }

        // Respect column settings from the current product archive

        wc_setup_loop([
            'columns'      => $columns ?: wc_get_loop_prop('columns'),
            'per_page'     => $per_page,
            'current_page' => $args['paged'],
        ]);

        if ( $orderby && ! empty( $ordering_args['orderby'] ) ) {
            wc_set_loop_prop( 'orderby', $ordering_args['orderby'] );
        }
        if ( $orderby && ! empty( $ordering_args['order'] ) ) {
            wc_set_loop_prop( 'order', $ordering_args['order'] );
        }

        $query = new WP_Query($args);

        wc_set_loop_prop('total', $query->found_posts);
        wc_set_loop_prop('total_pages', $query->max_num_pages);

        $prev_wp_query = $GLOBALS['wp_query'];
        $GLOBALS['wp_query'] = $query;

        ob_start();
        // Product markup is generated via WooCommerce template functions by
        // default. When filtering a list created with Elementor's Products
        // widget, use the widget renderer so the wrapper classes and data
        // attributes remain intact.
        if ($query->have_posts()) {
            $widget_class = null;

            if (
                $widget_type &&
                strpos($widget_type, 'archive-products') === 0 &&
                class_exists('\\ElementorPro\\Modules\\Woocommerce\\Widgets\\Archive_Products')
            ) {
                $widget_class = '\\ElementorPro\\Modules\\Woocommerce\\Widgets\\Archive_Products';
            } elseif (
                $widget_type &&
                strpos($widget_type, 'products') !== false &&
                class_exists('\\ElementorPro\\Modules\\Woocommerce\\Widgets\\Products')
            ) {
                $widget_class = '\\ElementorPro\\Modules\\Woocommerce\\Widgets\\Products';
            }

            if ($widget_class) {
                $widget = new $widget_class();
                if (method_exists($widget, 'set_settings')) {
                    $widget->set_settings( $settings );
                }
                if (method_exists($widget, 'render')) {
                    $widget->render();
                }
            } else {
                woocommerce_product_loop_start();
                while ($query->have_posts()) {
                    $query->the_post();
                    wc_get_template_part('content', 'product');
                }
                woocommerce_product_loop_end();
            }
        } else {
            woocommerce_no_products_found();
        }
        wp_reset_postdata();

        $html = ob_get_clean();

        if ($rows) {
            $html = preg_replace('/<ul class="products([^"]*)">/', '<ul class="products$1" data-rows="' . $rows . '">', $html, 1);
        }

        ob_start();
        woocommerce_result_count();
        $result_count = ob_get_clean();

        ob_start();
        woocommerce_pagination();
        $pagination = ob_get_clean();

        $GLOBALS['wp_query'] = $prev_wp_query;

        wc_reset_loop();

        wp_send_json_success([
            'html'  => $html,
            'count' => $result_count,
            'pagination' => $pagination,
        ]);
    }
}
