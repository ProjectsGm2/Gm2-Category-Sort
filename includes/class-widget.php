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

        // Widget Box
        $this->start_controls_section('gm2_widget_box_section', [
            'label' => __('Widget Box', 'gm2-category-sort'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name'     => 'widget_box_bg',
                'selector' => '{{WRAPPER}} .gm2-category-sort',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name'     => 'widget_box_border',
                'selector' => '{{WRAPPER}} .gm2-category-sort',
            ]
        );

        $this->add_responsive_control('widget_box_radius', [
            'label' => __('Border Radius', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .gm2-category-sort' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'widget_box_shadow',
                'selector' => '{{WRAPPER}} .gm2-category-sort',
            ]
        );

        $this->end_controls_section();

        // Layout
        $this->start_controls_section('gm2_layout_section', [
            'label' => __('Layout', 'gm2-category-sort'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('display_mode', [
            'label' => __('Display Mode', 'gm2-category-sort'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'block'  => __('Block', 'gm2-category-sort'),
                'inline' => __('Inline', 'gm2-category-sort'),
            ],
            'default' => 'block',
            'prefix_class' => 'gm2-display-'
        ]);

        $this->add_responsive_control('vertical_spacing', [
            'label' => __('Vertical Spacing', 'gm2-category-sort'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [
                'px' => ['min' => 0, 'max' => 50],
            ],
            'default' => [
                'size' => 5,
                'unit' => 'px',
            ],
            'selectors' => [
                '{{WRAPPER}} .gm2-category-node' => 'margin-bottom: {{SIZE}}{{UNIT}};'
            ],
        ]);

        $this->add_responsive_control('horizontal_spacing', [
            'label' => __('Horizontal Spacing', 'gm2-category-sort'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [
                'px' => ['min' => 0, 'max' => 50],
            ],
            'default' => [
                'size' => 5,
                'unit' => 'px',
            ],
            'selectors' => [
                '{{WRAPPER}}.gm2-display-inline .gm2-category-node' => 'margin-right: {{SIZE}}{{UNIT}};'
            ],
        ]);

        $this->end_controls_section();

        // Category Levels
        for ( $i = 0; $i <= 3; $i++ ) {
            $this->start_controls_section( 'gm2_depth_' . $i . '_section', [
                'label' => sprintf( __( 'Category Level %d', 'gm2-category-sort' ), $i ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ] );

            $this->add_control( 'display_mode_depth_' . $i, [
                'label' => __( 'Display Mode', 'gm2-category-sort' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'block'  => __( 'Block', 'gm2-category-sort' ),
                    'inline' => __( 'Inline', 'gm2-category-sort' ),
                ],
                'default' => 'block',
                'prefix_class' => 'gm2-depth-' . $i . '-display-'
            ] );

            $selector = '.gm2-category-name.depth-' . $i;
            $syn_selector = '.gm2-category-synonym.depth-' . $i;

            $this->add_group_control(
                \Elementor\Group_Control_Typography::get_type(),
                [
                    'name'     => 'depth_' . $i . '_typography',
                    'selector' => '{{WRAPPER}} ' . $selector . ', {{WRAPPER}} ' . $syn_selector,
                ]
            );

            $this->add_control( 'depth_' . $i . '_text_color', [
                'label' => __( 'Text Color', 'gm2-category-sort' ),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} ' . $selector => 'color: {{VALUE}};',
                    '{{WRAPPER}} ' . $syn_selector => 'color: {{VALUE}};',
                ],
            ] );

            $this->add_control( 'depth_' . $i . '_bg_color', [
                'label' => __( 'Background', 'gm2-category-sort' ),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} ' . $selector => 'background-color: {{VALUE}};',
                ],
            ] );

            $this->add_control( 'depth_' . $i . '_hover_color', [
                'label' => __( 'Hover Text Color', 'gm2-category-sort' ),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} ' . $selector . ':hover' => 'color: {{VALUE}};',
                    '{{WRAPPER}} ' . $syn_selector . ':hover' => 'color: {{VALUE}};',
                ],
            ] );

            $this->add_control( 'depth_' . $i . '_hover_bg', [
                'label' => __( 'Hover Background', 'gm2-category-sort' ),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} ' . $selector . ':hover' => 'background-color: {{VALUE}};',
                ],
            ] );

            $this->add_control( 'depth_' . $i . '_active_color', [
                'label' => __( 'Active Text Color', 'gm2-category-sort' ),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} ' . $selector . '.selected' => 'color: {{VALUE}};',
                ],
            ] );

            $this->add_control( 'depth_' . $i . '_active_bg', [
                'label' => __( 'Active Background', 'gm2-category-sort' ),
                'type'  => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} ' . $selector . '.selected' => 'background-color: {{VALUE}};',
                ],
            ] );

            $this->add_group_control(
                \Elementor\Group_Control_Border::get_type(),
                [
                    'name'     => 'depth_' . $i . '_border',
                    'selector' => '{{WRAPPER}} ' . $selector,
                ]
            );

            $this->add_responsive_control( 'depth_' . $i . '_radius', [
                'label' => __( 'Border Radius', 'gm2-category-sort' ),
                'type'  => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} ' . $selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ] );

            $this->add_group_control(
                \Elementor\Group_Control_Box_Shadow::get_type(),
                [
                    'name'     => 'depth_' . $i . '_shadow',
                    'selector' => '{{WRAPPER}} ' . $selector,
                ]
            );

            $this->add_responsive_control( 'depth_' . $i . '_padding', [
                'label' => __( 'Padding', 'gm2-category-sort' ),
                'type'  => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} ' . $selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ] );

            $this->add_responsive_control( 'depth_' . $i . '_margin', [
                'label' => __( 'Margin', 'gm2-category-sort' ),
                'type'  => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} ' . $selector => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ] );

            $this->end_controls_section();
        }

        // Expand/Collapse Icons
        $this->start_controls_section('gm2_expand_section', [
            'label' => __('Expand/Collapse', 'gm2-category-sort'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('expand_icon', [
            'label'   => __('Expand Icon', 'gm2-category-sort'),
            'type'    => \Elementor\Controls_Manager::ICONS,
            'default' => [
                'value'   => 'fas fa-plus',
                'library' => 'fa-solid',
            ],
        ]);

        $this->add_control('collapse_icon', [
            'label'   => __('Collapse Icon', 'gm2-category-sort'),
            'type'    => \Elementor\Controls_Manager::ICONS,
            'default' => [
                'value'   => 'fas fa-minus',
                'library' => 'fa-solid',
            ],
        ]);

        $this->add_responsive_control('expand_icon_size', [
            'label' => __('Icon Size', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [ 'px' => ['min' => 8, 'max' => 60] ],
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button i, {{WRAPPER}} .gm2-expand-button svg, {{WRAPPER}} .gm2-expand-button .gm2-expand-icon' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('expand_icon_hover_size', [
            'label' => __('Icon Size (Hover)', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [ 'px' => ['min' => 8, 'max' => 60] ],
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button:hover i, {{WRAPPER}} .gm2-expand-button:hover svg, {{WRAPPER}} .gm2-expand-button:hover .gm2-expand-icon' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('expand_icon_active_size', [
            'label' => __('Icon Size (Active)', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [ 'px' => ['min' => 8, 'max' => 60] ],
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button.gm2-expanded i, {{WRAPPER}} .gm2-expand-button[data-expanded="true"] i, {{WRAPPER}} .gm2-expand-button.gm2-expanded svg, {{WRAPPER}} .gm2-expand-button[data-expanded="true"] svg, {{WRAPPER}} .gm2-expand-button.gm2-expanded .gm2-expand-icon, {{WRAPPER}} .gm2-expand-button[data-expanded="true"] .gm2-expand-icon' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('expand_icon_margin', [
            'label' => __('Icon Spacing', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button i, {{WRAPPER}} .gm2-expand-button svg, {{WRAPPER}} .gm2-expand-button .gm2-expand-icon' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('expand_icon_bg', [
            'label' => __('Icon Background', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button i, {{WRAPPER}} .gm2-expand-button svg, {{WRAPPER}} .gm2-expand-button .gm2-expand-icon' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('expand_icon_hover_bg', [
            'label' => __('Icon Hover Background', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button:hover i, {{WRAPPER}} .gm2-expand-button:hover svg, {{WRAPPER}} .gm2-expand-button:hover .gm2-expand-icon' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('expand_icon_active_bg', [
            'label' => __('Icon Active Background', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button.gm2-expanded i, {{WRAPPER}} .gm2-expand-button[data-expanded="true"] i, {{WRAPPER}} .gm2-expand-button.gm2-expanded svg, {{WRAPPER}} .gm2-expand-button[data-expanded="true"] svg, {{WRAPPER}} .gm2-expand-button.gm2-expanded .gm2-expand-icon, {{WRAPPER}} .gm2-expand-button[data-expanded="true"] .gm2-expand-icon' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('expand_icon_color', [
            'label' => __('Icon Color', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button i, {{WRAPPER}} .gm2-expand-button .gm2-expand-icon' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('expand_icon_hover_color', [
            'label' => __('Icon Hover Color', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button:hover i, {{WRAPPER}} .gm2-expand-button:hover .gm2-expand-icon' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('expand_icon_active_color', [
            'label' => __('Icon Active Color', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button.gm2-expanded i, {{WRAPPER}} .gm2-expand-button.gm2-expanded .gm2-expand-icon' => 'color: {{VALUE}};',
                '{{WRAPPER}} .gm2-expand-button[data-expanded="true"] i, {{WRAPPER}} .gm2-expand-button[data-expanded="true"] .gm2-expand-icon' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('collapse_icon_size', [
            'label' => __('Collapse Icon Size', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [ 'px' => ['min' => 8, 'max' => 60] ],
            'selectors' => [
                '{{WRAPPER}} .gm2-collapse-icon' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('collapse_icon_hover_size', [
            'label' => __('Collapse Icon Size (Hover)', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [ 'px' => ['min' => 8, 'max' => 60] ],
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button:hover .gm2-collapse-icon' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('collapse_icon_active_size', [
            'label' => __('Collapse Icon Size (Active)', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [ 'px' => ['min' => 8, 'max' => 60] ],
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button.gm2-expanded .gm2-collapse-icon, {{WRAPPER}} .gm2-expand-button[data-expanded="true"] .gm2-collapse-icon' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('collapse_icon_margin', [
            'label' => __('Collapse Icon Spacing', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .gm2-collapse-icon' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('collapse_icon_bg', [
            'label' => __('Collapse Icon Background', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-collapse-icon' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('collapse_icon_hover_bg', [
            'label' => __('Collapse Icon Hover Background', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button:hover .gm2-collapse-icon' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('collapse_icon_active_bg', [
            'label' => __('Collapse Icon Active Background', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button.gm2-expanded .gm2-collapse-icon, {{WRAPPER}} .gm2-expand-button[data-expanded="true"] .gm2-collapse-icon' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('collapse_icon_color', [
            'label' => __('Collapse Icon Color', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-collapse-icon' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('collapse_icon_hover_color', [
            'label' => __('Collapse Icon Hover Color', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button:hover .gm2-collapse-icon' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('collapse_icon_active_color', [
            'label' => __('Collapse Icon Active Color', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button.gm2-expanded .gm2-collapse-icon' => 'color: {{VALUE}};',
                '{{WRAPPER}} .gm2-expand-button[data-expanded="true"] .gm2-collapse-icon' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name'     => 'expand_button_bg',
                'selector' => '{{WRAPPER}} .gm2-expand-button',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name'     => 'expand_button_border',
                'selector' => '{{WRAPPER}} .gm2-expand-button',
            ]
        );

        $this->add_responsive_control('expand_button_radius', [
            'label' => __('Border Radius', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'expand_button_shadow',
                'selector' => '{{WRAPPER}} .gm2-expand-button',
            ]
        );

        $this->add_responsive_control('expand_button_padding', [
            'label' => __('Padding', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('expand_button_margin', [
            'label' => __('Margin', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .gm2-expand-button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        // Selected Categories
        $this->start_controls_section('gm2_selected_section', [
            'label' => __('Selected Categories', 'gm2-category-sort'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name'     => 'selected_typography',
                'selector' => '{{WRAPPER}} .gm2-selected-category',
            ]
        );

        $this->add_control('selected_bg', [
            'label' => __('Background', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-selected-category' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('selected_text', [
            'label' => __('Text Color', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-selected-category' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('selected_hover_bg', [
            'label' => __('Hover Background', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-selected-category:hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('selected_hover_color', [
            'label' => __('Hover Color', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-selected-category:hover' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name'     => 'selected_border',
                'selector' => '{{WRAPPER}} .gm2-selected-category',
            ]
        );

        $this->add_responsive_control('selected_radius', [
            'label' => __('Border Radius', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .gm2-selected-category' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'selected_shadow',
                'selector' => '{{WRAPPER}} .gm2-selected-category',
            ]
        );

        $this->add_control('remove_icon_color', [
            'label' => __('Remove Icon Color', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-remove-category' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('remove_icon_hover_color', [
            'label' => __('Remove Icon Hover Color', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-remove-category:hover' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        // Synonyms
        $this->start_controls_section('gm2_synonyms_section', [
            'label' => __('Synonyms', 'gm2-category-sort'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name'     => 'synonym_typography',
                'selector' => '{{WRAPPER}} .gm2-category-synonym',
            ]
        );

        $this->add_control('synonym_color', [
            'label' => __('Text Color', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-category-synonym, {{WRAPPER}} .gm2-category-synonym:visited' => 'color: {{VALUE}};',
                '{{WRAPPER}} .gm2-synonyms' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('synonym_bg', [
            'label' => __('Background', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-category-synonym' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('synonym_hover_color', [
            'label' => __('Hover Color', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-category-synonym:hover' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('synonym_hover_bg', [
            'label' => __('Hover Background', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-category-synonym:hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name'     => 'synonym_border',
                'selector' => '{{WRAPPER}} .gm2-category-synonym',
            ]
        );

        $this->add_responsive_control('synonym_radius', [
            'label' => __('Border Radius', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .gm2-category-synonym' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'synonym_shadow',
                'selector' => '{{WRAPPER}} .gm2-category-synonym',
            ]
        );

        $this->add_responsive_control('synonym_padding', [
            'label' => __('Padding', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .gm2-category-synonym' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('synonym_margin', [
            'label' => __('Margin', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .gm2-category-synonym' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('synonym_below_spacing', [
            'label' => __('Below Spacing', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [ 'px' => ['min' => 0, 'max' => 50] ],
            'default' => [
                'size' => 5,
                'unit' => 'px',
            ],
            'selectors' => [
                '{{WRAPPER}}' => '--gm2-synonym-below-spacing: {{SIZE}}{{UNIT}};',
            ],
            'condition' => [
                'synonym_position' => 'below',
            ],
        ]);

        $this->add_control('synonym_icon', [
            'label' => __('Icon', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::ICONS,
            'label_block' => true,
        ]);

        $this->add_responsive_control('synonym_icon_size', [
            'label' => __('Icon Size', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [ 'px' => ['min' => 8, 'max' => 60] ],
            'selectors' => [
                '{{WRAPPER}} .gm2-synonym-icon' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('synonym_icon_hover_size', [
            'label' => __('Icon Size (Hover)', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [ 'px' => ['min' => 8, 'max' => 60] ],
            'selectors' => [
                '{{WRAPPER}} .gm2-category-synonym:hover .gm2-synonym-icon' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);
        $this->add_responsive_control('synonym_icon_active_size', [
            'label' => __('Icon Size (Active)', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [ 'px' => ['min' => 8, 'max' => 60] ],
            'selectors' => [
                '{{WRAPPER}} .gm2-category-synonym.selected .gm2-synonym-icon, {{WRAPPER}} .gm2-category-synonym.active .gm2-synonym-icon' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('synonym_icon_margin', [
            'label' => __('Icon Spacing', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .gm2-synonym-icon' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('synonym_icon_bg', [
            'label' => __('Icon Background', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-synonym-icon' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('synonym_icon_hover_bg', [
            'label' => __('Icon Hover Background', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-category-synonym:hover .gm2-synonym-icon' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('synonym_icon_active_bg', [
            'label' => __('Icon Active Background', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-category-synonym.selected .gm2-synonym-icon, {{WRAPPER}} .gm2-category-synonym.active .gm2-synonym-icon' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('synonym_icon_position', [
            'label' => __('Icon Position', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'before' => __('Before', 'gm2-category-sort'),
                'after'  => __('After', 'gm2-category-sort'),
            ],
            'default' => 'before',
        ]);

        $this->add_control('synonym_position', [
            'label' => __('Synonym Position', 'gm2-category-sort'),
            'type'  => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'inline' => __('Inline', 'gm2-category-sort'),
                'below'  => __('Below', 'gm2-category-sort'),
            ],
            'default' => 'inline',
            'prefix_class' => 'gm2-synonym-pos-'
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

        if ( ! empty( $settings['expand_icon'] ) ) {
            \Elementor\Icons_Manager::enqueue_shim( $settings['expand_icon'] );
        }
        if ( ! empty( $settings['collapse_icon'] ) ) {
            \Elementor\Icons_Manager::enqueue_shim( $settings['collapse_icon'] );
        }
        if ( ! empty( $settings['synonym_icon'] ) ) {
            \Elementor\Icons_Manager::enqueue_shim( $settings['synonym_icon'] );
        }

        // Only render on WooCommerce pages
        if (!is_shop() && !is_product_category() && !is_product_taxonomy() && !is_search()) {
            echo '<div class="elementor-alert elementor-alert-info">';
            echo __('GM2 Category Sort only works on WooCommerce archive pages.', 'gm2-category-sort');
            echo '</div>';
            return;
        }
        
        // Pass settings to renderer
        $renderer = new Gm2_Category_Sort_Renderer([
            'parent_categories'    => $settings['parent_categories'],
            'filter_type'          => $settings['filter_type'],
            'simple_operator'      => $settings['simple_operator'],
            'widget_id'            => $this->get_id(),
            'synonym_icon'         => $settings['synonym_icon'],
            'synonym_icon_position'=> $settings['synonym_icon_position'],
            'expand_icon'          => $settings['expand_icon'],
            'collapse_icon'        => $settings['collapse_icon'],
        ]);
        
        echo $renderer->generate_html();
    }
}
