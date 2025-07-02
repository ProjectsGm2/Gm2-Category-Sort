<?php
class Gm2_Category_Sort_Enqueuer {
    
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('elementor/editor/after_enqueue_scripts', [__CLASS__, 'enqueue_editor_assets']);
    }

    public static function enqueue_assets($force = false) {
        // Don't enqueue in the admin area unless doing AJAX or forced (Elementor editor)
        if (!$force && is_admin() && !wp_doing_ajax()) {
            return;
        }
        
        $css_ver = filemtime(GM2_CAT_SORT_PATH . 'assets/css/style.css');
        wp_enqueue_style(
            'gm2-category-sort-style',
            GM2_CAT_SORT_URL . 'assets/css/style.css',
            [],
            $css_ver
        );

        $polyfill_ver = filemtime(GM2_CAT_SORT_PATH . 'assets/vendor/url-polyfill.min.js');
        wp_enqueue_script(
            'gm2-url-polyfill',
            GM2_CAT_SORT_URL . 'assets/vendor/url-polyfill.min.js',
            [],
            $polyfill_ver,
            true
        );

        $legacy      = gm2_is_legacy_browser();
        $js_path     = $legacy ? 'assets/dist/frontend.js' : 'assets/js/frontend.js';
        $js_ver      = filemtime(GM2_CAT_SORT_PATH . $js_path);
        wp_enqueue_script(
            'gm2-category-sort-script',
            GM2_CAT_SORT_URL . $js_path,
            ['jquery', 'gm2-url-polyfill'],
            $js_ver,
            true
        );

        $nonce = wp_create_nonce('gm2_filter_products');
        $sitemap_nonce = wp_create_nonce('gm2_generate_sitemap');
        wp_localize_script(
            'gm2-category-sort-script',
            'gm2CategorySort',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => $nonce,
                'sitemap_nonce'   => $sitemap_nonce,
                'sitemap_success' => __( 'Sitemap generated successfully.', 'gm2-category-sort' ),
                'error_message'   => __( 'Error loading products. Please refresh the page.', 'gm2-category-sort' ),
            ]
        );
    }

    public static function enqueue_editor_assets() {
        self::enqueue_assets(true);
    }
}

