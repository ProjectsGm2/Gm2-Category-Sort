<?php
/**
 * Plugin Name: Gm2 Category Sort
 * Description: Adds category sorting widget to Elementor for WooCommerce archives.
 * Version: 1.0.1
 * Author: Your Name
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('GM2_CAT_SORT_PATH', plugin_dir_path(__FILE__));
define('GM2_CAT_SORT_URL', plugin_dir_url(__FILE__));

// Initialize plugin
add_action('plugins_loaded', 'gm2_category_sort_init');
function gm2_category_sort_init() {
    // Check for required plugins
    if (!did_action('elementor/loaded') || !function_exists('WC')) {
        add_action('admin_notices', 'gm2_category_sort_admin_notice');
        return;
    }
    
    // Include non-widget files
    require_once GM2_CAT_SORT_PATH . 'includes/class-enqueuer.php';
    require_once GM2_CAT_SORT_PATH . 'includes/class-query-handler.php';
    require_once GM2_CAT_SORT_PATH . 'includes/class-renderer.php';
    
    // Initialize components
    Gm2_Category_Sort_Enqueuer::init();
    Gm2_Category_Sort_Query_Handler::init();
    
    // Register widget after Elementor is fully loaded
    add_action('elementor/widgets/register', 'gm2_register_widget');
}

// Register widget callback
function gm2_register_widget($widgets_manager) {
    require_once GM2_CAT_SORT_PATH . 'includes/class-widget.php';
    $widgets_manager->register(new Gm2_Category_Sort_Widget());
}

// Add custom widget category
add_action('elementor/elements/categories_registered', 'add_gm2_widget_category');
function add_gm2_widget_category($elements_manager) {
    $elements_manager->add_category('gm2-widgets', [
        'title' => __('GM2 Widgets', 'gm2-category-sort'),
        'icon' => 'fa fa-filter',
    ]);
}

// Admin notice for missing dependencies
function gm2_category_sort_admin_notice() {
    $missing = [];
    if (!did_action('elementor/loaded')) $missing[] = 'Elementor';
    if (!function_exists('WC')) $missing[] = 'WooCommerce';
    
    if (!empty($missing)) {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Gm2 Category Sort</strong> requires the following plugins: ';
        echo implode(', ', $missing) . '. Please install and activate them.';
        echo '</p></div>';
    }
}