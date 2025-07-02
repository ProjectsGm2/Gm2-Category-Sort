<?php
namespace {
require_once __DIR__ . '/../includes/class-category-importer.php';
require_once __DIR__ . '/../includes/class-product-category-importer.php';
require_once __DIR__ . '/../includes/class-product-category-generator.php';
require_once __DIR__ . '/../includes/class-renderer.php';

// Minimal WP_Error class for tests.
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public $errors = [];
        public function __construct( $code = '', $message = '' ) {
            if ( $code ) {
                $this->errors[ $code ] = [ $message ];
            }
        }
        public function get_error_message() {
            $error = reset( $this->errors );
            return $error ? $error[0] : '';
        }
    }
}

function is_wp_error( $thing ) {
    return $thing instanceof WP_Error;
}

function __( $text, $domain = null ) {
    return $text;
}

// Globals for stubbed WordPress functions.
$GLOBALS['gm2_test_terms'] = [];
$GLOBALS['gm2_next_id'] = 1;
$GLOBALS['gm2_insert_calls'] = [];
$GLOBALS['gm2_meta_updates'] = [];
$GLOBALS['gm2_products'] = [];
$GLOBALS['gm2_set_terms_calls'] = [];
$GLOBALS['gm2_attributes'] = [];
$GLOBALS['gm2_attr_terms'] = [];

function gm2_test_reset_terms() {
    $GLOBALS['gm2_test_terms'] = [];
    $GLOBALS['gm2_attr_terms'] = [];
    $GLOBALS['gm2_next_id'] = 1;
    $GLOBALS['gm2_insert_calls'] = [];
    $GLOBALS['gm2_meta_updates'] = [];
    $GLOBALS['gm2_products'] = [];
    $GLOBALS['gm2_set_terms_calls'] = [];
    $GLOBALS['gm2_attributes'] = [];
}

gm2_test_reset_terms();

function term_exists( $name, $taxonomy = null, $parent = 0 ) {
    if ( $taxonomy && $taxonomy !== 'product_cat' ) {
        $terms = $GLOBALS['gm2_attr_terms'][ $taxonomy ] ?? [];
        if ( isset( $terms[ $name ] ) ) {
            return [ 'term_id' => $terms[ $name ] ];
        }
        return false;
    }
    $terms = $GLOBALS['gm2_test_terms'];
    if ( isset( $terms[ $parent ][ $name ] ) ) {
        return [ 'term_id' => $terms[ $parent ][ $name ] ];
    }
    return false;
}

function wp_insert_term( $name, $taxonomy, $args = [] ) {
    $parent = $args['parent'] ?? 0;
    $id = $GLOBALS['gm2_next_id']++;
    if ( $taxonomy === 'product_cat' ) {
        if ( ! isset( $GLOBALS['gm2_test_terms'][ $parent ] ) ) {
            $GLOBALS['gm2_test_terms'][ $parent ] = [];
        }
        $GLOBALS['gm2_test_terms'][ $parent ][ $name ] = $id;
    } else {
        if ( ! isset( $GLOBALS['gm2_attr_terms'][ $taxonomy ] ) ) {
            $GLOBALS['gm2_attr_terms'][ $taxonomy ] = [];
        }
        $GLOBALS['gm2_attr_terms'][ $taxonomy ][ $name ] = $id;
    }
    $GLOBALS['gm2_insert_calls'][] = [ 'name' => $name, 'parent' => $parent, 'id' => $id ];
    return [ 'term_id' => $id ];
}

function update_term_meta( $term_id, $key, $value ) {
    $GLOBALS['gm2_meta_updates'][] = [
        'term_id' => $term_id,
        'key'     => $key,
        'value'   => $value,
    ];
}

function get_term_by( $field, $value, $taxonomy ) {
    if ( $field === 'name' && $taxonomy === 'product_cat' ) {
        foreach ( $GLOBALS['gm2_test_terms'] as $parent => $terms ) {
            foreach ( $terms as $name => $id ) {
                if ( $name === $value ) {
                    return (object) [ 'term_id' => $id ];
                }
            }
        }
    }
    return false;
}

function wc_get_product_id_by_sku( $sku ) {
    return $GLOBALS['gm2_products'][ $sku ] ?? 0;
}

function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {
    $GLOBALS['gm2_set_terms_calls'][] = [
        'object_id' => $object_id,
        'terms'     => $terms,
        'taxonomy'  => $taxonomy,
        'append'    => $append,
    ];
    return $terms;
}

function wc_create_attribute( $args ) {
    $slug = sanitize_key( $args['slug'] ?? $args['name'] );
    $id   = count( $GLOBALS['gm2_attributes'] ) + 1;
    $GLOBALS['gm2_attributes'][ $slug ] = [
        'id'             => $id,
        'attribute_id'   => $id,
        'attribute_name' => $slug,
        'attribute_label'=> $args['name'] ?? $slug,
    ];
    return $id;
}

