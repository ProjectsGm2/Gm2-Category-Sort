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
            filemtime(GM2_CAT_SORT_PATH . 'assets/css/style.css')
        );
        
        // JavaScript
        wp_enqueue_script(
            'gm2-category-sort-script',
            GM2_CAT_SORT_URL . 'assets/js/frontend.js',
            ['jquery'],
            filemtime(GM2_CAT_SORT_PATH . 'assets/js/frontend.js'),
            true
        );
    }
}
