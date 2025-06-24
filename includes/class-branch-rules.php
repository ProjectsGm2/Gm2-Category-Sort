<?php
class Gm2_Category_Sort_Branch_Rules {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_admin_page' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
        add_action( 'wp_ajax_gm2_branch_rules_get', [ __CLASS__, 'ajax_get_rules' ] );
        add_action( 'wp_ajax_gm2_branch_rules_save', [ __CLASS__, 'ajax_save_rules' ] );
        add_action( 'admin_post_gm2_export_branch_rules', [ __CLASS__, 'handle_export' ] );
        add_action( 'admin_post_gm2_import_branch_rules', [ __CLASS__, 'handle_import' ] );
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

    /**
     * Parse category-tree.csv and build a mapping of branch slugs to their full path.
     *
     * Mirrors the slug creation logic from One_Click_Assign::export_branch_csvs().
     * Each slug includes every level so even leaf categories are returned.
     *
     * @param string $file Path to category-tree.csv.
     * @return array Mapping of slug => "Root > Child > ..." path.
     */
    public static function build_slug_path_map( $file ) {
        $rows = array_map( 'str_getcsv', file( $file ) );
        $map  = [];

        foreach ( $rows as $row ) {
            if ( empty( $row ) ) {
                continue;
            }

            $path_slugs = [];
            $path_names = [];
            foreach ( $row as $segment ) {
                $segment = trim( $segment );
                if ( $segment === '' ) {
                    continue;
                }

                $clean = preg_replace( '/\s*\([^\)]*\)$/', '', $segment );

                $path_slugs[] = Gm2_Category_Sort_Product_Category_Generator::slugify_segment( $clean );
                $path_names[] = preg_replace( '/\s*\([^\)]*\)/', '', $segment );
                $slug = implode( '-', $path_slugs );

                if ( ! isset( $map[ $slug ] ) ) {
                    $map[ $slug ] = implode( ' > ', $path_names );
                }
            }
        }

        return $map;
    }