function wc_get_attribute_taxonomies() {
    $list = [];
    foreach ( $GLOBALS['gm2_attributes'] as $slug => $attr ) {
        $list[] = (object) $attr;
    }
    return $list;
}

function wc_attribute_taxonomy_name( $name ) {
    $name = sanitize_title( $name );
    return strpos( $name, 'pa_' ) === 0 ? $name : 'pa_' . $name;
}

function wc_sanitize_taxonomy_name( $name ) {
    return sanitize_title( $name );
}

function wc_delete_attribute( $id ) {
    foreach ( $GLOBALS['gm2_attributes'] as $slug => $attr ) {
        if ( $attr['id'] === $id ) {
            unset( $GLOBALS['gm2_attributes'][ $slug ] );
            return true;
        }
    }
    return false;
}

if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $name ) {
        unset( $GLOBALS['gm2_options'][ $name ] );
        return true;
    }
}

if ( ! function_exists( 'wp_defer_term_counting' ) ) {
    function wp_defer_term_counting( $defer = false ) { return true; }
}

if ( ! function_exists( 'wp_count_posts' ) ) {
    function wp_count_posts( $post_type ) {
        $count = isset( $GLOBALS['gm2_product_objects'] ) ? count( $GLOBALS['gm2_product_objects'] ) : 0;
        return (object) [ 'publish' => $count ];
    }
}

if ( ! isset( $GLOBALS['wpdb'] ) ) {
    class WPDBStub {
        public $posts = 'wp_posts';
        public $term_taxonomy = 'wp_term_taxonomy';
        public $term_relationships = 'wp_term_relationships';
        public function prepare( $query, ...$args ) { return $query; }
        public function get_col( $query ) { return []; }
        public function query( $query ) { return 0; }
    }
    $GLOBALS['wpdb'] = new WPDBStub();
}

// Basic stubs for renderer tests and others.
if ( ! function_exists( 'get_terms' ) ) {
    function get_terms( $args ) {
        $taxonomy = $args['taxonomy'] ?? 'product_cat';
        $parent   = isset( $args['parent'] ) ? (int) $args['parent'] : null;
        $include  = isset( $args['include'] ) ? (array) $args['include'] : null;
        $terms    = [];
        if ( $taxonomy !== 'product_cat' ) {
            foreach ( $GLOBALS['gm2_attr_terms'][ $taxonomy ] ?? [] as $name => $id ) {
                $terms[] = (object) [ 'term_id' => $id, 'parent' => 0, 'name' => $name ];
            }
            return $terms;
        }
        foreach ( $GLOBALS['gm2_test_terms'] as $p => $cats ) {
            foreach ( $cats as $name => $id ) {
                if ( $parent !== null && (int) $p !== $parent ) {
                    continue;
                }
                if ( $include && ! in_array( $id, $include, true ) ) {
                    continue;
                }
                $terms[] = (object) [
                    'term_id' => $id,
                    'parent'  => $p,
                    'name'    => $name,
                ];
            }
        }
        return $terms;
    }
}

if ( ! function_exists( 'get_term_meta' ) ) {
    function get_term_meta( $term_id, $key, $single = true ) {
        foreach ( $GLOBALS['gm2_meta_updates'] as $meta ) {
            if ( $meta['term_id'] === $term_id && $meta['key'] === $key ) {
                return $meta['value'];
            }
        }
        return '';
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) { return $text; }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) { return $url; }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) { return $text; }
}

if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( $params ) {
        return '?' . http_build_query( $params );
    }
}

if ( ! function_exists( 'trailingslashit' ) ) {
    function trailingslashit( $path ) {
        return rtrim( $path, '/\\' ) . '/';
    }
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
    function wp_upload_dir() {
        $base = sys_get_temp_dir() . '/uploads';
        if ( ! is_dir( $base ) ) {
            mkdir( $base, 0777, true );
        }
        return [ 'basedir' => $base, 'baseurl' => 'http://example.com/uploads' ];
    }
}

}

namespace Elementor {
    class Icons_Manager {
        public static function try_get_icon_html( $icon, $attrs = [], $tag = null, $echo = false ) {
            $value     = $icon['value'] ?? '';
            $attr_str  = '';
            foreach ( $attrs as $k => $v ) {
                $attr_str .= ' ' . $k . '="' . $v . '"';
            }
            if ( isset( $icon['library'] ) && $icon['library'] === 'svg' ) {
                $markup = '<svg' . $attr_str . '><path d="' . $value . '"></path></svg>';
            } else {
                $markup = '<i class="' . $value . '"' . $attr_str . '></i>';
            }
            if ( $echo ) {
                echo $markup;
                return null;
            }
            return $markup;
        }

        public static function render_icon( $icon, $attrs = [], $tag = null ) {
            echo self::try_get_icon_html( $icon, $attrs, $tag );
            return true;
        }
        public static function enqueue_shim( $icon ) {}
    }
}

