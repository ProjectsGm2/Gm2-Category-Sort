<?php
class Gm2_Category_Sort_Canonical {
    public static function init() {
        add_action('wp_head', [__CLASS__, 'maybe_output_canonical']);
    }

    public static function maybe_output_canonical() {
        $alt = get_query_var( 'gm2_alt_base' );
        if ( $alt ) {
            $product_slug = get_query_var( 'product' );
            if ( $product_slug ) {
                $product = get_page_by_path( $product_slug, OBJECT, 'product' );
                if ( $product ) {
                    $link = get_permalink( $product );
                    if ( $link ) {
                        echo '<link rel="canonical" href="' . esc_url( $link ) . '" />\n';
                    }
                }
                return;
            }
            $slug = get_query_var( 'product_cat' );
            $term = $slug ? get_term_by( 'slug', $slug, 'product_cat' ) : false;
            if ( $term && ! is_wp_error( $term ) ) {
                $link = get_term_link( $term );
                if ( ! is_wp_error( $link ) ) {
                    echo '<link rel="canonical" href="' . esc_url( $link ) . '" />\n';
                }
            }
            return;
        }

        if (!self::has_filter_params()) {
            return;
        }

        $canonical = '';
        if (is_product_taxonomy()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                // Check for a primary category and canonicalize to it when set.
                $primary_id = get_term_meta( $term->term_id, 'gm2_primary_category', true );
                $primary_id = absint( $primary_id );
                if ( $primary_id ) {
                    $primary = get_term( $primary_id, 'product_cat' );
                    if ( $primary && ! is_wp_error( $primary ) ) {
                        $canonical = get_term_link( $primary );
                    }
                }

                if ( ! $canonical ) {
                    $canonical = get_term_link( $term );
                }
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
