<?php
class Gm2_Category_Sort_Rewrite_Rules {
    public static function init() {
        add_action( 'init', [ __CLASS__, 'add_rules' ] );
        add_action( 'init', [ __CLASS__, 'maybe_track_base_change' ] );
        add_filter( 'query_vars', [ __CLASS__, 'add_query_var' ] );
        add_action( 'admin_menu', [ __CLASS__, 'register_page' ] );
        add_action( 'template_redirect', [ __CLASS__, 'maybe_redirect' ] );
        add_action( 'update_option_woocommerce_permalinks', [ __CLASS__, 'permalinks_updated' ], 10, 2 );
    }

    public static function add_rules() {
        $bases = get_option( 'gm2_rewrite_bases', [] );
        if ( ! is_array( $bases ) ) {
            $bases = [];
        }
        foreach ( $bases as $base ) {
            $base = trim( $base, '/' );
            if ( $base === '' ) {
                continue;
            }
            add_rewrite_rule(
                '^' . preg_quote( $base, '#' ) . '/(.+?)/?$',
                'index.php?product_cat=$matches[1]&gm2_alt_base=' . $base,
                'top'
            );
        }
    }

    public static function add_query_var( $vars ) {
        $vars[] = 'gm2_alt_base';
        return $vars;
    }

    public static function register_page() {
        add_submenu_page(
            GM2_CAT_SORT_MENU_SLUG,
            __( 'Rewrite Rules', 'gm2-category-sort' ),
            __( 'Rewrite Rules', 'gm2-category-sort' ),
            'manage_options',
            'gm2-rewrite-rules',
            [ __CLASS__, 'admin_page' ]
        );
    }

    public static function admin_page() {
        $bases = get_option( 'gm2_rewrite_bases', [] );
        if ( ! is_array( $bases ) ) {
            $bases = [];
        }
        $message = isset( $_GET['gm2_saved'] );
        $text    = implode( "\n", array_map( 'esc_textarea', $bases ) );
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Rewrite Rules', 'gm2-category-sort' ) . '</h1>';
        if ( $message ) {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Rules saved.', 'gm2-category-sort' ) . '</p></div>';
        }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'gm2_save_rewrites', 'gm2_rewrites_nonce' );
        echo '<input type="hidden" name="action" value="gm2_save_rewrites" />';
        echo '<p>' . esc_html__( 'Enter alternate category bases, one per line.', 'gm2-category-sort' ) . '</p>';
        echo '<textarea name="gm2_rewrite_bases" rows="5" class="large-text code">' . $text . '</textarea>';
        submit_button( __( 'Save Rules', 'gm2-category-sort' ) );
        echo '</form></div>';
    }

    public static function save_rules() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_safe_redirect( add_query_arg( 'gm2_error', 1, menu_page_url( 'gm2-rewrite-rules', false ) ) );
            exit;
        }

        check_admin_referer( 'gm2_save_rewrites', 'gm2_rewrites_nonce' );
        if ( isset( $_POST['gm2_rewrite_bases'] ) ) {
            $text  = wp_unslash( $_POST['gm2_rewrite_bases'] );
            $bases = preg_split( '/\r?\n/', $text );
        } else {
            $bases = [];
        }
        $clean = [];
        foreach ( $bases as $base ) {
            $base = trim( sanitize_text_field( $base ) );
            $base = trim( $base, '/' );
            if ( $base !== '' ) {
                $clean[] = $base;
            }
        }
        update_option( 'gm2_rewrite_bases', $clean );
        flush_rewrite_rules();
        wp_safe_redirect( add_query_arg( 'gm2_saved', 1, menu_page_url( 'gm2-rewrite-rules', false ) ) );
        exit;
    }

    public static function maybe_redirect() {
        $base = get_query_var( 'gm2_alt_base' );
        if ( ! $base ) {
            return;
        }
        $slug = get_query_var( 'product_cat' );
        if ( strpos( $slug, '/' ) !== false ) {
            $parts = explode( '/', trim( $slug, '/' ) );
            $slug  = end( $parts );
        }
        $term = get_term_by( 'slug', $slug, 'product_cat' );
        if ( ! $term || is_wp_error( $term ) ) {
            return;
        }
        $link = get_term_link( $term );
        if ( ! is_wp_error( $link ) ) {
            wp_redirect( $link, 301 );
            exit;
        }
    }

    /**
     * Detect changes to the WooCommerce category base and store previous values.
     */
    public static function maybe_track_base_change() {
        $permalinks = get_option( 'woocommerce_permalinks' );
        $current    = is_array( $permalinks ) && isset( $permalinks['category_base'] )
            ? trim( $permalinks['category_base'], '/' )
            : '';
        self::handle_base_change( $current );

        if ( is_array( $permalinks ) && ! empty( $permalinks['product_base'] ) && strpos( $permalinks['product_base'], '%product_cat%' ) !== false ) {
            $parts   = explode( '/', trim( $permalinks['product_base'], '/' ) );
            $segment = $parts[0] ?? '';
            self::handle_product_base_change( $segment );
        }
    }

    /**
     * Handle update_option_woocommerce_permalinks action.
     *
     * @param mixed $old_value Previous value.
     * @param mixed $value     New value.
     */
    public static function permalinks_updated( $old_value, $value ) {
        $current = is_array( $value ) && isset( $value['category_base'] )
            ? trim( $value['category_base'], '/' )
            : '';
        self::handle_base_change( $current );

        if ( is_array( $value ) && ! empty( $value['product_base'] ) && strpos( $value['product_base'], '%product_cat%' ) !== false ) {
            $parts   = explode( '/', trim( $value['product_base'], '/' ) );
            $segment = $parts[0] ?? '';
            self::handle_product_base_change( $segment );
        }
    }

    /**
     * Compare the current base with the stored one and update options.
     *
     * @param string $current Current category base.
     */
    protected static function handle_base_change( $current ) {
        $previous = get_option( 'gm2_rewrite_prev_base', '' );
        if ( $previous === '' ) {
            update_option( 'gm2_rewrite_prev_base', $current );
            return;
        }

        if ( $current === '' || $current === $previous ) {
            return;
        }

        $bases = get_option( 'gm2_rewrite_bases', [] );
        if ( ! is_array( $bases ) ) {
            $bases = [];
        }
        $updated = false;
        if ( ! in_array( $previous, $bases, true ) ) {
            $bases[] = $previous;
            update_option( 'gm2_rewrite_bases', $bases );
            $updated = true;
        }
        update_option( 'gm2_rewrite_prev_base', $current );
        if ( $updated ) {
            flush_rewrite_rules();
        }
    }

    /**
     * Track product permalink base segments when %product_cat% is used.
     *
     * @param string $segment Leading segment before %product_cat%.
     */
    protected static function handle_product_base_change( $segment ) {
        $previous = get_option( 'gm2_rewrite_prev_product_segment', '' );
        if ( $previous === '' ) {
            update_option( 'gm2_rewrite_prev_product_segment', $segment );
            return;
        }

        if ( $segment === '' || $segment === $previous ) {
            return;
        }

        $bases = get_option( 'gm2_rewrite_bases', [] );
        if ( ! is_array( $bases ) ) {
            $bases = [];
        }
        $updated = false;
        if ( ! in_array( $previous, $bases, true ) ) {
            $bases[] = $previous;
            update_option( 'gm2_rewrite_bases', $bases );
            $updated = true;
        }
        update_option( 'gm2_rewrite_prev_product_segment', $segment );
        if ( $updated ) {
            flush_rewrite_rules();
        }
    }
}
