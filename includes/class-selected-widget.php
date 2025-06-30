<?php
class Gm2_Selected_Category_Widget extends \Elementor\Widget_Base {
    public function get_name() {
        return 'gm2-selected-category';
    }

    public function get_title() {
        return __( 'GM2 Selected Category', 'gm2-category-sort' );
    }

    public function get_icon() {
        // Reuse the same icon as the main category sort widget.
        return 'eicon-product-categories';
    }

    public function get_categories() {
        return [ 'gm2-widgets' ];
    }

    protected function register_controls() {
        $this->start_controls_section( 'content_section', [
            'label' => __( 'Content', 'gm2-category-sort' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'title', [
            'label'       => __( 'Title', 'gm2-category-sort' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => __( 'Selected Categories', 'gm2-category-sort' ),
            'label_block' => true,
        ] );

        $this->end_controls_section();

        // Title styles.
        $this->start_controls_section( 'title_style_section', [
            'label' => __( 'Title', 'gm2-category-sort' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ] );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name'     => 'title_typography',
                'selector' => '{{WRAPPER}} .gm2-selected-header',
            ]
        );

        $this->add_control( 'title_color', [
            'label' => __( 'Color', 'gm2-category-sort' ),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-selected-header' => 'color: {{VALUE}};',
            ],
        ] );

        $this->end_controls_section();

        // Widget Box styles.
        $this->start_controls_section( 'gm2_widget_box_section', [
            'label' => __( 'Widget Box', 'gm2-category-sort' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ] );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name'     => 'widget_box_bg',
                'selector' => '{{WRAPPER}} .gm2-selected-category-widget',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name'     => 'widget_box_border',
                'selector' => '{{WRAPPER}} .gm2-selected-category-widget',
            ]
        );

        $this->add_responsive_control( 'widget_box_radius', [
            'label' => __( 'Border Radius', 'gm2-category-sort' ),
            'type'  => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .gm2-selected-category-widget' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ] );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'widget_box_shadow',
                'selector' => '{{WRAPPER}} .gm2-selected-category-widget',
            ]
        );

        $this->add_responsive_control( 'widget_box_padding', [
            'label' => __( 'Padding', 'gm2-category-sort' ),
            'type'  => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .gm2-selected-category-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ] );

        $this->add_responsive_control( 'widget_box_margin', [
            'label' => __( 'Margin', 'gm2-category-sort' ),
            'type'  => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .gm2-selected-category-widget' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ] );

        $this->end_controls_section();

        // Selected Categories styles copied from the main widget.
        $this->start_controls_section( 'gm2_selected_section', [
            'label' => __( 'Selected Categories', 'gm2-category-sort' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ] );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name'     => 'selected_typography',
                'selector' => '{{WRAPPER}} .gm2-selected-category',
            ]
        );

        $this->add_control( 'selected_bg', [
            'label' => __( 'Background', 'gm2-category-sort' ),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-selected-category' => 'background-color: {{VALUE}};',
            ],
        ] );

        $this->add_control( 'selected_text', [
            'label' => __( 'Text Color', 'gm2-category-sort' ),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-selected-category' => 'color: {{VALUE}};',
            ],
        ] );

        $this->add_control( 'selected_hover_bg', [
            'label' => __( 'Hover Background', 'gm2-category-sort' ),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-selected-category:hover' => 'background-color: {{VALUE}};',
            ],
        ] );

        $this->add_control( 'selected_hover_color', [
            'label' => __( 'Hover Color', 'gm2-category-sort' ),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-selected-category:hover' => 'color: {{VALUE}};',
            ],
        ] );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name'     => 'selected_border',
                'selector' => '{{WRAPPER}} .gm2-selected-category',
            ]
        );

        $this->add_responsive_control( 'selected_radius', [
            'label' => __( 'Border Radius', 'gm2-category-sort' ),
            'type'  => \Elementor\Controls_Manager::DIMENSIONS,
            'selectors' => [
                '{{WRAPPER}} .gm2-selected-category' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ] );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'selected_shadow',
                'selector' => '{{WRAPPER}} .gm2-selected-category',
            ]
        );

        $this->add_control( 'remove_icon_color', [
            'label' => __( 'Remove Icon Color', 'gm2-category-sort' ),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-remove-category' => 'color: {{VALUE}};',
            ],
        ] );

        $this->add_control( 'remove_icon_hover_color', [
            'label' => __( 'Remove Icon Hover Color', 'gm2-category-sort' ),
            'type'  => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .gm2-remove-category:hover' => 'color: {{VALUE}};',
            ],
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        $settings  = $this->get_settings_for_display();
        $selected  = $this->get_selected_categories();

        $visible  = ! empty( $selected );
        $style    = $visible ? ' style="display:block"' : ' style="display:none"';
        echo '<div class="gm2-selected-category-widget"' . $style . '>'; 
        if ( ! empty( $settings['title'] ) ) {
            echo '<div class="gm2-selected-header">' . esc_html( $settings['title'] ) . '</div>';
        }
        echo '<div class="gm2-selected-categories">';
        foreach ( $selected as $term_id ) {
            $term = get_term( $term_id, 'product_cat' );
            if ( ! $term || is_wp_error( $term ) ) {
                continue;
            }
            echo '<div class="gm2-selected-category" data-term-id="' . esc_attr( $term_id ) . '">';
            echo esc_html( $term->name );
            echo '<span class="gm2-remove-category">✕</span>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }

    private function get_selected_categories() {
        if ( empty( $_GET['gm2_cat'] ) ) {
            return [];
        }
        return array_map( 'intval', explode( ',', $_GET['gm2_cat'] ) );
    }
}

