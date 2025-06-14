<?php
/**
 * Generate product category assignments by analyzing product text.
 */
class Gm2_Category_Sort_Product_Category_Generator {

    /**
     * Build a mapping of category and synonym terms to their full hierarchy.
     *
     * This uses the globals populated by the test stubs.
     *
     * @return array<string,array> Mapping of lowercase term => list of category names from root to leaf.
     */
    public static function build_mapping_from_globals() {
        $id_to_parent = [];
        $id_to_name   = [];

        foreach ( $GLOBALS['gm2_test_terms'] as $parent => $terms ) {
            foreach ( $terms as $name => $id ) {
                $id_to_parent[ $id ] = (int) $parent;
                $id_to_name[ $id ]   = $name;
            }
        }

        $synonyms = [];
        foreach ( $GLOBALS['gm2_meta_updates'] as $meta ) {
            if ( $meta['key'] === 'gm2_synonyms' ) {
                $synonyms[ $meta['term_id'] ] = $meta['value'];
            }
        }

        $mapping = [];
        foreach ( $id_to_name as $id => $name ) {
            $path   = [];
            $curr   = $id;
            while ( $curr && isset( $id_to_name[ $curr ] ) ) {
                array_unshift( $path, $id_to_name[ $curr ] );
                $curr = $id_to_parent[ $curr ] ?? 0;
            }

            $terms = array_merge( [ $name ], array_filter( array_map( 'trim', explode( ',', $synonyms[ $id ] ?? '' ) ) ) );
            foreach ( $terms as $term ) {
                $key = strtolower( $term );
                if ( ! isset( $mapping[ $key ] ) ) {
                    $mapping[ $key ] = $path;
                }
            }
        }

        return $mapping;
    }

    /**
     * Assign categories to a block of text using a mapping.
     *
     * @param string $text    Product text.
     * @param array  $mapping Term mapping from build_mapping_from_globals().
     * @return array List of category names.
     */
    public static function assign_categories( $text, array $mapping ) {
        $lower = strtolower( $text );
        $cats  = [];
        foreach ( $mapping as $term => $path ) {
            if ( preg_match( '/(?<!\\w)' . preg_quote( $term, '/' ) . '(?!\\w)/', $lower ) ) {
                foreach ( $path as $cat ) {
                    if ( ! in_array( $cat, $cats, true ) ) {
                        $cats[] = $cat;
                    }
                }
            }
        }
        return $cats;
    }
}
