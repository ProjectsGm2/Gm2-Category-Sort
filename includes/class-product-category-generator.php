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
     * Locate the index of the brand branch within a category path.
     *
     * The tree may use different wording like "Brands" or "By Brand & Model". Any
     * segment containing the word "brand" (case-insensitive) is treated as the
     * start of the branch.
     *
     * @param array $path Category names from root to leaf.
     * @return int|false Index of the brand branch or false when absent.
     */
    protected static function find_brand_index( array $path ) {
        foreach ( $path as $i => $segment ) {
            if ( stripos( $segment, 'brand' ) !== false ) {
                return $i;
            }
        }
        return false;
    }

    /**
     * Split a category segment into name and synonyms.
     *
     * @param string $segment Raw segment from CSV.
     * @return array{0:string,1:array}
     */
    protected static function parse_segment( $segment ) {
        $segment = trim( $segment );
        if ( preg_match( '/^([^()]+)\(([^)]+)\)/', $segment, $m ) ) {
            $name = trim( $m[1] );
            $syns = array_map( 'trim', explode( ',', $m[2] ) );
        } else {
            $name = $segment;
            $syns = [];
        }
        return [ $name, $syns ];
    }

    /**
     * Build brand and model synonym lists from a category tree CSV.
     *
     * @param string $file CSV file path.
     * @return array{0:array,1:array}
     */
    protected static function build_brands_models_from_tree( $file ) {
        $brands = [];
        $models = [];
        if ( ! file_exists( $file ) ) {
            return [ $brands, $models ];
        }
        $rows = array_map( 'str_getcsv', file( $file ) );
        foreach ( $rows as $row ) {
            $brand_idx = false;
            foreach ( $row as $i => $seg ) {
                if ( stripos( $seg, 'brand' ) !== false ) {
                    $brand_idx = $i;
                    break;
                }
            }
            if ( $brand_idx === false ) {
                continue;
            }
            $brand_seg = $row[ $brand_idx + 1 ] ?? '';
            if ( $brand_seg === '' ) {
                continue;
            }
            list( $brand_name, $brand_syns ) = self::parse_segment( $brand_seg );
            if ( ! isset( $brands[ $brand_name ] ) ) {
                $brands[ $brand_name ] = [];
            }
            $brands[ $brand_name ] = array_merge( $brands[ $brand_name ], [ $brand_name ], $brand_syns );

            $model_seg = $row[ $brand_idx + 2 ] ?? '';
            if ( $model_seg !== '' ) {
                list( $model_name, $model_syns ) = self::parse_segment( $model_seg );
                if ( ! isset( $models[ $brand_name ] ) ) {
                    $models[ $brand_name ] = [];
                }
                if ( ! isset( $models[ $brand_name ][ $model_name ] ) ) {
                    $models[ $brand_name ][ $model_name ] = [];
                }
                $models[ $brand_name ][ $model_name ] = array_merge( $models[ $brand_name ][ $model_name ], [ $model_name ], $model_syns );
            }
        }
        foreach ( $brands as $b => $t ) {
            $brands[ $b ] = array_values( array_unique( array_filter( $t ) ) );
        }
        foreach ( $models as $b => $set ) {
            foreach ( $set as $m => $t ) {
                $models[ $b ][ $m ] = array_values( array_unique( array_filter( $t ) ) );
            }
        }
        return [ $brands, $models ];
    }

    /**
     * Load brand and model synonym lists from CSV files.
     *
     * @param string $dir Directory containing brands.csv and models.csv
     * @return array{0:array,1:array}
     */
    protected static function load_brand_model_csv( $dir ) {
        $brands = [];
        $models = [];
        $bfile  = rtrim( $dir, '/' ) . '/brands.csv';
        $mfile  = rtrim( $dir, '/' ) . '/models.csv';
        if ( file_exists( $bfile ) ) {
            $rows = array_map( 'str_getcsv', file( $bfile ) );
            array_shift( $rows );
            foreach ( $rows as $row ) {
                $brand = trim( $row[0] ?? '' );
                $terms = array_map( 'trim', explode( '|', $row[1] ?? '' ) );
                $brands[ $brand ] = array_filter( $terms );
            }
        }
        if ( file_exists( $mfile ) ) {
            $rows = array_map( 'str_getcsv', file( $mfile ) );
            array_shift( $rows );
            foreach ( $rows as $row ) {
                $brand = trim( $row[0] ?? '' );
                $model = trim( $row[1] ?? '' );
                $terms = array_map( 'trim', explode( '|', $row[2] ?? '' ) );
                if ( ! isset( $models[ $brand ] ) ) {
                    $models[ $brand ] = [];
                }
                $models[ $brand ][ $model ] = array_filter( $terms );
            }
        }
        return [ $brands, $models ];
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
    public static function assign_categories( $text, array $mapping, $fuzzy = false, $threshold = 85, $csv_dir = null ) {
        $lower = self::normalize_text( $text );
        $cats  = [];
        $words = preg_split( '/\s+/', $lower );
        $wheel_size = null;
        if ( preg_match(
            '/^\s*(\d{1,2}(?:\.\d+)?)(?=[\s"\'\x{201C}\x{201D}\x{2019}\x{2032}\x{2033}xX]|$)/u',
            $text,
            $m
        ) ) {
            $wheel_size = $m[1] . '"';
        }
        $word_count = count( $words );
        $lug_hole_candidates = [];
        $brands        = [];
        $brand_models  = [];
        $other_mapping = [];

        if ( $csv_dir ) {
            list( $csv_brands, $csv_models ) = self::load_brand_model_csv( $csv_dir );
            foreach ( $mapping as $term => $path ) {
                $brand_idx = self::find_brand_index( $path );
                if ( $brand_idx === false ) {
                    $other_mapping[ $term ] = $path;
                    continue;
                }
                $brand = $path[ $brand_idx + 1 ] ?? '';
                $model = $path[ $brand_idx + 2 ] ?? '';
                if ( $brand && isset( $csv_brands[ $brand ] ) && ! $model ) {
                    foreach ( $csv_brands[ $brand ] as $bterm ) {
                        if ( ! isset( $brands[ $brand ] ) ) {
                            $brands[ $brand ] = [];
                        }
                        $brands[ $brand ][] = [ 'term' => self::normalize_text( $bterm ), 'path' => $path ];
                    }
                } elseif ( $brand && $model && isset( $csv_models[ $brand ][ $model ] ) ) {
                    $model_words = preg_split( '/\s+/', self::normalize_text( $model ) );
                    $model_words = array_values( array_filter( $model_words, static function ( $w ) {
                        return ! in_array( $w, [ 'wheel', 'wheels', 'simulator', 'simulators', 'rim', 'liner', 'cover', 'covers', 'hubcap', 'hubcaps' ], true );
                    } ) );
                    foreach ( $csv_models[ $brand ][ $model ] as $mterm ) {
                        if ( ! isset( $brand_models[ $brand ] ) ) {
                            $brand_models[ $brand ] = [];
                        }
                        $brand_models[ $brand ][] = [
                            'term'        => self::normalize_text( $mterm ),
                            'path'        => $path,
                            'model_words' => $model_words,
                        ];
                    }
                }
            }
        } else {
            foreach ( $mapping as $term => $path ) {
                $brand_idx = self::find_brand_index( $path );
                if ( $brand_idx !== false ) {
                    if ( isset( $path[ $brand_idx + 1 ] ) && ! isset( $path[ $brand_idx + 2 ] ) ) {
                        // Skip numeric-only synonyms when matching brands to avoid
                        // confusing model numbers with the brand itself.
                        if ( ! preg_match( '/[a-z]/i', $term ) ) {
                            continue;
                        }
                        $brand = $path[ $brand_idx + 1 ];
                        if ( ! isset( $brands[ $brand ] ) ) {
                            $brands[ $brand ] = [];
                        }
                        $brands[ $brand ][] = [ 'term' => $term, 'path' => $path ];
                        continue;
                    }
                    if ( isset( $path[ $brand_idx + 2 ] ) ) {
                        $brand       = $path[ $brand_idx + 1 ];
                        $model_name  = self::normalize_text( $path[ $brand_idx + 2 ] );
                        $m_words     = preg_split( '/\s+/', $model_name );
                        $m_words     = array_values( array_filter( $m_words, static function ( $w ) {
                            return ! in_array( $w, [ 'wheel', 'wheels', 'simulator', 'simulators', 'rim', 'liner', 'cover', 'covers', 'hubcap', 'hubcaps' ], true );
                        } ) );
                        if ( ! isset( $brand_models[ $brand ] ) ) {
                            $brand_models[ $brand ] = [];
                        }
                        $brand_models[ $brand ][] = [
                            'term'        => $term,
                            'path'        => $path,
                            'model_words' => $m_words,
                        ];
                        continue;
                    }
                }

                $other_mapping[ $term ] = $path;
            }
        }


        $brand_matches = [];
        foreach ( $brands as $brand => $entries ) {
            foreach ( $entries as $entry ) {
                $term    = $entry['term'];
                $matched = false;
                if ( preg_match( '/(?<!\w)' . preg_quote( $term, '/' ) . '(?!\w)/', $lower ) ) {
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
                if ( ! $matched ) {
                    continue;
                }
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
                $brand_matches[ $brand ] = $term;
                foreach ( $entry['path'] as $cat ) {
                    if ( ! in_array( $cat, $cats, true ) ) {
                        $cats[] = $cat;
                    }
                }
                break;
            }
        }

        foreach ( $other_mapping as $term => $path ) {
            $matched = false;
            if ( preg_match( '/(?<!\w)' . preg_quote( $term, '/' ) . '(?!\w)/', $lower ) ) {
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
            if ( ! $matched ) {
                continue;
            }
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

        if ( $lug_hole_candidates ) {
            uksort( $lug_hole_candidates, static function ( $a, $b ) {
                return strlen( $b ) <=> strlen( $a );
            } );
            $path = reset( $lug_hole_candidates );
            foreach ( $path as $cat ) {
                if ( ! in_array( $cat, $cats, true ) ) {
                    $cats[] = $cat;
                }
            }
        }
        foreach ( $brand_matches as $brand => $brand_term ) {
            if ( empty( $brand_models[ $brand ] ) ) {
                continue;
            }
            foreach ( $brand_models[ $brand ] as $model ) {
                $all_present = true;
                foreach ( $model['model_words'] as $word ) {
                    if ( strpos( $lower, $word ) === false ) {
                        $all_present = false;
                        break;
                    }
                }
                if ( ! $all_present ) {
                    continue;
                }
                $brand_norm     = self::normalize_text( $brand_term );
                $first_word     = $model['model_words'][0] ?? '';
                $close_pattern  = '/\b' . preg_quote( $brand_norm, '/' ) . '\b.{0,40}\b' . preg_quote( $first_word, '/' ) . '/';
                $reverse_pattern = '/\b' . preg_quote( $first_word, '/' ) . '\b.{0,40}\b' . preg_quote( $brand_norm, '/' ) . '/';
                if ( ! preg_match( $close_pattern, $lower ) && ! preg_match( $reverse_pattern, $lower ) ) {
                    continue;
                }
                foreach ( $model['path'] as $cat ) {
                    if ( ! in_array( $cat, $cats, true ) ) {
                        $cats[] = $cat;
                    }
                }
            }
        }

        $brand_terms  = [ 'wheel simulator', 'rim liner', 'hubcap', 'wheel cover' ];
        $brand_found  = false;
        foreach ( $brand_terms as $term ) {
            if ( preg_match( '/(?<!\w)' . preg_quote( $term, '/' ) . '(?!\w)/', $lower ) ) {
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
                    $brand_found = true;
                    break;
                }
            }
        }

        if ( $brand_found && $wheel_size ) {
            foreach ( [ 'By Wheel Size', $wheel_size ] as $cat ) {
                if ( ! in_array( $cat, $cats, true ) ) {
                    $cats[] = $cat;
                }
            }
        }

        return $cats;
    }

    /**
     * Export brand and model lists used for matching to CSV files.
     *
     * @param array  $mapping Mapping from build_mapping_from_globals() or build_mapping().
     * @param string $dir     Directory path for CSV output.
     * @return void
     */
    public static function export_brand_model_csv( array $mapping, $dir ) {
        if ( ! is_dir( $dir ) ) {
            @mkdir( $dir, 0777, true );
        }

        self::export_category_tree_csv( $dir );
        $tree_file = rtrim( $dir, '/' ) . '/category-tree.csv';
        list( $brands, $models ) = self::build_brands_models_from_tree( $tree_file );

        $brand_file = rtrim( $dir, '/' ) . '/brands.csv';
        if ( $fh = fopen( $brand_file, 'w' ) ) {
            fputcsv( $fh, [ 'Brand', 'Terms' ] );
            foreach ( $brands as $brand => $terms ) {
                fputcsv( $fh, [ $brand, implode( ' | ', array_unique( $terms ) ) ] );
            }
            fclose( $fh );
        }

        $model_file = rtrim( $dir, '/' ) . '/models.csv';
        if ( $fh = fopen( $model_file, 'w' ) ) {
            fputcsv( $fh, [ 'Brand', 'Model', 'Terms' ] );
            foreach ( $models as $brand => $mset ) {
                foreach ( $mset as $model => $terms ) {
                    fputcsv( $fh, [ $brand, $model, implode( ' | ', array_unique( $terms ) ) ] );
                }
            }
            fclose( $fh );
        }
    }

    /**
     * Export the full product category tree to a CSV file.
     *
     * Each row lists the hierarchy from root to leaf. Synonyms are included in
     * parentheses after the category name.
     *
     * @param string $dir Directory path for CSV output.
     * @return void
     */
    public static function export_category_tree_csv( $dir ) {
        if ( ! is_dir( $dir ) ) {
            @mkdir( $dir, 0777, true );
        }

        $terms = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ] );

        if ( is_wp_error( $terms ) ) {
            return;
        }

        $id_to_parent = [];
        $id_to_name   = [];
        $synonyms     = [];

        foreach ( $terms as $term ) {
            $id_to_parent[ $term->term_id ] = (int) $term->parent;
            $id_to_name[ $term->term_id ]   = $term->name;
            $syn = get_term_meta( $term->term_id, 'gm2_synonyms', true );
            if ( $syn ) {
                $synonyms[ $term->term_id ] = $syn;
            }
        }

        $children = [];
        foreach ( $id_to_parent as $id => $parent ) {
            if ( ! isset( $children[ $parent ] ) ) {
                $children[ $parent ] = [];
            }
            $children[ $parent ][] = $id;
        }

        $file = rtrim( $dir, '/' ) . '/category-tree.csv';
        $fh   = fopen( $file, 'w' );
        if ( ! $fh ) {
            return;
        }

        $write = function ( $id, $path ) use ( &$write, &$children, &$id_to_name, &$synonyms, $fh ) {
            $name = $id_to_name[ $id ] ?? '';
            if ( isset( $synonyms[ $id ] ) && $synonyms[ $id ] !== '' ) {
                $name .= ' (' . $synonyms[ $id ] . ')';
            }
            $path[] = $name;
            if ( empty( $children[ $id ] ) ) {
                fputcsv( $fh, $path );
            } else {
                foreach ( $children[ $id ] as $child ) {
                    $write( $child, $path );
                }
            }
        };

        foreach ( $children[0] ?? [] as $root_id ) {
            $write( $root_id, [] );
        }

        fclose( $fh );
    }
}
