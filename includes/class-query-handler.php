<?php
class Gm2_Category_Sort_Query_Handler {
    
    public static function init() {
        add_action('pre_get_posts', [__CLASS__, 'modify_archive_query']);
    }
    
    public static function modify_archive_query($query) {
        // Only modify the main query on frontend archive pages
        if (is_admin() || 
            !$query->is_main_query() || 
            !(is_shop() || is_product_taxonomy() || is_search())) {
            return;
        }
        
        // Check for our filter parameters
        if (empty($_GET['gm2_cat'])) {
            return;
        }
        
        $term_ids = array_map('intval', explode(',', $_GET['gm2_cat']));
        $filter_type = sanitize_key($_GET['gm2_filter_type'] ?? 'simple');
        $simple_operator = sanitize_key($_GET['gm2_simple_operator'] ?? 'IN');
        
        // Get existing tax query ensuring it's an array
        $tax_query = $query->get( 'tax_query' );
        if ( ! is_array( $tax_query ) ) {
            $tax_query = [];
        }

        // Remove any existing product_cat queries to prevent conflicts
        foreach ( $tax_query as $index => $tax ) {
            if ( is_array( $tax ) && isset( $tax['taxonomy'] ) && 'product_cat' === $tax['taxonomy'] ) {
                unset( $tax_query[ $index ] );
            }
        }
        $tax_query = array_values( $tax_query ); // Reindex array
        
        if ($filter_type === 'advanced') {
            // Advanced logic: Custom logic based on your requirements
            $category_query = self::build_advanced_query($term_ids);
        } else {
            // Simple logic: single operator for all terms
            $category_query = [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $term_ids,
                'operator' => $simple_operator,
                'include_children' => true,
            ];
        }
        
        // Add our category filter
        $tax_query[] = $category_query;
        $query->set('tax_query', $tax_query);
    }
    
    public static function build_advanced_query($term_ids) {
        // If only one category is selected, use simple IN query
        if (count($term_ids) === 1) {
            return [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $term_ids,
                'include_children' => true,
            ];
        }
        
        // Group terms by their root branch
        $branches = [];
        foreach ($term_ids as $term_id) {
            $root_id = self::get_root_category_id($term_id);
            if ($root_id) {
                $branches[$root_id][] = $term_id;
            }
        }
        
        // If all terms are from the same branch, use AND logic
        if (count($branches) === 1) {
            $clauses = array_map(function ($term_id) {
                return [
                    'taxonomy'        => 'product_cat',
                    'field'           => 'term_id',
                    'terms'           => [$term_id],
                    'include_children'=> true,
                ];
            }, $term_ids);

            return array_merge(['relation' => 'AND'], $clauses);
        }
        
        // If terms are from different branches, use OR logic
        return [
            'taxonomy' => 'product_cat',
            'field' => 'term_id',
            'terms' => $term_ids,
            'operator' => 'IN',
            'include_children' => true,
        ];
    }
    
    private static function get_root_category_id($term_id) {
        $term = get_term($term_id, 'product_cat');
        if (is_wp_error($term) || !$term) return 0;
        
        // If it's a top-level category, return itself
        if ($term->parent == 0) return $term->term_id;
        
        // Traverse up to find root category
        $ancestors = get_ancestors($term_id, 'product_cat');
        if (empty($ancestors)) return $term->term_id;
        
        // Return the topmost ancestor (root)
        return end($ancestors);
    }
}
