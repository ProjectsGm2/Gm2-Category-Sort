<?php
class Gm2_Category_Sort_Branch_Rules {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_admin_page' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
        add_action( 'wp_ajax_gm2_branch_rules_get', [ __CLASS__, 'ajax_get_rules' ] );
        add_action( 'wp_ajax_gm2_branch_rules_save', [ __CLASS__, 'ajax_save_rules' ] );
    }

    public static function register_admin_page() {
        add_management_page(
            __( 'Branch Rules', 'gm2-category-sort' ),
            __( 'Branch Rules', 'gm2-category-sort' ),
            'manage_options',
            'gm2-branch-rules',
            [ __CLASS__, 'admin_page' ]
        );
    }

    public static function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'tools_page_gm2-branch-rules' ) {
            return;
        }

        $ver = file_exists( GM2_CAT_SORT_PATH . 'assets/js/branch-rules.js' ) ? filemtime( GM2_CAT_SORT_PATH . 'assets/js/branch-rules.js' ) : GM2_CAT_SORT_VERSION;
        wp_enqueue_script(
            'gm2-branch-rules',
            GM2_CAT_SORT_URL . 'assets/js/branch-rules.js',
            [ 'jquery' ],
            $ver,
            true
        );

        wp_localize_script(
            'gm2-branch-rules',
            'gm2BranchRules',
            [
                'nonce' => wp_create_nonce( 'gm2_branch_rules' ),
                'saved' => __( 'Rules saved.', 'gm2-category-sort' ),
                'error' => __( 'Error saving rules.', 'gm2-category-sort' ),
            ]
        );
    }

    protected static function get_branch_dir() {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['basedir'] ) . 'gm2-category-sort/categories-structure';
    }

    public static function admin_page() {
        $dir  = self::get_branch_dir();
        $tree = rtrim( $dir, '/' ) . '/category-tree.csv';
        if ( ! file_exists( $tree ) ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Branch Rules', 'gm2-category-sort' ) . '</h1>';
            echo '<p>' . esc_html__( 'category-tree.csv not found. Run One Click Categories Assignment first.', 'gm2-category-sort' ) . '</p></div>';
            return;
        }

        $branches = Gm2_Category_Sort_One_Click_Assign::build_branch_map( $tree );
        $rules    = get_option( 'gm2_branch_rules', [] );
        if ( ! is_array( $rules ) ) {
            $rules = [];
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Branch Rules', 'gm2-category-sort' ) . '</h1>';
        echo '<form id="gm2-branch-rules-form">';
        wp_nonce_field( 'gm2_branch_rules', 'gm2_branch_rules_nonce' );
        echo '<table class="widefat">';
        echo '<thead><tr><th>' . esc_html__( 'Branch', 'gm2-category-sort' ) . '</th><th>' . esc_html__( 'Include Keywords', 'gm2-category-sort' ) . '</th><th>' . esc_html__( 'Exclude Keywords', 'gm2-category-sort' ) . '</th></tr></thead>';
        echo '<tbody>';
        foreach ( $branches as $parent => $children ) {
            foreach ( $children as $child ) {
                $slug = sanitize_title( $parent ) . '-' . sanitize_title( $child );
                $inc  = $rules[ $slug ][ 'include' ] ?? '';
                $exc  = $rules[ $slug ][ 'exclude' ] ?? '';
                echo '<tr data-slug="' . esc_attr( $slug ) . '">';
                echo '<td><strong>' . esc_html( $parent . ' > ' . $child ) . '</strong></td>';
                echo '<td><textarea data-slug="' . esc_attr( $slug ) . '" data-type="include" rows="2" style="width:100%;">' . esc_textarea( $inc ) . '</textarea></td>';
                echo '<td><textarea data-slug="' . esc_attr( $slug ) . '" data-type="exclude" rows="2" style="width:100%;">' . esc_textarea( $exc ) . '</textarea></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '<p><button class="button button-primary">' . esc_html__( 'Save Rules', 'gm2-category-sort' ) . '</button> <span id="gm2-branch-rules-msg"></span></p>';
        echo '</form></div>';
    }

    public static function ajax_get_rules() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized' );
        }
        check_ajax_referer( 'gm2_branch_rules', 'nonce' );
        $rules = get_option( 'gm2_branch_rules', [] );
        if ( ! is_array( $rules ) ) {
            $rules = [];
        }
        wp_send_json_success( $rules );
    }

    public static function ajax_save_rules() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized' );
        }
        check_ajax_referer( 'gm2_branch_rules', 'nonce' );
        $data = isset( $_POST['rules'] ) && is_array( $_POST['rules'] ) ? $_POST['rules'] : [];
        $rules = [];
        foreach ( $data as $slug => $rule ) {
            $slug          = sanitize_key( $slug );
            $rules[ $slug ] = [
                'include' => sanitize_text_field( $rule['include'] ?? '' ),
                'exclude' => sanitize_text_field( $rule['exclude'] ?? '' ),
            ];
        }
        update_option( 'gm2_branch_rules', $rules );
        wp_send_json_success();
    }
}
