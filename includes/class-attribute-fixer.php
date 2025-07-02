<?php
/**
 * Admin tools for merging WooCommerce attributes and terms.
 */
class Gm2_Category_Sort_Attribute_Fixer {

    /**
     * Initialize hooks.
     */
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_admin_page' ] );
    }

    /**
     * Register the Tools page.
     */
    public static function register_admin_page() {
        add_submenu_page(
            GM2_CAT_SORT_MENU_SLUG,
            __( 'Attributes Fixer', 'gm2-category-sort' ),
            __( 'Attributes Fixer', 'gm2-category-sort' ),
            'manage_options',
            'gm2-attribute-fixer',
            [ __CLASS__, 'admin_page' ]
        );
    }

    /**
     * Render the admin page and handle form submission.
     */
    public static function admin_page() {
        $message = '';
        $error   = '';

        if ( isset( $_POST['gm2_merge_attributes_nonce'] ) ) {
            check_admin_referer( 'gm2_merge_attributes', 'gm2_merge_attributes_nonce' );
            $result = self::handle_merge_attributes();
            if ( is_wp_error( $result ) ) {
                $error = $result->get_error_message();
            } else {
                $message = $result;
            }
        } elseif ( isset( $_POST['gm2_merge_terms_nonce'] ) ) {
            check_admin_referer( 'gm2_merge_terms', 'gm2_merge_terms_nonce' );
            $result = self::handle_merge_terms();
            if ( is_wp_error( $result ) ) {
                $error = $result->get_error_message();
            } else {
                $message = $result;
            }
        }

        self::render_page( $message, $error );
    }

    /**
     * Handle attribute merging.
     *
     * @return string|WP_Error
     */
    protected static function handle_merge_attributes() {
        $attributes = isset( $_POST['attributes'] ) ? (array) $_POST['attributes'] : [];
        $attributes = array_map( 'sanitize_text_field', $attributes );
        $target     = sanitize_text_field( $_POST['target_attribute'] ?? '' );
        $new_name   = sanitize_text_field( $_POST['new_attribute'] ?? '' );

        if ( count( $attributes ) < 2 ) {
            return new WP_Error( 'gm2_invalid', __( 'Select at least two attributes.', 'gm2-category-sort' ) );
        }

        if ( $target === '' && $new_name === '' ) {
            return new WP_Error( 'gm2_invalid', __( 'Please select a target attribute or enter a new name.', 'gm2-category-sort' ) );
        }

        if ( $target === '' ) {
            if ( ! function_exists( 'wc_create_attribute' ) ) {
                return new WP_Error( 'gm2_missing', __( 'WooCommerce functions not available.', 'gm2-category-sort' ) );
            }
            $slug     = wc_sanitize_taxonomy_name( $new_name );
            $attr_id  = wc_create_attribute( [
                'slug' => $slug,
                'name' => $new_name,
                'type' => 'select',
            ] );
            if ( is_wp_error( $attr_id ) ) {
                return $attr_id;
            }
            $target = $slug;
        }

        foreach ( $attributes as $slug ) {
            if ( $slug === $target ) {
                continue;
            }
            self::transfer_terms_and_assignments( $slug, $target );
            if ( function_exists( 'wc_delete_attribute' ) ) {
                $attr = self::get_attribute_by_slug( $slug );
                if ( $attr ) {
                    $id = isset( $attr->attribute_id ) ? $attr->attribute_id : $attr->id;
                    wc_delete_attribute( $id );
                    delete_transient( 'wc_attribute_taxonomies' );
                }
            }
        }

        return __( 'Attributes merged successfully.', 'gm2-category-sort' );
    }

    /**
     * Handle merging of terms within a single attribute.
     *
     * @return string|WP_Error
     */
    protected static function handle_merge_terms() {
        $attribute = sanitize_text_field( $_POST['attribute'] ?? '' );
        $terms     = isset( $_POST['terms'] ) ? array_map( 'absint', (array) $_POST['terms'] ) : [];
        $target    = isset( $_POST['target_term'] ) ? absint( $_POST['target_term'] ) : 0;
        $new_name  = sanitize_text_field( $_POST['new_term'] ?? '' );

        if ( ! $attribute || count( $terms ) < 2 ) {
            return new WP_Error( 'gm2_invalid', __( 'Select an attribute and at least two terms.', 'gm2-category-sort' ) );
        }

        $taxonomy = wc_attribute_taxonomy_name( $attribute );

        if ( $target ) {
            $term = get_term( $target, $taxonomy );
            if ( ! $term || is_wp_error( $term ) ) {
                return new WP_Error( 'gm2_invalid', __( 'Invalid target term.', 'gm2-category-sort' ) );
            }
            $target_id = $term->term_id;
        } elseif ( $new_name !== '' ) {
            $existing = get_term_by( 'name', $new_name, $taxonomy );
            if ( $existing ) {
                $target_id = $existing->term_id;
            } else {
                $insert = wp_insert_term( $new_name, $taxonomy );
                if ( is_wp_error( $insert ) ) {
                    return $insert;
                }
                $target_id = $insert['term_id'];
            }
        } else {
            return new WP_Error( 'gm2_invalid', __( 'Please select a target term or enter a new name.', 'gm2-category-sort' ) );
        }

        foreach ( $terms as $term_id ) {
            if ( $term_id === $target_id ) {
                continue;
            }
            $objects = get_objects_in_term( $term_id, $taxonomy );
            if ( ! is_wp_error( $objects ) ) {
                foreach ( $objects as $product_id ) {
                    wp_set_object_terms( $product_id, $target_id, $taxonomy, true );
                    wp_remove_object_terms( $product_id, $term_id, $taxonomy );
                }
            }
            wp_delete_term( $term_id, $taxonomy );
        }

        return __( 'Terms merged successfully.', 'gm2-category-sort' );
    }

    /**
     * Transfer terms and product assignments between attributes.
     *
     * @param string $from Attribute slug to merge from.
     * @param string $to   Target attribute slug.
     */
    protected static function transfer_terms_and_assignments( $from, $to ) {
        $from_tax = wc_attribute_taxonomy_name( $from );
        $to_tax   = wc_attribute_taxonomy_name( $to );

        $terms = get_terms( [
            'taxonomy'   => $from_tax,
            'hide_empty' => false,
        ] );

        if ( is_wp_error( $terms ) ) {
            return;
        }

        foreach ( $terms as $term ) {
            $target = get_term_by( 'slug', $term->slug, $to_tax );
            if ( $target ) {
                $target_id = $target->term_id;
            } else {
                $insert = wp_insert_term( $term->name, $to_tax, [ 'slug' => $term->slug ] );
                if ( is_wp_error( $insert ) ) {
                    continue;
                }
                $target_id = $insert['term_id'];
            }

            $objects = get_objects_in_term( $term->term_id, $from_tax );
            if ( ! is_wp_error( $objects ) ) {
                foreach ( $objects as $object_id ) {
                    wp_set_object_terms( $object_id, $target_id, $to_tax, true );
                    wp_remove_object_terms( $object_id, $term->term_id, $from_tax );
                }
            }

            wp_delete_term( $term->term_id, $from_tax );
        }
    }

    /**
     * Get attribute taxonomy object by slug.
     *
     * @param string $slug Attribute slug.
     * @return object|null
     */
    protected static function get_attribute_by_slug( $slug ) {
        $taxes = wc_get_attribute_taxonomies();
        if ( empty( $taxes ) ) {
            return null;
        }
        foreach ( $taxes as $tax ) {
            if ( $tax->attribute_name === $slug ) {
                return $tax;
            }
        }
        return null;
    }

    /**
     * Render the HTML for the admin page.
     *
     * @param string $message Success message.
     * @param string $error   Error message.
     */
    protected static function render_page( $message, $error ) {
        $attributes = wc_get_attribute_taxonomies();
        $current    = sanitize_text_field( $_GET['attr'] ?? '' );
        if ( ! $current && $attributes ) {
            $current = $attributes[0]->attribute_name;
        }
        $taxonomy = $current ? wc_attribute_taxonomy_name( $current ) : '';
        $terms    = $taxonomy ? get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] ) : [];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Attributes Fixer', 'gm2-category-sort' ); ?></h1>
            <?php if ( $message ) : ?>
                <div class="notice notice-success"><p><?php echo esc_html( $message ); ?></p></div>
            <?php elseif ( $error ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Merge Attributes', 'gm2-category-sort' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'gm2_merge_attributes', 'gm2_merge_attributes_nonce' ); ?>
                <p><?php esc_html_e( 'Select attributes to merge:', 'gm2-category-sort' ); ?></p>
                <?php foreach ( (array) $attributes as $attr ) : ?>
                    <label><input type="checkbox" name="attributes[]" value="<?php echo esc_attr( $attr->attribute_name ); ?>"> <?php echo esc_html( $attr->attribute_label ); ?></label><br>
                <?php endforeach; ?>
                <p>
                    <?php esc_html_e( 'Merge into existing attribute:', 'gm2-category-sort' ); ?>
                    <select name="target_attribute">
                        <option value=""><?php esc_html_e( '-- None --', 'gm2-category-sort' ); ?></option>
                        <?php foreach ( (array) $attributes as $attr ) : ?>
                            <option value="<?php echo esc_attr( $attr->attribute_name ); ?>"><?php echo esc_html( $attr->attribute_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <?php esc_html_e( 'or create new attribute name:', 'gm2-category-sort' ); ?>
                    <input type="text" name="new_attribute" />
                </p>
                <?php submit_button( __( 'Merge Attributes', 'gm2-category-sort' ) ); ?>
            </form>

            <hr />
            <h2><?php esc_html_e( 'Merge Terms', 'gm2-category-sort' ); ?></h2>
            <form method="get" style="margin-bottom:1em;">
                <input type="hidden" name="page" value="gm2-attribute-fixer">
                <select name="attr">
                    <?php foreach ( (array) $attributes as $attr ) : ?>
                        <option value="<?php echo esc_attr( $attr->attribute_name ); ?>" <?php selected( $current, $attr->attribute_name ); ?>><?php echo esc_html( $attr->attribute_label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button"><?php esc_html_e( 'Load Terms', 'gm2-category-sort' ); ?></button>
            </form>
            <form method="post">
                <?php wp_nonce_field( 'gm2_merge_terms', 'gm2_merge_terms_nonce' ); ?>
                <input type="hidden" name="attribute" value="<?php echo esc_attr( $current ); ?>">
                <?php if ( $terms ) : ?>
                    <p><?php esc_html_e( 'Select terms to merge:', 'gm2-category-sort' ); ?></p>
                    <?php foreach ( $terms as $term ) : ?>
                        <label><input type="checkbox" name="terms[]" value="<?php echo esc_attr( $term->term_id ); ?>"> <?php echo esc_html( $term->name ); ?></label><br>
                    <?php endforeach; ?>
                    <p>
                        <?php esc_html_e( 'Merge into existing term:', 'gm2-category-sort' ); ?>
                        <select name="target_term">
                            <option value=""><?php esc_html_e( '-- None --', 'gm2-category-sort' ); ?></option>
                            <?php foreach ( $terms as $term ) : ?>
                                <option value="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p>
                        <?php esc_html_e( 'or create new term name:', 'gm2-category-sort' ); ?>
                        <input type="text" name="new_term" />
                    </p>
                    <?php submit_button( __( 'Merge Terms', 'gm2-category-sort' ) ); ?>
                <?php else : ?>
                    <p><?php esc_html_e( 'No terms found.', 'gm2-category-sort' ); ?></p>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }
}