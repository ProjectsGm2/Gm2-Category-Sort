<?php
/**
 * Plugin Name: Gm2 Category Sort
 * Description: ...
 * Version: 1.0.8
 * Author: Your Name
 * Text Domain: gm2-category-sort
 */

defined('ABSPATH') || exit;

// Plugin version used for cache busting
define('GM2_CAT_SORT_VERSION', '1.0.8');

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

    // Load translations
    load_plugin_textdomain( 'gm2-category-sort', false, basename( dirname( __FILE__ ) ) . '/languages' );

    // Include non-widget files
    require_once GM2_CAT_SORT_PATH . 'includes/utilities.php';
    require_once GM2_CAT_SORT_PATH . 'includes/class-enqueuer.php';
    require_once GM2_CAT_SORT_PATH . 'includes/class-query-handler.php';
    require_once GM2_CAT_SORT_PATH . 'includes/class-renderer.php';
    require_once GM2_CAT_SORT_PATH . 'includes/class-ajax.php';
    
    // Initialize components
    Gm2_Category_Sort_Enqueuer::init();
    Gm2_Category_Sort_Query_Handler::init();
    Gm2_Category_Sort_Ajax::init();
    
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
        printf(
            /* translators: 1: plugin name. 2: comma separated list of missing plugins. */
            esc_html__( '%1$s requires the following plugins: %2$s.', 'gm2-category-sort' ),
            '<strong>Gm2 Category Sort</strong>',
            implode( ', ', array_map( 'esc_html', $missing ) )
        );
        echo ' ' . esc_html__( 'Please install and activate them.', 'gm2-category-sort' );
        echo '</p></div>';
    }
}
