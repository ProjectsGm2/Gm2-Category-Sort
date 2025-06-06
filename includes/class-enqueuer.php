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
        
        $css_ver = filemtime(GM2_CAT_SORT_PATH . 'assets/css/style.css');
        wp_enqueue_style(
            'gm2-category-sort-style',
            GM2_CAT_SORT_URL . 'assets/css/style.css',
            [],
            $css_ver
        );

        $js_ver = filemtime(GM2_CAT_SORT_PATH . 'assets/js/frontend.js');
        wp_enqueue_script(
            'gm2-category-sort-script',
            GM2_CAT_SORT_URL . 'assets/js/frontend.js',
            ['jquery'],
            $js_ver,
            true
        );
         
        wp_localize_script(
            'gm2-category-sort-script',
            'gm2CategorySort',
            ['ajax_url' => admin_url('admin-ajax.php')]
        );
    }
}
