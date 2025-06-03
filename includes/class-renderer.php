<?php
class Gm2_Category_Sort_Renderer {
    
    private $settings;
    private $selected_categories = [];
    
    public function __construct($settings) {
        $this->settings = $settings;
        $this->selected_categories = $this->get_selected_categories();
    }
    
    public function generate_html() {
        ob_start();
        ?>
        <div class="gm2-category-sort" 
             data-widget-id="<?= esc_attr($this->settings['widget_id']) ?>" 
             data-filter-type="<?= esc_attr($this->settings['filter_type']) ?>"
             data-simple-operator="<?= esc_attr($this->settings['simple_operator'] ?? 'IN') ?>">
             
            <div class="gm2-category-tree">
                <?php $this->render_category_tree(); ?>
            </div>
            
            <?php if (!empty($this->selected_categories)) : ?>
            <div class="gm2-selected-header">
                <?= __('Selected Categories:', 'gm2-category-sort') ?>
            </div>
            <div class="gm2-selected-categories">
                <?php $this->render_selected_categories(); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_category_tree() {
        $roots = $this->get_root_categories();
        
        if (empty($roots)) {
            echo '<div class="gm2-no-categories">';
            echo __('No categories found.', 'gm2-category-sort');
            echo '</div>';
            return;
        }
        
        echo '<div class="gm2-parent-categories-container">';
        foreach ($roots as $root) {
            if (is_wp_error($root) || !is_object($root)) continue;
            
            $this->render_category_node($root, 0);
        }
        echo '</div>';
    }
    
    private function render_category_node($term, $depth) {
        $children = get_terms([
            'taxonomy' => 'product_cat',
            'parent' => $term->term_id,
            'hide_empty' => false,
            'orderby' => 'name'
        ]);
        
        $has_children = !empty($children) && !is_wp_error($children);
        $is_selected = in_array($term->term_id, $this->selected_categories);
        $selected_class = $is_selected ? 'selected' : '';
        
        echo '<div class="gm2-category-node">';
        echo '<div class="gm2-category-header">';
        
        // Add indentation for child nodes
        $indent = str_repeat('<span class="gm2-indent"></span>', $depth);
        echo $indent;
        
        echo '<div class="gm2-category-name-container">';
        echo '<div class="gm2-category-name ' . $selected_class . '" data-term-id="' . esc_attr($term->term_id) . '">' . esc_html($term->name) . '</div>';
        
        if ($has_children) {
            echo '<button class="gm2-expand-button" data-expanded="false">+</button>';
        }
        echo '</div>';
        echo '</div>';
        
        if ($has_children) {
            // Only show immediate children, grandchildren should be hidden
            echo '<div class="gm2-child-categories" style="display:none">';
            foreach ($children as $child) {
                // Render child node at next depth level
                $this->render_category_node($child, $depth + 1);
            }
            echo '</div>';
        }
        echo '</div>';
    }
    
    private function render_selected_categories() {
        foreach ($this->selected_categories as $term_id) {
            $term = get_term($term_id);
            if (!$term || is_wp_error($term)) continue;
            
            echo '<div class="gm2-selected-category" data-term-id="' . $term_id . '">';
            echo esc_html($term->name);
            echo '<span class="gm2-remove-category">✕</span>';
            echo '</div>';
        }
    }
    
    private function get_root_categories() {
        // On category pages, use the current category
        if (is_product_category()) {
            $current = get_queried_object();
            return [$current];
        }
        
        // On shop/search pages, use selected parent categories
        if (!empty($this->settings['parent_categories'])) {
            return get_terms([
                'taxonomy' => 'product_cat',
                'include' => $this->settings['parent_categories'],
                'hide_empty' => false,
                'orderby' => 'include'
            ]);
        }
        
        // Default: show all top-level categories
        return get_terms([
            'taxonomy' => 'product_cat',
            'parent' => 0,
            'hide_empty' => false
        ]);
    }
    
    private function get_selected_categories() {
        if (!empty($_GET['gm2_cat'])) {
            return array_map('intval', explode(',', $_GET['gm2_cat']));
        }
        return [];
    }
}