<?php
/**
 * Generate product category assignments by analyzing product text.
 */
class Gm2_Category_Sort_Product_Category_Generator {

    /** @var array<string,string> */
    protected static $replacements = [
        'lugs'            => 'lug',
        'holes'           => 'hole',
        'hh'              => 'hole',
        'hub caps'        => 'hubcap',
        'hub cap'         => 'hubcap',
        'wheelcovers'     => 'wheel cover',
        'wheelcover'      => 'wheel cover',
        'wheel-simulator' => 'wheel simulator',
        'wheel-simulators'=> 'wheel simulator',
        'over-lug'        => 'over lug',
        'rimliner'        => 'rim liner',
        'rim-liner'       => 'rim liner',
        'rim liners'      => 'rim liner',
    ];

    /** @var string[] */
    protected static $negation_patterns = [
        'not\s+for\s+%s',
        'does\s+not\s+fit\s+%s',
        'without\s+%s',
        'except\s+for\s+%s',
        'not\s+compatible\s+with\s+%s',
        'not\s+recommended\s+for\s+%s',
        'not\s+intended\s+for\s+%s',
    ];

    /**
     * Normalize text for matching.
     *
     * @param string $text Raw text.
     * @return string Normalized text.
     */
    public static function normalize_text( $text ) {
        $text = strtolower( $text );
        foreach ( self::$replacements as $key => $val ) {
            $text = preg_replace( '/\b' . preg_quote( $key, '/' ) . '\b/', $val, $text );
        }
        return preg_replace( '/\s+/', ' ', $text );
    }

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
            $path = [];
            $curr = $id;
            while ( $curr && isset( $id_to_name[ $curr ] ) ) {
                array_unshift( $path, $id_to_name[ $curr ] );
                $curr = $id_to_parent[ $curr ] ?? 0;
            }

            $terms = array_merge( [ $name ], array_filter( array_map( 'trim', explode( ',', $synonyms[ $id ] ?? '' ) ) ) );
            foreach ( $terms as $term ) {
                $variants = [ $term ];
                if ( substr( $term, -1 ) !== 's' ) {
                    $variants[] = $term . 's';
                } else {
                    $variants[] = substr( $term, 0, -1 );
                }
                if ( $term === 'hole' ) {
                    $variants[] = 'hh';
                    $variants[] = 'holes';
                }
                if ( $term === 'lug' ) {
                    $variants[] = 'lugs';
                }
                foreach ( $variants as $v ) {
                    $key = self::normalize_text( $v );
                    if ( ! isset( $mapping[ $key ] ) ) {
                        $mapping[ $key ] = $path;
                    }
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
    public static function assign_categories( $text, array $mapping, $fuzzy = false, $threshold = 85 ) {
        $lower = self::normalize_text( $text );
        $cats  = [];
        $words = preg_split( '/\s+/', $lower );
        $word_count = count( $words );
        $lug_hole_candidates = [];
        foreach ( $mapping as $term => $path ) {
            $matched = false;
            if ( preg_match( '/(?<!\\w)' . preg_quote( $term, '/' ) . '(?!\\w)/', $lower ) ) {
                $matched = true;
            } elseif ( $fuzzy ) {
                $term_words = preg_split( '/\s+/', $term );
                $n = count( $term_words );
                for ( $i = 0; $i <= $word_count - $n; $i++ ) {
                    $segment = implode( ' ', array_slice( $words, $i, $n ) );
                    similar_text( $term, $segment, $percent );
                    if ( $percent >= $threshold ) {
                        $matched = true;
                        break;
                    }
                }
            }

            if ( $matched ) {
                $neg = false;
                foreach ( self::$negation_patterns as $pattern ) {
                    $regex = '/' . sprintf( $pattern, preg_quote( $term, '/' ) ) . '/';
                    if ( preg_match( $regex, $lower ) ) {
                        $neg = true;
                        break;
                    }
                }
                if ( $neg ) {
                    continue;
                }
                if ( in_array( 'By Lug/Hole Configuration', $path, true ) ) {
                    if ( ! isset( $lug_hole_candidates[ $term ] ) ) {
                        $lug_hole_candidates[ $term ] = $path;
                    }
                } else {
                    foreach ( $path as $cat ) {
                        if ( ! in_array( $cat, $cats, true ) ) {
                            $cats[] = $cat;
                        }
                    }
                }
            }
        }
        if ( $lug_hole_candidates ) {
            uksort(
                $lug_hole_candidates,
                static function ( $a, $b ) {
                    return strlen( $b ) <=> strlen( $a );
                }
            );
            $path = reset( $lug_hole_candidates );
            foreach ( $path as $cat ) {
                if ( ! in_array( $cat, $cats, true ) ) {
                    $cats[] = $cat;
                }
            }
        }
      
        $brand_terms = [ 'wheel simulator', 'rim liner', 'hubcap', 'wheel cover' ];
        foreach ( $brand_terms as $term ) {
            if ( preg_match( '/(?<!\\w)' . preg_quote( $term, '/' ) . '(?!\\w)/', $lower ) ) {
                $neg = false;
                foreach ( self::$negation_patterns as $pattern ) {
                    $regex = '/' . sprintf( $pattern, preg_quote( $term, '/' ) ) . '/';
                    if ( preg_match( $regex, $lower ) ) {
                        $neg = true;
                        break;
                    }
                }
                if ( ! $neg ) {
                    foreach ( [ 'Wheel Simulators', 'Brands', 'Eagle Flight Wheel Simulators' ] as $cat ) {
                        if ( ! in_array( $cat, $cats, true ) ) {
                            $cats[] = $cat;
                        }
                    }
                    break;
                }
            }
        }

        return $cats;
    }
}