    public static function admin_page() {
        $dir  = self::get_branch_dir();
        $tree = rtrim( $dir, '/' ) . '/category-tree.csv';
        if ( ! file_exists( $tree ) ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Branch Rules', 'gm2-category-sort' ) . '</h1>';
            echo '<p>' . esc_html__( 'category-tree.csv not found. Run One Click Categories Assignment first.', 'gm2-category-sort' ) . '</p></div>';
            return;
        }

        $branches = self::build_slug_path_map( $tree );
        $rules    = get_option( 'gm2_branch_rules', [] );
        if ( ! is_array( $rules ) ) {
            $rules = [];
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Branch Rules', 'gm2-category-sort' ) . '</h1>';

        $success = isset( $_GET['gm2_import_success'] );
        $error   = isset( $_GET['gm2_import_error'] ) ? sanitize_text_field( wp_unslash( $_GET['gm2_import_error'] ) ) : '';
        if ( $success ) {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Rules imported.', 'gm2-category-sort' ) . '</p></div>';
        } elseif ( $error ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;margin-right:10px;">';
        wp_nonce_field( 'gm2_export_branch_rules', 'gm2_export_branch_rules_nonce' );
        echo '<input type="hidden" name="action" value="gm2_export_branch_rules">';
        submit_button( __( 'Download CSV', 'gm2-category-sort' ), 'secondary', 'submit', false );
        echo '</form>';

        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;margin-right:10px;">';
        wp_nonce_field( 'gm2_import_branch_rules', 'gm2_import_branch_rules_nonce' );
        echo '<input type="hidden" name="action" value="gm2_import_branch_rules">';
        echo '<input type="file" name="gm2_branch_rules_file" accept=".csv" style="margin-right:6px;">';
        submit_button( __( 'Upload CSV', 'gm2-category-sort' ), 'secondary', 'submit', false );
        echo '</form>';

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
        echo '<thead><tr><th>' . esc_html__( 'Branch', 'gm2-category-sort' ) . '</th><th>' . esc_html__( 'Include Keywords', 'gm2-category-sort' ) . '</th><th>' . esc_html__( 'Exclude Keywords', 'gm2-category-sort' ) . '</th><th>' . esc_html__( 'Include Attributes', 'gm2-category-sort' ) . '</th><th>' . esc_html__( 'Exclude Attributes', 'gm2-category-sort' ) . '</th><th>' . esc_html__( 'Allow Multiple Leaves', 'gm2-category-sort' ) . '</th></tr></thead>';
        echo '<tbody>';
        foreach ( $branches as $slug => $path ) {
            $inc   = $rules[ $slug ][ 'include' ] ?? '';
            $exc   = $rules[ $slug ][ 'exclude' ] ?? '';
            $multi = ! empty( $rules[ $slug ]['allow_multi'] );
            echo '<tr data-slug="' . esc_attr( $slug ) . '">';
            echo '<td><strong>' . esc_html( $path ) . '</strong></td>';
            echo '<td><textarea data-slug="' . esc_attr( $slug ) . '" data-type="include" rows="2" style="width:100%;">' . esc_textarea( $inc ) . '</textarea></td>';
            echo '<td><textarea data-slug="' . esc_attr( $slug ) . '" data-type="exclude" rows="2" style="width:100%;">' . esc_textarea( $exc ) . '</textarea></td>';
            echo '<td><select multiple class="gm2-attr-select gm2-include-attr" data-type="include_attrs" data-slug="' . esc_attr( $slug ) . '" style="width:100%;">' . $options . '</select><div class="gm2-include-terms"></div><div class="gm2-include-tags"></div></td>';
            echo '<td><select multiple class="gm2-attr-select gm2-exclude-attr" data-type="exclude_attrs" data-slug="' . esc_attr( $slug ) . '" style="width:100%;">' . $options . '</select><div class="gm2-exclude-terms"></div><div class="gm2-exclude-tags"></div></td>';
            echo '<td class="gm2-allow-multi"><input type="checkbox" data-type="allow_multi" data-slug="' . esc_attr( $slug ) . '"' . ( $multi ? ' checked' : '' ) . '></td>';
            echo '</tr>';
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
        foreach ( $rules as $slug => $rule ) {
            $rules[ $slug ]['allow_multi'] = isset( $rule['allow_multi'] ) ? (bool) $rule['allow_multi'] : false;
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
            $allow_multi = isset( $rule['allow_multi'] ) ? filter_var( $rule['allow_multi'], FILTER_VALIDATE_BOOLEAN ) : false;

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
                'allow_multi'   => $allow_multi,
            ];
        }
        update_option( 'gm2_branch_rules', $rules );
        wp_send_json_success();
    }

    /**
     * Convert attribute array to export string.
     *
     * @param array $attrs Attribute array.
     * @return string
     */
    protected static function attrs_to_string( $attrs ) {
        $parts = [];
        foreach ( (array) $attrs as $tax => $terms ) {
            $tax   = sanitize_key( $tax );
            $terms = array_filter( array_map( 'sanitize_key', (array) $terms ) );
            if ( empty( $terms ) ) {
                continue;
            }
            $parts[] = $tax . ':' . implode( '|', $terms );
        }
        return implode( ';', $parts );
    }

    /**
     * Parse attribute string back into array.
     *
     * @param string $str Attribute string.
     * @return array
     */
    protected static function string_to_attrs( $str ) {
        $result = [];
        foreach ( explode( ';', (string) $str ) as $pair ) {
            $pair = trim( $pair );
            if ( $pair === '' ) {
                continue;
            }
            list( $tax, $terms ) = array_pad( explode( ':', $pair, 2 ), 2, '' );
            $tax   = sanitize_key( $tax );
            $terms = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( '|', $terms ) ) ) );
            if ( $tax !== '' && ! empty( $terms ) ) {
                $result[ $tax ] = $terms;
            }
        }
        return $result;
    }

    /**
     * Export branch rules to a CSV file.
     *
     * @param string $file Destination file path.
     * @return true|WP_Error
     */
    public static function export_to_csv( $file ) {
        $fh = fopen( $file, 'w' );
        if ( ! $fh ) {
            return new WP_Error( 'gm2_write_failed', __( 'Unable to write file.', 'gm2-category-sort' ) );
        }

        $upload = wp_upload_dir();
        $tree   = trailingslashit( $upload['basedir'] ) . 'gm2-category-sort/categories-structure/category-tree.csv';
        $map    = file_exists( $tree ) ? self::build_slug_path_map( $tree ) : [];

        fputcsv( $fh, [ 'slug', 'path', 'include', 'exclude', 'include_attrs', 'exclude_attrs', 'allow_multi' ] );

        $rules = get_option( 'gm2_branch_rules', [] );
        if ( is_array( $rules ) ) {
            foreach ( $rules as $slug => $rule ) {
                $row = [
                    $slug,
                    $map[ $slug ] ?? '',
                    $rule['include'] ?? '',
                    $rule['exclude'] ?? '',
                    self::attrs_to_string( $rule['include_attrs'] ?? [] ),
                    self::attrs_to_string( $rule['exclude_attrs'] ?? [] ),
                    empty( $rule['allow_multi'] ) ? '0' : '1',
                ];
                fputcsv( $fh, $row );
            }
        }

        fclose( $fh );
        return true;
    }

    /**
     * Import branch rules from a CSV file.
     *
     * @param string $file CSV file path.
     * @return array|WP_Error Array of rules or error.
     */
    public static function import_from_csv( $file ) {
        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            return new WP_Error( 'gm2_invalid_file', __( 'Invalid CSV file.', 'gm2-category-sort' ) );
        }

        $fh = fopen( $file, 'r' );
        if ( ! $fh ) {
            return new WP_Error( 'gm2_unreadable', __( 'Unable to read file.', 'gm2-category-sort' ) );
        }

        $header = fgetcsv( $fh );
        if ( ! $header ) {
            fclose( $fh );
            return [];
        }
        $header[0] = preg_replace( "/^\xEF\xBB\xBF/", '', $header[0] );
        $cols      = array_flip( $header );
        $rules     = [];
        while ( ( $row = fgetcsv( $fh ) ) !== false ) {
            if ( empty( $row ) ) {
                continue;
            }
            $slug = sanitize_key( $row[ $cols['slug'] ] ?? '' );
            if ( $slug === '' ) {
                continue;
            }
            $rules[ $slug ] = [
                'include'       => $row[ $cols['include'] ] ?? '',
                'exclude'       => $row[ $cols['exclude'] ] ?? '',
                'include_attrs' => self::string_to_attrs( $row[ $cols['include_attrs'] ] ?? '' ),
                'exclude_attrs' => self::string_to_attrs( $row[ $cols['exclude_attrs'] ] ?? '' ),
                'allow_multi'   => ! empty( $row[ $cols['allow_multi'] ] ),
            ];
        }

        fclose( $fh );
        return $rules;
    }

    /**
     * Handle CSV export request.
     */
    public static function handle_export() {
        check_admin_referer( 'gm2_export_branch_rules', 'gm2_export_branch_rules_nonce' );

        $file = wp_tempnam( 'branch-rules.csv' );
        if ( ! $file ) {
            wp_die( esc_html__( 'Unable to create temporary file.', 'gm2-category-sort' ) );
        }

        $result = self::export_to_csv( $file );
        if ( is_wp_error( $result ) ) {
            wp_die( esc_html( $result->get_error_message() ) );
        }

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="branch-rules.csv"' );
        readfile( $file );
        unlink( $file );
        exit;
    }

    /**
     * Handle CSV import request.
     */
    public static function handle_import() {
        check_admin_referer( 'gm2_import_branch_rules', 'gm2_import_branch_rules_nonce' );

        if ( empty( $_FILES['gm2_branch_rules_file']['tmp_name'] ) ) {
            $redirect = add_query_arg( 'gm2_import_error', rawurlencode( __( 'No file uploaded.', 'gm2-category-sort' ) ), admin_url( 'tools.php?page=gm2-branch-rules' ) );
            wp_safe_redirect( $redirect );
            exit;
        }

        $file   = $_FILES['gm2_branch_rules_file']['tmp_name'];
        $result = self::import_from_csv( $file );
        if ( is_wp_error( $result ) ) {
            $redirect = add_query_arg( 'gm2_import_error', rawurlencode( $result->get_error_message() ), admin_url( 'tools.php?page=gm2-branch-rules' ) );
            wp_safe_redirect( $redirect );
            exit;
        }

        update_option( 'gm2_branch_rules', $result );
        $redirect = add_query_arg( 'gm2_import_success', '1', admin_url( 'tools.php?page=gm2-branch-rules' ) );
        wp_safe_redirect( $redirect );
        exit;
    }
}
