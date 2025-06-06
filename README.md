# Gm2 Category Sort

Gm2 Category Sort adds a product category sorting widget for WooCommerce shops when using Elementor. Visitors can filter products by category through a collapsible tree of categories.

## Requirements
- PHP 7.0 or higher
- WordPress 5.0 or higher
- [WooCommerce](https://woocommerce.com/)
- [Elementor](https://elementor.com/)

## Installation
1. Download or clone this repository into the `wp-content/plugins` directory of your WordPress installation.
2. Make sure WooCommerce and Elementor are installed and activated.
3. Activate **Gm2 Category Sort** from the **Plugins** screen in WordPress.

## Usage
1. Edit a WooCommerce shop or archive page with Elementor.
2. Search for the **GM2 Category Sort** widget and drag it into your layout.
3. Choose optional parent categories and select the filter logic (Simple or Advanced) in the widget settings.
4. Save the page. On the frontend, shoppers can expand categories and filter the product list.

## Sorting

The widget honors the WooCommerce sorting dropdown. Values like `price`,
`price-desc`, `rating`, `popularity`, `date`, `rand`, `id` and `title` are
accepted. You may also append `-asc` or `-desc` to control direction when
applicable. These values are translated to `WP_Query` parameters through the
`gm2_get_orderby_args` helper, so the AJAX output matches the chosen order.

## Security
AJAX filtering uses a nonce exposed to JavaScript as `gm2CategorySort.nonce`.
If you customize the script, include this value in your requests.

## License
This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.
