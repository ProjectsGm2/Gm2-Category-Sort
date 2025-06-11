<?php
class Gm2_Category_Sort_Canonical {
    public static function init() {
        add_action('wp_head', [__CLASS__, 'maybe_output_canonical']);
    }

    public static function maybe_output_canonical() {
        if (!self::has_filter_params()) {
            return;
        }

        $canonical = '';

        if (is_singular('product')) {
            $canonical = get_permalink();
        } elseif (is_product_taxonomy()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                $canonical = get_term_link($term);
            }
        } elseif (function_exists('wc_get_page_permalink')) {
            $canonical = wc_get_page_permalink('shop');
        }

        if ($canonical) {
            echo '<link rel="canonical" href="' . esc_url($canonical) . '" />\n';
        }
    }

    private static function has_filter_params() {
        foreach (['gm2_cat', 'gm2_filter_type', 'gm2_simple_operator'] as $key) {
            if (isset($_GET[$key])) {
                return true;
            }
        }
        return false;
    }
}
