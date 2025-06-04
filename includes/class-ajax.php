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

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => wc_get_loop_prop('per_page'),
            'paged'          => 1,
            'tax_query'      => $tax_query,
        ];

        // Setup loop properties so the product grid follows archive settings.
        wc_setup_loop();

        $query = new WP_Query($args);

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
        wc_reset_loop();

        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }
}
