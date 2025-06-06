<?php
class Gm2_Category_Sort_Ajax {
    public static function init() {
        add_action('wp_ajax_gm2_filter_products', [__CLASS__, 'filter_products']);
        add_action('wp_ajax_nopriv_gm2_filter_products', [__CLASS__, 'filter_products']);
    }

    public static function filter_products() {
        $term_ids = [];
        if (!empty($_POST['gm2_cat'])) {
            $term_ids = array_map('intval', explode(',', $_POST['gm2_cat']));
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

        $per_page = isset($_POST['gm2_per_page']) ? absint($_POST['gm2_per_page']) : 0;
        if (!$per_page) {
            $per_page = wc_get_loop_prop('per_page');
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
            $ordering_args = WC()->query->get_catalog_ordering_args( $orderby );
            $args = array_merge( $args, $ordering_args );
        }

        // Respect column settings from the current product archive
        $columns = isset($_POST['gm2_columns']) ? absint($_POST['gm2_columns']) : 0;

        wc_setup_loop([
            'columns'      => $columns ?: wc_get_loop_prop('columns'),
            'per_page'     => $per_page,
            'current_page' => $args['paged'],
        ]);

        $query = new WP_Query($args);

        wc_set_loop_prop('total', $query->found_posts);
        wc_set_loop_prop('total_pages', $query->max_num_pages);

        $prev_wp_query = $GLOBALS['wp_query'];
        $GLOBALS['wp_query'] = $query;
        WC()->query->remove_ordering_args();

      
        ob_start();
        if ($query->have_posts()) {
            woocommerce_product_loop_start();
            while ($query->have_posts()) {
                $query->the_post();
                wc_get_template_part('content', 'product');
            }
            woocommerce_product_loop_end();
        } else {
            woocommerce_no_products_found();
        }
        wp_reset_postdata();
        
        $html = ob_get_clean();

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