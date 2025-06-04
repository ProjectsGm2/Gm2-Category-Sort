<?php
class Gm2_Category_Sort_Enqueuer {
    
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }
    
    public static function enqueue_assets() {
        // Don't enqueue in the admin area unless doing AJAX
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'gm2-category-sort-style',
            GM2_CAT_SORT_URL . 'assets/css/style.css',
            [],
            GM2_CAT_SORT_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'gm2-category-sort-script',
            GM2_CAT_SORT_URL . 'assets/js/frontend.js',
            ['jquery'],
            GM2_CAT_SORT_VERSION,
            true
        );

        wp_localize_script(
            'gm2-category-sort-script',
            'gm2CategorySort',
            ['ajax_url' => admin_url('admin-ajax.php')]
        );
    }
}

