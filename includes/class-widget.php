<?php
class Gm2_Category_Sort_Widget extends \Elementor\Widget_Base {
    
    public function get_name() {
        return 'gm2-category-sort';
    }
    
    public function get_title() {
        return __('GM2 Category Sort', 'gm2-category-sort');
    }
    
    public function get_icon() {
        return 'eicon-product-categories';
    }
    
    public function get_categories() {
        return ['gm2-widgets'];
    }
    
    protected function register_controls() {
        // Settings for Shop/Search pages
        $this->start_controls_section('content_section', [
            'label' => __('Settings', 'gm2-category-sort'),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);
        
        $this->add_control('parent_categories', [
            'label' => __('Parent Categories', 'gm2-category-sort'),
            'type' => \Elementor\Controls_Manager::SELECT2,
            'label_block' => true,
            'multiple' => true,
            'options' => $this->get_product_categories(),
            'description' => __('Applies to Shop and Search pages only', 'gm2-category-sort'),
        ]);
        
        $this->add_control('filter_type', [
            'label' => __('Filter Logic', 'gm2-category-sort'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'simple',
            'options' => [
                'simple' => __('Simple (AND/OR for all categories)', 'gm2-category-sort'),
                'advanced' => __('Advanced (AND between groups, OR within groups)', 'gm2-category-sort'),
            ],
            'description' => __('Advanced: Categories from different groups use AND, categories in same group use OR', 'gm2-category-sort'),
        ]);
        
        $this->add_control('simple_operator', [
            'label' => __('Simple Logic', 'gm2-category-sort'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'IN',
            'options' => [
                'IN' => __('OR - Show products in any selected category', 'gm2-category-sort'),
                'AND' => __('AND - Show products in all selected categories', 'gm2-category-sort'),
            ],
            'condition' => [
                'filter_type' => 'simple',
            ],
        ]);
        
        $this->end_controls_section();
        
        // Style Section
        $this->start_controls_section('style_section', [
            'label' => __('Style', 'gm2-category-sort'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ]);
        
        $this->add_control('parent_category_color', [
            'label' => __('Parent Category Color', 'gm2-category-sort'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '#333333',
            'selectors' => [
                '{{WRAPPER}} .gm2-parent-category' => 'color: {{VALUE}};',
            ],
        ]);
        
        $this->add_control('child_category_color', [
            'label' => __('Child Category Color', 'gm2-category-sort'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '#666666',
            'selectors' => [
                '{{WRAPPER}} .gm2-category-item' => 'color: {{VALUE}};',
            ],
        ]);
        
        $this->add_control('selected_color', [
            'label' => __('Selected Color', 'gm2-category-sort'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '#3a8bff',
            'selectors' => [
                '{{WRAPPER}} .gm2-category-item.selected' => 'color: {{VALUE}}; font-weight: bold;',
            ],
        ]);
        
        $this->end_controls_section();

        if ( current_user_can( 'manage_options' ) ) {
            $this->start_controls_section( 'gm2_tools_section', [
                'label' => __( 'Tools', 'gm2-category-sort' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ] );

            $this->add_control( 'gm2_generate_sitemap', [
                'type'            => \Elementor\Controls_Manager::RAW_HTML,
                'raw'             => '<button type="button" class="gm2-generate-sitemap button" data-nonce="' . esc_attr( wp_create_nonce( 'gm2_generate_sitemap' ) ) . '">' . esc_html__( 'Generate Sitemap', 'gm2-category-sort' ) . '</button>',
                'content_classes' => 'gm2-sitemap-tools',
                'label_block'     => false,
            ] );

            $this->end_controls_section();
        }
    }
    
    private function get_product_categories() {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => 0
        ]);
        
        $options = [];
        foreach ($categories as $category) {
            $options[$category->term_id] = $category->name;
        }
        return $options;
    }
    
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Only render on WooCommerce pages
        if (!is_shop() && !is_product_category() && !is_product_taxonomy() && !is_search()) {
            echo '<div class="elementor-alert elementor-alert-info">';
            echo __('GM2 Category Sort only works on WooCommerce archive pages.', 'gm2-category-sort');
            echo '</div>';
            return;
        }
        
        // Pass settings to renderer
        $renderer = new Gm2_Category_Sort_Renderer([
            'parent_categories' => $settings['parent_categories'],
            'filter_type' => $settings['filter_type'],
            'simple_operator' => $settings['simple_operator'],
            'widget_id' => $this->get_id()
        ]);
        
        echo $renderer->generate_html();
    }
}
