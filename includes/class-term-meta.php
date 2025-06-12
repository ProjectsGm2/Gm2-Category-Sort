<?php
class Gm2_Category_Sort_Term_Meta {
    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_meta' ] );
        add_action( 'product_cat_add_form_fields', [ __CLASS__, 'add_fields' ] );
        add_action( 'product_cat_edit_form_fields', [ __CLASS__, 'edit_fields' ] );
        add_action( 'created_product_cat', [ __CLASS__, 'save_fields' ] );
        add_action( 'edited_product_cat', [ __CLASS__, 'save_fields' ] );
    }

    public static function register_meta() {
        register_meta( 'term', 'gm2_primary_category', [
            'type'              => 'boolean',
            'single'            => true,
            'object_subtype'    => 'product_cat',
            'show_in_rest'      => true,
            'sanitize_callback' => 'wp_validate_boolean',
        ] );

        register_meta( 'term', 'gm2_synonyms', [
            'type'              => 'string',
            'single'            => true,
            'object_subtype'    => 'product_cat',
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_textarea_field',
        ] );
    }

    public static function add_fields() {
        ?>
        <div class="form-field">
            <label for="gm2_primary_category"><?php esc_html_e( 'Primary Category', 'gm2-category-sort' ); ?></label>
            <input type="checkbox" name="gm2_primary_category" id="gm2_primary_category" value="1">
            <p class="description"><?php esc_html_e( 'Mark this as a primary category.', 'gm2-category-sort' ); ?></p>
        </div>
        <div class="form-field">
            <label for="gm2_synonyms"><?php esc_html_e( 'Synonyms', 'gm2-category-sort' ); ?></label>
            <textarea name="gm2_synonyms" id="gm2_synonyms" rows="3" cols="40"></textarea>
            <p class="description"><?php esc_html_e( 'Comma-separated synonyms used in search.', 'gm2-category-sort' ); ?></p>
        </div>
        <?php
    }

    public static function edit_fields( $term ) {
        $primary  = get_term_meta( $term->term_id, 'gm2_primary_category', true );
        $synonyms = get_term_meta( $term->term_id, 'gm2_synonyms', true );
        ?>
        <tr class="form-field">
            <th scope="row"><label for="gm2_primary_category"><?php esc_html_e( 'Primary Category', 'gm2-category-sort' ); ?></label></th>
            <td>
                <label>
                    <input type="checkbox" name="gm2_primary_category" id="gm2_primary_category" value="1" <?php checked( $primary, true ); ?>>
                    <?php esc_html_e( 'Mark this as a primary category.', 'gm2-category-sort' ); ?>
                </label>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="gm2_synonyms"><?php esc_html_e( 'Synonyms', 'gm2-category-sort' ); ?></label></th>
            <td>
                <textarea name="gm2_synonyms" id="gm2_synonyms" rows="3" cols="40"><?php echo esc_textarea( $synonyms ); ?></textarea>
                <p class="description"><?php esc_html_e( 'Comma-separated synonyms used in search.', 'gm2-category-sort' ); ?></p>
            </td>
        </tr>
        <?php
    }

    public static function save_fields( $term_id ) {
        $primary  = isset( $_POST['gm2_primary_category'] ) ? 1 : 0;
        $synonyms = isset( $_POST['gm2_synonyms'] ) ? sanitize_textarea_field( $_POST['gm2_synonyms'] ) : '';

        update_term_meta( $term_id, 'gm2_primary_category', $primary );
        update_term_meta( $term_id, 'gm2_synonyms', $synonyms );
    }
}
