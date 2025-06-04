<?php
class Gm2_Category_Sort_Enqueuer {
    
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }
    
    public static function enqueue_assets() {
        // Only on WooCommerce pages
        if (!is_shop() && !is_product_category() && !is_product_taxonomy() && !is_search()) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'gm2-category-sort-style',
            GM2_CAT_SORT_URL . 'assets/css/style.css',
            [],
            '1.0'
        );
        
        // JavaScript
        wp_enqueue_script(
            'gm2-category-sort-script',
            GM2_CAT_SORT_URL . 'assets/js/frontend.js',
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script(
            'gm2-category-sort-script',
            'gm2CategorySort',
            [
                'ajax_url' => admin_url('admin-ajax.php')
            ]
        );
    }
}
