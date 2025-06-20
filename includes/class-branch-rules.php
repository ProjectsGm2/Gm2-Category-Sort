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

        $css_ver = file_exists( GM2_CAT_SORT_PATH . 'assets/css/branch-rules.css' ) ? filemtime( GM2_CAT_SORT_PATH . 'assets/css/branch-rules.css' ) : GM2_CAT_SORT_VERSION;
        wp_enqueue_style(
            'gm2-branch-rules',
            GM2_CAT_SORT_URL . 'assets/css/branch-rules.css',
            [],
            $css_ver
        );

        $attrs = wc_get_attribute_taxonomies();
        $attr_data = [];
        if ( $attrs ) {
            foreach ( $attrs as $attr ) {
                $taxonomy = wc_attribute_taxonomy_name( $attr->attribute_name );
                $terms    = get_terms( [
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => false,
                ] );
                $term_map = [];
                foreach ( $terms as $term ) {
                    $term_map[ $term->slug ] = $term->name;
                }
                $attr_data[ $taxonomy ] = [
                    'label' => $attr->attribute_label,
                    'terms' => $term_map,
                ];
            }
        }

        wp_localize_script(
            'gm2-branch-rules',
            'gm2BranchRules',
            [
                'nonce'      => wp_create_nonce( 'gm2_branch_rules' ),
                'saved'      => __( 'Rules saved.', 'gm2-category-sort' ),
                'error'      => __( 'Error saving rules.', 'gm2-category-sort' ),
                'attributes' => $attr_data,
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
        $attrs = wc_get_attribute_taxonomies();
        $options = '';
        if ( $attrs ) {
            foreach ( $attrs as $attr ) {
                $taxonomy = wc_attribute_taxonomy_name( $attr->attribute_name );
                $options .= '<option value="' . esc_attr( $taxonomy ) . '">' . esc_html( $attr->attribute_label ) . '</option>';
            }
        }

        echo '<table class="widefat">';
        echo '<thead><tr><th>' . esc_html__( 'Branch', 'gm2-category-sort' ) . '</th><th>' . esc_html__( 'Include Keywords', 'gm2-category-sort' ) . '</th><th>' . esc_html__( 'Exclude Keywords', 'gm2-category-sort' ) . '</th><th>' . esc_html__( 'Include Attributes', 'gm2-category-sort' ) . '</th><th>' . esc_html__( 'Exclude Attributes', 'gm2-category-sort' ) . '</th></tr></thead>';
        echo '<tbody>';
        foreach ( $branches as $parent => $children ) {
            foreach ( $children as $child => $slug ) {
                $inc = $rules[ $slug ][ 'include' ] ?? '';
                $exc = $rules[ $slug ][ 'exclude' ] ?? '';
                $clean_parent = preg_replace( '/\s*\([^\)]*\)/', '', $parent );
                $clean_child  = preg_replace( '/\s*\([^\)]*\)/', '', $child );
                echo '<tr data-slug="' . esc_attr( $slug ) . '">';
                echo '<td><strong>' . esc_html( $clean_parent . ' > ' . $clean_child ) . '</strong></td>';
                echo '<td><textarea data-slug="' . esc_attr( $slug ) . '" data-type="include" rows="2" style="width:100%;">' . esc_textarea( $inc ) . '</textarea></td>';
                echo '<td><textarea data-slug="' . esc_attr( $slug ) . '" data-type="exclude" rows="2" style="width:100%;">' . esc_textarea( $exc ) . '</textarea></td>';
                echo '<td><select multiple class="gm2-attr-select gm2-include-attr" data-type="include_attrs" data-slug="' . esc_attr( $slug ) . '" style="width:100%;">' . $options . '</select><div class="gm2-include-terms"></div><div class="gm2-include-tags"></div><div class="gm2-include-summary"></div></td>';
                echo '<td><select multiple class="gm2-attr-select gm2-exclude-attr" data-type="exclude_attrs" data-slug="' . esc_attr( $slug ) . '" style="width:100%;">' . $options . '</select><div class="gm2-exclude-terms"></div><div class="gm2-exclude-tags"></div><div class="gm2-exclude-summary"></div></td>';
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
        $data  = isset( $_POST['rules'] ) && is_array( $_POST['rules'] ) ? wp_unslash( $_POST['rules'] ) : [];
        $rules = [];
        foreach ( $data as $slug => $rule ) {
            $slug    = sanitize_key( $slug );
            $include = sanitize_textarea_field( $rule['include'] ?? '' );
            $exclude = sanitize_textarea_field( $rule['exclude'] ?? '' );

            $include_attrs = [];
            if ( isset( $rule['include_attrs'] ) && is_array( $rule['include_attrs'] ) ) {
                foreach ( $rule['include_attrs'] as $attr => $terms ) {
                    $attr               = sanitize_key( $attr );
                    $include_attrs[$attr] = array_map( 'sanitize_key', (array) $terms );
                }
            }

            $exclude_attrs = [];
            if ( isset( $rule['exclude_attrs'] ) && is_array( $rule['exclude_attrs'] ) ) {
                foreach ( $rule['exclude_attrs'] as $attr => $terms ) {
                    $attr               = sanitize_key( $attr );
                    $exclude_attrs[$attr] = array_map( 'sanitize_key', (array) $terms );
                }
            }

            $rules[ $slug ] = [
                'include'       => $include,
                'exclude'       => $exclude,
                'include_attrs' => $include_attrs,
                'exclude_attrs' => $exclude_attrs,
            ];
        }
        update_option( 'gm2_branch_rules', $rules );
        wp_send_json_success();
    }
}
