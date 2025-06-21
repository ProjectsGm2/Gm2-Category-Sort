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
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5 );
        $text = strtr( $text, [
            '′' => "'",
            '″' => '"',
            '‘' => "'",
            '’' => "'",
            '“' => '"',
            '”' => '"',
        ] );
        $text = strtolower( $text );
        foreach ( self::$replacements as $key => $val ) {
            $text = preg_replace( '/\b' . preg_quote( $key, '/' ) . '\b/', $val, $text );
        }
        return preg_replace( '/\s+/', ' ', $text );
    }

    /**
     * Sanitize a category segment for use in branch slugs while keeping quotes distinct.
     *
     * Quotes and prime characters are converted to "d" (double) or "s" (single)
     * before WordPress sanitization so slugs differ for segments like `19"` and
     * `19'`.
     *
     * @param string $segment Raw category segment.
     * @return string Sanitized slug portion.
     */
    public static function slugify_segment( $segment ) {
        $segment = strtr( $segment, [
            '″' => 'd',
            '“' => 'd',
            '”' => 'd',
            '"' => 'd',
            '′' => 's',
            '‘' => 's',
            '’' => 's',
            "'" => 's',
        ] );
        return sanitize_title( $segment );
    }

    /**
     * Determine if a normalized rule keyword exists within the normalized text.
     *
     * Underscores in the keyword act as wildcards matching any words in
     * between the segments. For example, the keyword "foo_bar" matches
     * "foo something bar".
     *
     * @param string $keyword Normalized keyword from rules.
     * @param string $text    Normalized text to search.
     * @return bool True when the keyword matches.
     */
    protected static function match_rule_keyword( $keyword, $text ) {
        if ( $keyword === '' ) {
            return false;
        }
        if ( strpos( $keyword, '_' ) === false ) {
            return strpos( $text, $keyword ) !== false;
        }
        $parts  = array_filter( explode( '_', $keyword ) );
        $offset = 0;
        foreach ( $parts as $part ) {
            $pos = strpos( $text, $part, $offset );
            if ( $pos === false ) {
                return false;
            }
            $offset = $pos + strlen( $part );
        }
        return true;
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
     * Build wheel size synonym list from a category tree CSV.
     *
     * @param string $file CSV file path.
     * @return array<string,array>
     */
    protected static function build_wheel_sizes_from_tree( $file ) {
        $sizes = [];
        if ( ! file_exists( $file ) ) {
            return $sizes;
        }
        $rows = array_map( 'str_getcsv', file( $file ) );
        foreach ( $rows as $row ) {
            $idx = array_search( 'By Wheel Size', $row, true );
            if ( $idx === false ) {
                continue;
            }
            $size_seg = $row[ $idx + 1 ] ?? '';
            if ( $size_seg === '' ) {
                continue;
            }
            list( $size_name, $size_syns ) = self::parse_segment( $size_seg );
            if ( ! isset( $sizes[ $size_name ] ) ) {
                $sizes[ $size_name ] = [];
            }
            $sizes[ $size_name ] = array_merge( $sizes[ $size_name ], [ $size_name ], $size_syns );
        }
        foreach ( $sizes as $s => $terms ) {
            $sizes[ $s ] = array_values( array_unique( array_filter( $terms ) ) );
        }
        return $sizes;
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
     * Extract mapping entries that belong to the "By Wheel Size" branch.
     *
     * This scans the full term mapping and returns a new array containing only
     * the entries whose category path includes the "By Wheel Size" segment. It
     * allows wheel size categories to be discovered regardless of where the
     * branch sits in the overall hierarchy.
     *
     * @param array<string,array> $mapping Full term mapping.
     * @return array<string,array> Filtered mapping for wheel size terms.
     */
    protected static function extract_wheel_size_map( array $mapping ) {
        return self::filter_by_segment( $mapping, 'By Wheel Size' );
    }

    /**
     * Filter a term mapping to only entries containing a specific branch name.
     *
     * @param array  $mapping Full mapping from term => category path.
     * @param string $segment Branch label to search for.
     * @return array<string,array>
     */
    protected static function filter_by_segment( array $mapping, $segment ) {
        $result = [];
        foreach ( $mapping as $term => $paths ) {
            foreach ( $paths as $path ) {
                if ( in_array( $segment, $path, true ) ) {
                    if ( ! isset( $result[ $term ] ) ) {
                        $result[ $term ] = [];
                    }
                    if ( ! in_array( $path, $result[ $term ], true ) ) {
                        $result[ $term ][] = $path;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Basic term matching helper used by many category checks.
     *
     * @param string $lower    Normalized text.
     * @param array  $words    Tokenized words from $lower.
     * @param array  $mapping  Filtered mapping to check.
     * @param bool   $fuzzy    Whether to allow fuzzy matching.
     * @param int    $threshold Fuzzy matching threshold.
     * @return array List of category names to assign.
     */
    protected static function match_terms( $lower, array $words, array $mapping, $fuzzy, $threshold, array $attributes = [], array &$assigned = null, array $branch_rules = [] ) {
        $cats       = [];
        $word_count = count( $words );
        foreach ( $mapping as $term => $paths ) {
            $matched = false;
            $end_boundary = '/(?<!\\w)' . preg_quote( $term, '/' );
            if ( substr( $term, -1 ) === '"' || substr( $term, -1 ) === "'" ) {
                $end_boundary .= '(?=$|[^\\w]|[xX])';
            } else {
                $end_boundary .= '(?!\\w)';
            }
            if ( preg_match( $end_boundary . '/', $lower ) ) {
                $matched = true;
            } elseif ( $fuzzy ) {
                $term_words = preg_split( '/\\s+/', $term );
                $n          = count( $term_words );
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
            foreach ( $paths as $path ) {
                if ( ! self::passes_branch_rules_for_path( $path, $lower, $attributes ) ) {
                    continue;
                }
                if ( is_array( $assigned ) ) {
                    self::add_path_categories( $path, $cats, $assigned, $branch_rules );
                } else {
                    foreach ( $path as $cat ) {
                        if ( ! in_array( $cat, $cats, true ) ) {
                            $cats[] = $cat;
                        }
                    }
                }
            }
        }
        return $cats;
    }

    /** Helper for root wheel simulator keywords. */
    protected static function check_wheel_simulators( $lower, array $attributes = [] ) {
        $cats        = [];
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
        if ( $cats && ! self::passes_branch_rules_for_path( $cats, $lower, $attributes ) ) {
            return [];
        }
        return $cats;
    }

    /** Brand and model branch logic. */
    protected static function check_brand_model( $lower, array $words, array $mapping, $fuzzy, $threshold, $csv_dir, array $attributes = [], array &$assigned = null, array $branch_rules = [] ) {
        $brands       = [];
        $brand_models = [];

        $flat_mapping = [];
        foreach ( $mapping as $t => $paths ) {
            foreach ( $paths as $p ) {
                $flat_mapping[] = [ 'term' => $t, 'path' => $p ];
            }
        }

        if ( $csv_dir ) {
            list( $csv_brands, $csv_models ) = self::load_brand_model_csv( $csv_dir );
            foreach ( $flat_mapping as $entry ) {
                $term = $entry['term'];
                $path = $entry['path'];
                $brand_idx = self::find_brand_index( $path );
                if ( $brand_idx === false ) {
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
                    $model_words = preg_split( '/\\s+/', self::normalize_text( $model ) );
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
            foreach ( $flat_mapping as $entry ) {
                $term = $entry['term'];
                $path = $entry['path'];
                $brand_idx = self::find_brand_index( $path );
                if ( $brand_idx === false ) {
                    continue;
                }
                if ( isset( $path[ $brand_idx + 1 ] ) && ! isset( $path[ $brand_idx + 2 ] ) ) {
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
                    $brand      = $path[ $brand_idx + 1 ];
                    $model_name = self::normalize_text( $path[ $brand_idx + 2 ] );
                    $m_words    = preg_split( '/\\s+/', $model_name );
                    $m_words    = array_values( array_filter( $m_words, static function ( $w ) {
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
        }

        $cats         = [];
        $brand_matches = [];
        $word_count    = count( $words );

        foreach ( $brands as $brand => $entries ) {
            foreach ( $entries as $entry ) {
                $term    = $entry['term'];
                $matched = false;
                if ( preg_match( '/(?<!\\w)' . preg_quote( $term, '/' ) . '(?!\\w)/', $lower ) ) {
                    $matched = true;
                } elseif ( $fuzzy ) {
                    $term_words = preg_split( '/\\s+/', $term );
                    $n          = count( $term_words );
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
                if ( ! self::passes_branch_rules_for_path( $entry['path'], $lower, $attributes ) ) {
                    continue;
                }
                $brand_matches[ $brand ] = $term;
                if ( is_array( $assigned ) ) {
                    self::add_path_categories( $entry['path'], $cats, $assigned, $branch_rules );
                } else {
                    foreach ( $entry['path'] as $cat ) {
                        if ( ! in_array( $cat, $cats, true ) ) {
                            $cats[] = $cat;
                        }
                    }
                }
                break;
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
                $brand_norm      = self::normalize_text( $brand_term );
                $first_word      = $model['model_words'][0] ?? '';
                $close_pattern   = '/\\b' . preg_quote( $brand_norm, '/' ) . '\\b.{0,40}\\b' . preg_quote( $first_word, '/' ) . '/';
                $reverse_pattern = '/\\b' . preg_quote( $first_word, '/' ) . '\\b.{0,40}\\b' . preg_quote( $brand_norm, '/' ) . '/';
                if ( ! preg_match( $close_pattern, $lower ) && ! preg_match( $reverse_pattern, $lower ) ) {
                    continue;
                }
                if ( ! self::passes_branch_rules_for_path( $model['path'], $lower, $attributes ) ) {
                    continue;
                }
                if ( is_array( $assigned ) ) {
                    self::add_path_categories( $model['path'], $cats, $assigned, $branch_rules );
                } else {
                    foreach ( $model['path'] as $cat ) {
                        if ( ! in_array( $cat, $cats, true ) ) {
                            $cats[] = $cat;
                        }
                    }
                }
            }
        }

        return $cats;
    }

    /** Wheel size branch logic. */
    protected static function check_wheel_size( $lower, array $mapping, $wheel_size_num, $wheel_size, $brand_found, array $attributes = [], array &$assigned = null, array $branch_rules = [] ) {
        if ( ! $wheel_size_num ) {
            return [];
        }
        $cats           = [];
        $wheel_size_map = self::extract_wheel_size_map( $mapping );
        $candidates     = [
            $wheel_size,
            $wheel_size_num . '"',
            $wheel_size_num . "'",
            $wheel_size_num . "\xE2\x80\xB3",
            $wheel_size_num,
        ];
        $found_child = false;
        foreach ( $candidates as $cand ) {
            $key = self::normalize_text( $cand );
            if ( isset( $wheel_size_map[ $key ] ) ) {
                foreach ( $wheel_size_map[ $key ] as $path ) {
                    if ( ! self::passes_branch_rules_for_path( $path, $lower, $attributes ) ) {
                        continue;
                    }
                    if ( is_array( $assigned ) ) {
                        self::add_path_categories( $path, $cats, $assigned, $branch_rules );
                    } else {
                        foreach ( $path as $cat ) {
                            if ( ! in_array( $cat, $cats, true ) ) {
                                $cats[] = $cat;
                            }
                        }
                    }
                }
                $found_child = true;
                break;
            }
        }
        if ( ! $found_child ) {
            foreach ( [ 'By Wheel Size', $wheel_size ] as $cat ) {
                if ( ! in_array( $cat, $cats, true ) ) {
                    $cats[] = $cat;
                }
            }
        }
        if ( ! $found_child ) {
            $path_for_rules = $cats;
            if ( ! self::passes_branch_rules_for_path( $path_for_rules, $lower, $attributes ) ) {
                return [];
            }
            if ( is_array( $assigned ) ) {
                self::add_path_categories( $path_for_rules, $cats, $assigned, $branch_rules );
                return $cats;
            }
        }
        return $cats;
    }

    /** Lug/Hole configuration branch logic. */
    protected static function check_lug_hole( $lower, array $words, array $mapping, $fuzzy, $threshold, array $attributes = [] ) {
        $subset      = self::filter_by_segment( $mapping, 'By Lug/Hole Configuration' );
        $candidates  = [];
        $word_count  = count( $words );
        foreach ( $subset as $term => $paths ) {
            $matched = false;
            if ( preg_match( '/(?<!\\w)' . preg_quote( $term, '/' ) . '(?!\\w)/', $lower ) ) {
                $matched = true;
            } elseif ( $fuzzy ) {
                $term_words = preg_split( '/\\s+/', $term );
                $n          = count( $term_words );
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
            foreach ( $paths as $path ) {
                $candidates[] = [ 'term' => $term, 'path' => $path ];
            }
        }
        if ( ! $candidates ) {
            return [];
        }
        $lug_num = null;
        if ( preg_match( '/\b(\d+)\s*lugs?\b/', $lower, $m ) ) {
            $lug_num = $m[1];
        }
        usort( $candidates, static function ( $a, $b ) use ( $lug_num ) {
            $ta = $a['term'];
            $tb = $b['term'];
            if ( $lug_num !== null ) {
                $a_has = strpos( $ta, $lug_num ) !== false;
                $b_has = strpos( $tb, $lug_num ) !== false;
                if ( $a_has && ! $b_has ) {
                    return -1;
                }
                if ( $b_has && ! $a_has ) {
                    return 1;
                }
            }
            return strlen( $tb ) <=> strlen( $ta );
        } );
        foreach ( $candidates as $cand ) {
            $path = $cand['path'];
            if ( self::passes_branch_rules_for_path( $path, $lower, $attributes ) ) {
                return array_values( array_unique( $path ) );
            }
        }
        return [];
    }

    /** Generic helpers for additional branches. */
    protected static function check_wheel_type( $lower, array $words, array $mapping, $fuzzy, $threshold, array $attributes = [] ) {
        return self::match_terms( $lower, $words, self::filter_by_segment( $mapping, 'By Wheel Type' ), $fuzzy, $threshold, $attributes );
    }

    protected static function check_set_sizes( $lower, array $words, array $mapping, $fuzzy, $threshold, array $attributes = [] ) {
        return self::match_terms( $lower, $words, self::filter_by_segment( $mapping, 'By Wheel Set Sizes' ), $fuzzy, $threshold, $attributes );
    }

    protected static function check_fit_type( $lower, array $words, array $mapping, $fuzzy, $threshold, array $attributes = [] ) {
        return self::match_terms( $lower, $words, self::filter_by_segment( $mapping, 'By Fit Type' ), $fuzzy, $threshold, $attributes );
    }

    protected static function check_vehicle_type( $lower, array $words, array $mapping, $fuzzy, $threshold, array $attributes = [] ) {
        return self::match_terms( $lower, $words, self::filter_by_segment( $mapping, 'By Vehicle Type' ), $fuzzy, $threshold, $attributes );
    }

    protected static function check_ring_mount( $lower, array $words, array $mapping, $fuzzy, $threshold, array $attributes = [] ) {
        return self::match_terms( $lower, $words, self::filter_by_segment( $mapping, 'Ring Mount' ), $fuzzy, $threshold, $attributes );
    }

    protected static function check_dayton_spoke( $lower, array $words, array $mapping, $fuzzy, $threshold, array $attributes = [] ) {
        return self::match_terms( $lower, $words, self::filter_by_segment( $mapping, 'Dayton Spoke' ), $fuzzy, $threshold, $attributes );
    }

    protected static function check_wheel_center_caps( $lower, array $words, array $mapping, $fuzzy, $threshold, array $attributes = [] ) {
        return self::match_terms( $lower, $words, self::filter_by_segment( $mapping, 'Wheel Center Caps' ), $fuzzy, $threshold, $attributes );
    }

    protected static function check_wheel_cover_parts( $lower, array $words, array $mapping, $fuzzy, $threshold, array $attributes = [] ) {
        return self::match_terms( $lower, $words, self::filter_by_segment( $mapping, 'Wheel Cover Parts' ), $fuzzy, $threshold, $attributes );
    }

    protected static function check_seat_covers( $lower, array $words, array $mapping, $fuzzy, $threshold, array $attributes = [] ) {
        return self::match_terms( $lower, $words, self::filter_by_segment( $mapping, 'Seat Covers' ), $fuzzy, $threshold, $attributes );
    }

    protected static function check_coverking_accessories( $lower, array $words, array $mapping, $fuzzy, $threshold, array $attributes = [] ) {
        return self::match_terms( $lower, $words, self::filter_by_segment( $mapping, 'Coverking Accessories' ), $fuzzy, $threshold, $attributes );
    }

    protected static function check_accessories_hardware( $lower, array $words, array $mapping, $fuzzy, $threshold, array $attributes = [] ) {
        return self::match_terms( $lower, $words, self::filter_by_segment( $mapping, 'Accessories & Hardware' ), $fuzzy, $threshold, $attributes );
    }

    protected static function check_brands( $lower, array $words, array $mapping, $fuzzy, $threshold, array $attributes = [] ) {
        return self::match_terms( $lower, $words, self::filter_by_segment( $mapping, 'Brands' ), $fuzzy, $threshold, $attributes );
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
                        $mapping[ $key ] = [];
                    }
                    $exists = false;
                    foreach ( $mapping[ $key ] as $existing ) {
                        if ( $existing === $path ) {
                            $exists = true;
                            break;
                        }
                    }
                    if ( ! $exists ) {
                        $mapping[ $key ][] = $path;
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
    public static function assign_categories( $text, array $mapping, $fuzzy = false, $threshold = 85, $csv_dir = null, array $attributes = [] ) {
        $decoded       = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5 );
        $lower         = self::normalize_text( $decoded );
        $cats          = [];
        $branch_rules  = get_option( 'gm2_branch_rules', [] );
        $assigned      = [];
        $words          = preg_split( '/\s+/', $lower );
        $wheel_size_num = null;
        $wheel_size     = null;
        if ( preg_match(
            '/(?<![\d.])(\d{1,2}(?:\.\d+)?)(["\'\x{201C}\x{201D}\x{2019}\x{2032}\x{2033}])(?:[xX])?/u',
            $decoded,
            $m
        ) ) {
            $wheel_size_num = $m[1];
            $quote          = strtr( $m[2], [ "\xE2\x80\xB3" => '"', "\xE2\x80\xB2" => "'", '“' => '"', '”' => '"', '‘' => "'", '’' => "'" ] );
            $wheel_size     = $wheel_size_num . $quote;
        }
        $brand_found = false;

        $exclude_segments = [
            'By Wheel Size',
            'By Lug/Hole Configuration',
            'By Wheel Type',
            'By Wheel Set Sizes',
            'By Fit Type',
            'By Vehicle Type',
            'Ring Mount',
            'Dayton Spoke',
            'Wheel Center Caps',
            'Wheel Cover Parts',
            'Seat Covers',
            'Coverking Accessories',
            'Accessories & Hardware',
            'Brands',
        ];

        $other_map = [];
        foreach ( $mapping as $term => $paths ) {
            foreach ( $paths as $path ) {
                if ( self::find_brand_index( $path ) !== false ) {
                    continue;
                }
                $skip = false;
                foreach ( $exclude_segments as $seg ) {
                    if ( in_array( $seg, $path, true ) ) {
                        $skip = true;
                        break;
                    }
                }
                if ( $skip ) {
                    continue;
                }
                if ( ! isset( $other_map[ $term ] ) ) {
                    $other_map[ $term ] = [];
                }
                if ( ! in_array( $path, $other_map[ $term ], true ) ) {
                    $other_map[ $term ][] = $path;
                }
            }
        }

        $cats = array_merge( $cats, self::match_terms( $lower, $words, $other_map, $fuzzy, $threshold, $attributes, $assigned, $branch_rules ) );

        $sim = self::check_wheel_simulators( $lower, $attributes );
        if ( $sim ) {
            foreach ( $sim as $c ) {
                if ( ! in_array( $c, $cats, true ) ) {
                    $cats[] = $c;
                }
            }
            $brand_found = true;
        }

        $bm = self::check_brand_model( $lower, $words, $mapping, $fuzzy, $threshold, $csv_dir, $attributes, $assigned, $branch_rules );
        foreach ( $bm as $c ) {
            if ( ! in_array( $c, $cats, true ) ) {
                $cats[] = $c;
            }
        }

        if ( ! $wheel_size_num && $brand_found && preg_match('/(?<![\\d.])(\\d{1,2}(?:\\.\\d+)?)(?=\\s)/u', $decoded, $m) ) {
            $wheel_size_num = $m[1];
            $wheel_size     = $wheel_size_num . '"';
        }

        $ws = self::check_wheel_size( $lower, $mapping, $wheel_size_num, $wheel_size, $brand_found, $attributes, $assigned, $branch_rules );
        foreach ( $ws as $c ) {
            if ( ! in_array( $c, $cats, true ) ) {
                $cats[] = $c;
            }
        }

        $lh = self::check_lug_hole( $lower, $words, $mapping, $fuzzy, $threshold, $attributes );
        foreach ( $lh as $c ) {
            if ( ! in_array( $c, $cats, true ) ) {
                $cats[] = $c;
            }
        }

        $wt = self::check_wheel_type( $lower, $words, $mapping, $fuzzy, $threshold, $attributes );
        foreach ( $wt as $c ) {
            if ( ! in_array( $c, $cats, true ) ) {
                $cats[] = $c;
            }
        }

        $ss = self::check_set_sizes( $lower, $words, $mapping, $fuzzy, $threshold, $attributes );
        foreach ( $ss as $c ) {
            if ( ! in_array( $c, $cats, true ) ) {
                $cats[] = $c;
            }
        }

        $ft = self::check_fit_type( $lower, $words, $mapping, $fuzzy, $threshold, $attributes );
        foreach ( $ft as $c ) {
            if ( ! in_array( $c, $cats, true ) ) {
                $cats[] = $c;
            }
        }

        $vt = self::check_vehicle_type( $lower, $words, $mapping, $fuzzy, $threshold, $attributes );
        foreach ( $vt as $c ) {
            if ( ! in_array( $c, $cats, true ) ) {
                $cats[] = $c;
            }
        }

        $rm = self::check_ring_mount( $lower, $words, $mapping, $fuzzy, $threshold, $attributes );
        foreach ( $rm as $c ) {
            if ( ! in_array( $c, $cats, true ) ) {
                $cats[] = $c;
            }
        }

        $ds = self::check_dayton_spoke( $lower, $words, $mapping, $fuzzy, $threshold, $attributes );
        foreach ( $ds as $c ) {
            if ( ! in_array( $c, $cats, true ) ) {
                $cats[] = $c;
            }
        }

        $cap = self::check_wheel_center_caps( $lower, $words, $mapping, $fuzzy, $threshold, $attributes );
        foreach ( $cap as $c ) {
            if ( ! in_array( $c, $cats, true ) ) {
                $cats[] = $c;
            }
        }

        $wcp = self::check_wheel_cover_parts( $lower, $words, $mapping, $fuzzy, $threshold, $attributes );
        foreach ( $wcp as $c ) {
            if ( ! in_array( $c, $cats, true ) ) {
                $cats[] = $c;
            }
        }

        $sc = self::check_seat_covers( $lower, $words, $mapping, $fuzzy, $threshold, $attributes );
        foreach ( $sc as $c ) {
            if ( ! in_array( $c, $cats, true ) ) {
                $cats[] = $c;
            }
        }

        $cka = self::check_coverking_accessories( $lower, $words, $mapping, $fuzzy, $threshold, $attributes );
        foreach ( $cka as $c ) {
            if ( ! in_array( $c, $cats, true ) ) {
                $cats[] = $c;
            }
        }

        $ah = self::check_accessories_hardware( $lower, $words, $mapping, $fuzzy, $threshold, $attributes );
        foreach ( $ah as $c ) {
            if ( ! in_array( $c, $cats, true ) ) {
                $cats[] = $c;
            }
        }

        $br = self::check_brands( $lower, $words, $mapping, $fuzzy, $threshold, $attributes );
        foreach ( $br as $c ) {
            if ( ! in_array( $c, $cats, true ) ) {
                $cats[] = $c;
            }
        }
        if ( is_array( $branch_rules ) && $branch_rules ) {
            foreach ( $branch_rules as $slug => $rule ) {
                $includes = array_filter( array_map( 'trim', explode( ',', $rule['include'] ?? '' ) ) );
                if ( ! $includes ) {
                    continue;
                }
                $excludes = array_filter( array_map( 'trim', explode( ',', $rule['exclude'] ?? '' ) ) );

                $has_include = false;
                foreach ( $includes as $t ) {
                    $t = self::normalize_text( $t );
                    if ( self::match_rule_keyword( $t, $lower ) ) {
                        $has_include = true;
                        break;
                    }
                }
                if ( ! $has_include ) {
                    continue;
                }
                $skip = false;
                foreach ( $excludes as $t ) {
                    $t = self::normalize_text( $t );
                    if ( self::match_rule_keyword( $t, $lower ) ) {
                        $skip = true;
                        break;
                    }
                }
                if ( $skip ) {
                    continue;
                }
                $path = self::path_from_branch_slug( $slug );
                if ( is_array( $assigned ) ) {
                    self::add_path_categories( $path, $cats, $assigned, $branch_rules, $slug );
                } else {
                    foreach ( $path as $cat ) {
                        if ( ! in_array( $cat, $cats, true ) ) {
                            $cats[] = $cat;
                        }
                    }
                }
            }
        }

        return $cats;
    }

    /**
     * Check branch rules for a specific category path.
     *
     * @param array  $path  Category names from root to leaf.
     * @param string $lower Normalized text being analyzed.
     * @return bool True when the path should be allowed.
     */
    protected static function passes_branch_rules_for_path( array $path, $lower, array $attributes = [] ) {
        $rules = get_option( 'gm2_branch_rules', [] );
        if ( ! is_array( $rules ) || ! $rules ) {
            return true;
        }
        for ( $i = 0; $i < count( $path ) - 1; $i++ ) {
            $slug        = self::slugify_segment( $path[ $i ] ) . '-' . self::slugify_segment( $path[ $i + 1 ] );
            $legacy_slug = sanitize_title( $path[ $i ] ) . '-' . sanitize_title( $path[ $i + 1 ] );
            if ( isset( $rules[ $slug ] ) ) {
                $rule = $rules[ $slug ];
            } elseif ( isset( $rules[ $legacy_slug ] ) ) {
                $rule = $rules[ $legacy_slug ];
            } else {
                continue;
            }
            $includes = array_filter( array_map( 'trim', explode( ',', $rule['include'] ?? '' ) ) );
            $excludes = array_filter( array_map( 'trim', explode( ',', $rule['exclude'] ?? '' ) ) );
            if ( $includes ) {
                $found = false;
                foreach ( $includes as $term ) {
                    $term = self::normalize_text( $term );
                    if ( self::match_rule_keyword( $term, $lower ) ) {
                        $found = true;
                        break;
                    }
                }
                if ( ! $found ) {
                    return false;
                }
            }
            foreach ( $excludes as $term ) {
                $term = self::normalize_text( $term );
                if ( self::match_rule_keyword( $term, $lower ) ) {
                    return false;
                }
            }

            $include_attrs = $rule['include_attrs'] ?? [];
            foreach ( $include_attrs as $attr => $terms ) {
                $found = false;
                $attr  = sanitize_key( $attr );
                foreach ( (array) $terms as $t ) {
                    $slug = sanitize_title( $t );
                    if ( isset( $attributes[ $attr ] ) && in_array( $slug, $attributes[ $attr ], true ) ) {
                        $found = true;
                        break;
                    }
                }
                if ( ! $found ) {
                    return false;
                }
            }

            $exclude_attrs = $rule['exclude_attrs'] ?? [];
            foreach ( $exclude_attrs as $attr => $terms ) {
                $attr = sanitize_key( $attr );
                foreach ( (array) $terms as $t ) {
                    $slug = sanitize_title( $t );
                    if ( isset( $attributes[ $attr ] ) && in_array( $slug, $attributes[ $attr ], true ) ) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Assign categories based solely on attribute rules.
     *
     * @param array $attributes Mapping of attribute slugs to selected term slugs.
     * @return array List of category names that match the attribute rules.
     */
    public static function assign_categories_from_attributes( array $attributes, array &$assigned = null, array $branch_rules_override = [] ) {
        $rules = $branch_rules_override ? $branch_rules_override : get_option( 'gm2_branch_rules', [] );
        if ( ! is_array( $rules ) || ! $rules ) {
            return [];
        }

        $cats = [];
        foreach ( $rules as $slug => $rule ) {
            $inc = $rule['include_attrs'] ?? [];
            if ( ! $inc ) {
                continue;
            }

            $exc = $rule['exclude_attrs'] ?? [];

            $skip = false;
            foreach ( $exc as $attr => $terms ) {
                $attr = sanitize_key( $attr );
                foreach ( (array) $terms as $t ) {
                    $t = sanitize_title( $t );
                    if ( isset( $attributes[ $attr ] ) && in_array( $t, $attributes[ $attr ], true ) ) {
                        $skip = true;
                        break 2;
                    }
                }
            }
            if ( $skip ) {
                continue;
            }

            $match = true;
            foreach ( $inc as $attr => $terms ) {
                $attr  = sanitize_key( $attr );
                $found = false;
                foreach ( (array) $terms as $t ) {
                    $t = sanitize_title( $t );
                    if ( isset( $attributes[ $attr ] ) && in_array( $t, $attributes[ $attr ], true ) ) {
                        $found = true;
                        break;
                    }
                }
                if ( ! $found ) {
                    $match = false;
                    break;
                }
            }

            if ( ! $match ) {
                continue;
            }

            $path = self::path_from_branch_slug( $slug );
            if ( is_array( $assigned ) ) {
                self::add_path_categories( $path, $cats, $assigned, $rules, $slug );
            } else {
                foreach ( $path as $cat ) {
                    if ( ! in_array( $cat, $cats, true ) ) {
                        $cats[] = $cat;
                    }
                }
            }
        }

        return $cats;
    }

    /**
     * Get the category path represented by a branch slug.
     *
     * @param string $slug Branch slug from branch CSV filename.
     * @return array<string>
     */
    protected static function path_from_branch_slug( $slug ) {
        $upload = wp_upload_dir();
        $dir    = trailingslashit( $upload['basedir'] ) . 'gm2-category-sort/categories-structure';
        $file   = rtrim( $dir, '/' ) . '/' . sanitize_key( $slug ) . '.csv';
        if ( ! file_exists( $file ) ) {
            $legacy = rtrim( $dir, '/' ) . '/' . sanitize_key( sanitize_title( $slug ) ) . '.csv';
            if ( file_exists( $legacy ) ) {
                $file = $legacy;
            } else {
                return [];
            }
        }
        $rows = array_map( 'str_getcsv', file( $file ) );
        if ( empty( $rows ) ) {
            return [];
        }
        $row   = $rows[0];
        $path  = [];
        $parts = [];
        foreach ( $row as $segment ) {
            $segment = trim( $segment );
            if ( $segment === '' ) {
                continue;
            }
            $clean  = preg_replace( '/\s*\([^\)]*\)/', '', $segment );
            $path[] = $clean;
            $parts[] = self::slugify_segment( $clean );
            if ( implode( '-', $parts ) === $slug ) {
                break;
            }
        }
        return $path;
    }

    /**
     * Build a branch slug from a matched category path.
     *
     * @param array $path Category names from root to leaf.
     * @return string Branch slug.
     */
    protected static function branch_slug_from_path( array $path ) {
        $parts = array_map( [ __CLASS__, 'slugify_segment' ], $path );
        return implode( '-', $parts );
    }

    /**
     * Get the slug representing the parent portion of a branch path.
     *
     * @param array $path Category names from root to leaf.
     * @return string|null Parent slug or null when not applicable.
     */
    protected static function branch_parent_slug( array $path ) {
        if ( count( $path ) < 2 ) {
            return null;
        }
        $parent = array_slice( $path, 0, -1 );
        return implode( '-', array_map( [ __CLASS__, 'slugify_segment' ], $parent ) );
    }

    /**
     * Add categories from a path while respecting branch allow_multi settings.
     *
     * @param array       $path       Category path from root to leaf.
     * @param array       &$cats      Reference to the category list being built.
     * @param array       &$assigned  Tracking of assigned leaves per branch slug.
     * @param array       $rules      Branch rules option array.
     * @param string|null $slug       Optional branch slug to use for lookup.
     * @return void
     */
    protected static function add_path_categories( array $path, array &$cats, array &$assigned, array $rules, $slug = null ) {
        if ( $slug === null ) {
            $slug = self::branch_slug_from_path( $path );
        }
        $parent_slug = self::branch_parent_slug( $path );

        $allow_multi = null;
        for ( $i = count( $path ); $i >= 1; $i-- ) {
            $part_slug = self::branch_slug_from_path( array_slice( $path, 0, $i ) );
            if ( isset( $rules[ $part_slug ] ) && array_key_exists( 'allow_multi', $rules[ $part_slug ] ) ) {
                $allow_multi = (bool) $rules[ $part_slug ]['allow_multi'];
                break;
            }
        }
        if ( $allow_multi === null ) {
            $allow_multi = false;
        }

        if ( $parent_slug !== null && ! $allow_multi && ! empty( $assigned[ $parent_slug ] ) ) {
            return;
        }

        foreach ( $path as $cat ) {
            if ( ! in_array( $cat, $cats, true ) ) {
                $cats[] = $cat;
            }
        }

        if ( $parent_slug !== null ) {
            if ( ! isset( $assigned[ $parent_slug ] ) ) {
                $assigned[ $parent_slug ] = [];
            }
            $leaf = end( $path );
            if ( ! in_array( $leaf, $assigned[ $parent_slug ], true ) ) {
                $assigned[ $parent_slug ][] = $leaf;
            }
        }
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

        $sizes = self::build_wheel_sizes_from_tree( $tree_file );
        $size_file = rtrim( $dir, '/' ) . '/wheel-sizes.csv';
        if ( $fh = fopen( $size_file, 'w' ) ) {
            fputcsv( $fh, [ 'Size', 'Terms' ] );
            foreach ( $sizes as $size => $terms ) {
                fputcsv( $fh, [ $size, implode( ' | ', array_unique( $terms ) ) ] );
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
