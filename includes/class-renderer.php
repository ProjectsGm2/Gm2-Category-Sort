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
             data-simple-operator="<?= esc_attr($this->settings['simple_operator'] ?? 'IN') ?>"
             data-columns="<?= esc_attr(wc_get_loop_prop('columns')) ?>"
             data-per-page="<?= esc_attr(wc_get_loop_prop('per_page')) ?>">
             
            <nav class="gm2-category-tree">
                <?php $this->render_category_tree(); ?>
            </nav>
            
            <?php
            $has_selected = !empty($this->selected_categories);
            $style = $has_selected ? '' : 'style="display:none"';
            ?>
            <div class="gm2-selected-header" <?= $style ?>>
                <?= __('Selected Categories:', 'gm2-category-sort') ?>
            </div>
            <div class="gm2-selected-categories" <?= $style ?>>
                <?php if ($has_selected) $this->render_selected_categories(); ?>
            </div>
            <?php if (current_user_can('manage_options')) : ?>
                <div class="gm2-sitemap-tools">
                    <button type="button" class="gm2-generate-sitemap" data-nonce="<?= wp_create_nonce('gm2_generate_sitemap') ?>">
                        <?= esc_html__('Generate Sitemap', 'gm2-category-sort') ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_category_tree() {
        $roots = $this->get_root_categories();

        if ( class_exists( 'Gm2_Category_Sort_Schema' ) ) {
            Gm2_Category_Sort_Schema::set_categories( $roots );
        }

        if (empty($roots)) {
            echo '<div class="gm2-no-categories">';
            echo __('No categories found.', 'gm2-category-sort');
            echo '</div>';
            return;
        }
        
        echo '<ul class="gm2-parent-categories-container">';
        foreach ($roots as $root) {
            if (is_wp_error($root) || !is_object($root)) continue;

            $this->render_category_node($root, 0);
        }
        echo '</ul>';
    }
    
    private function render_category_node($term, $depth) {
        $children = get_terms([
            'taxonomy' => 'product_cat',
            'parent' => $term->term_id,
            'hide_empty' => false,
            'orderby' => 'name'
        ]);
        foreach ($children as $child) {
            if (is_wp_error($child) || !is_object($child)) {
                continue;
            }
            $child->gm2_synonyms = $this->fetch_synonyms($child->term_id);
        }
        
        $has_children = !empty($children) && !is_wp_error($children);
        $is_selected = in_array($term->term_id, $this->selected_categories);
        $selected_class = $is_selected ? 'selected' : '';
        
        echo '<li class="gm2-category-node depth-' . intval( $depth ) . '">';
        echo '<div class="gm2-category-header">';
        
        // Add indentation for child nodes
        $indent = str_repeat('<span class="gm2-indent"></span>', $depth);
        echo $indent;
        
        echo '<div class="gm2-category-name-container">';
        $href = add_query_arg([
            'gm2_cat' => $term->term_id,
            'gm2_filter_type' => $this->settings['filter_type'],
            'gm2_simple_operator' => $this->settings['simple_operator'] ?? 'IN',
        ]);
        echo '<a class="gm2-category-name depth-' . intval( $depth ) . ' ' . $selected_class . '" data-term-id="' . esc_attr($term->term_id) . '" href="' . esc_url($href) . '">' . esc_html($term->name) . '</a>';

        $synonyms = isset($term->gm2_synonyms) ? $term->gm2_synonyms : array_filter(array_map('trim', explode(',', (string) get_term_meta($term->term_id, 'gm2_synonyms', true))));
        if (!empty($synonyms)) {
            echo '<div class="gm2-synonyms-container"><span class="gm2-synonyms">(';
            $first = true;
            foreach ($synonyms as $syn) {
                if (!$syn) continue;
                if (!$first) {
                    echo ', ';
                }
                $href = add_query_arg([
                    'gm2_cat' => $term->term_id,
                    'gm2_filter_type' => $this->settings['filter_type'],
                    'gm2_simple_operator' => $this->settings['simple_operator'] ?? 'IN',
                ]);
                $icon_html = '';
                if ( ! empty( $this->settings['synonym_icon']['value'] ) ) {
                      $icon_html = \Elementor\Icons_Manager::render_icon( $this->settings['synonym_icon'], [ 'aria-hidden' => 'true', 'class' => 'gm2-synonym-icon' ] );
                    $icon_html = \Elementor\Icons_Manager::try_get_icon_html(
                        $this->settings['synonym_icon'],
                        [ 'aria-hidden' => 'true', 'class' => 'gm2-synonym-icon' ]
                    );
                }
                if ( ! empty( $icon_html ) && $this->settings['synonym_icon_position'] === 'after' ) {
                    $link_content = esc_html($syn) . $icon_html;
                } elseif ( ! empty( $icon_html ) ) {
                    $link_content = $icon_html . esc_html($syn);
                } else {
                    $link_content = esc_html($syn);
                }
                echo '<a class="gm2-category-synonym depth-' . intval( $depth ) . '" data-term-id="' . esc_attr($term->term_id) . '" href="' . esc_url($href) . '">' . $link_content . '</a>';
                $first = false;
            }
            echo ')</span></div>';
        }
        
        if ($has_children) {
            $expand_class   = ! empty( $this->settings['expand_icon']['value'] ) ? $this->settings['expand_icon']['value'] : '';
            $collapse_class = ! empty( $this->settings['collapse_icon']['value'] ) ? $this->settings['collapse_icon']['value'] : '';

            if ( $expand_class ) {
                $icon_markup  = \Elementor\Icons_Manager::try_get_icon_html(
                    $this->settings['expand_icon'],
                    [ 'aria-hidden' => 'true' ],
                    null,
                    null,
                    false
                );
                $expand_html  = '<span class="gm2-expand-icon">' . $icon_markup . '</span>';
            } else {
                $expand_html = '<span class="gm2-expand-icon" aria-hidden="true">+</span>';
            }

            if ( $collapse_class ) {
                $icon_markup   = \Elementor\Icons_Manager::try_get_icon_html(
                    $this->settings['collapse_icon'],
                    [ 'aria-hidden' => 'true' ],
                    null,
                    null,
                    false
                );
                $collapse_html = '<span class="gm2-collapse-icon" style="display:none;">' . $icon_markup . '</span>';
            } else {
                $collapse_html = '<span class="gm2-collapse-icon" style="display:none;" aria-hidden="true">-</span>';
            }

            echo '<button class="gm2-expand-button" data-expanded="false" data-expand-class="' . esc_attr( $expand_class ) . '" data-collapse-class="' . esc_attr( $collapse_class ) . '">' . $expand_html . $collapse_html . '</button>';
        }
        echo '</div>';
        echo '</div>';
        
        if ($has_children) {
            // Only show immediate children, grandchildren should be hidden
            echo '<ul class="gm2-child-categories" style="display:none">';
            foreach ($children as $child) {
                // Render child node at next depth level
                $this->render_category_node($child, $depth + 1);
            }
            echo '</ul>';
        }
        echo '</li>';
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
    
    private function fetch_synonyms($term_id) {
        $raw = (string) get_term_meta($term_id, 'gm2_synonyms', true);
        return array_filter(array_map('trim', explode(',', $raw)));
    }

    private function get_root_categories() {
        $terms = [];
        // On category pages, use the current category
        if (is_product_category()) {
            $current = get_queried_object();
            if ($current && !is_wp_error($current)) {
                $terms = [$current];
            }
        } elseif (!empty($this->settings['parent_categories'])) {
            // On shop/search pages, use selected parent categories
            $terms = get_terms([
                'taxonomy' => 'product_cat',
                'include' => $this->settings['parent_categories'],
                'hide_empty' => false,
                'orderby' => 'include'
            ]);
        } else {
            // Default: show all top-level categories
            $terms = get_terms([
                'taxonomy' => 'product_cat',
                'parent' => 0,
                'hide_empty' => false
            ]);
        }

        foreach ($terms as $term) {
            if (is_wp_error($term) || !is_object($term)) {
                continue;
            }
            $term->gm2_synonyms = $this->fetch_synonyms($term->term_id);
        }

        return $terms;
    }
    
    private function get_selected_categories() {
        if (!empty($_GET['gm2_cat'])) {
            return array_map('intval', explode(',', $_GET['gm2_cat']));
        }
        return [];
    }
}
