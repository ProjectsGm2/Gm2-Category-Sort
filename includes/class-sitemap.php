<?php
class Gm2_Category_Sort_Sitemap {

    /**
     * Generate sitemap XML file for category filter combinations.
     *
     * @return string Path to the generated sitemap file.
     */
    public static function generate() {
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        $shop_url = function_exists('wc_get_page_permalink')
            ? wc_get_page_permalink('shop')
            : home_url('/');

        $urls     = [];
        $term_ids = [];
        foreach ($terms as $term) {
            $term_ids[] = $term->term_id;
            $urls[]     = add_query_arg(
                ['gm2_cat' => $term->term_id],
                $shop_url
            );
        }

        $count = count($term_ids);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $urls[] = add_query_arg([
                    'gm2_cat'        => $term_ids[$i] . ',' . $term_ids[$j],
                    'gm2_filter_type'=> 'advanced',
                ], $shop_url);
            }
        }

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>'
        );
        foreach ($urls as $loc) {
            $url = $xml->addChild('url');
            $url->addChild('loc', esc_url_raw($loc));
        }

        $upload_dir = wp_upload_dir();
        $file       = trailingslashit($upload_dir['basedir']) . 'gm2-category-sort-sitemap.xml';
        $xml->asXML($file);

        return $file;
    }

    /**
     * Initialize sitemap functionality.
     *
     * Registers CLI and AJAX handlers.
     */
    public static function init() {
        self::register_cli();
        add_action('wp_ajax_gm2_generate_sitemap', [__CLASS__, 'ajax_generate']);
    }

    /**
     * Register WP-CLI command for generating the sitemap.
     */
    public static function register_cli() {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('gm2-category-sort sitemap', [__CLASS__, 'cli_generate']);
        }
    }

    /**
     * Handle WP-CLI sitemap generation.
     */
    public static function cli_generate() {
        $file = self::generate();
        if ($file) {
            \WP_CLI::success("Sitemap generated at: $file");
        } else {
            \WP_CLI::error('Failed to generate sitemap.');
        }
    }

    /**
     * Handle AJAX sitemap generation.
     */
    public static function ajax_generate() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('unauthorized');
        }

        check_ajax_referer('gm2_generate_sitemap', 'nonce');
        $file = self::generate();
        if ($file) {
            wp_send_json_success(['file' => $file]);
        }

        wp_send_json_error('failed');
    }
}
