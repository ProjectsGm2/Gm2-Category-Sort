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
   After each filter update, the page automatically scrolls back to the selected
   categories list so it's easy to refine choices.

## Sorting

The widget honors the WooCommerce sorting dropdown. Values like `price`,
`price-desc`, `rating`, `popularity`, `date`, `rand`, `id` and `title` are
accepted. You may also append `-asc` or `-desc` to control direction when
applicable. These values are translated to `WP_Query` parameters through the
`gm2_get_orderby_args` helper, so the AJAX output matches the chosen order.

## Sitemap

Administrators can edit any page containing the **GM2 Category Sort** widget and
use the **Generate Sitemap** button found in the widget's settings panel to
create or update the sitemap of category combinations. Alternatively, run
`wp gm2-category-sort sitemap` from WP‑CLI. The file is saved to
`wp-content/uploads/gm2-category-sort-sitemap.xml` and is also regenerated
automatically once per day via WP&nbsp;Cron. Submit this URL to search engines
for indexing.

## CSV Import

Product categories can be created in bulk from a CSV file. Each line should
list category names from top to bottom, separated by commas. For example:

```
Wheel Simulators,By Brand & Model,Ford Wheel Simulators,F350 Wheel Simulators
```

Upload a CSV through **Tools → Import Categories** in the admin area or run
`wp gm2-category-sort import &lt;file&gt;` from WP‑CLI. An example file is
available at `assets/example-categories.csv`.

## SEO Improvements

When active filters are applied, the plugin outputs a canonical link pointing to
the unfiltered shop page. Selected categories are exposed as a JSON‑LD ItemList,
and the page title and meta description automatically include the chosen
category names. The generated sitemap of filter combinations further helps
search engines discover relevant listings.

## Synonyms

Edit any product category and enter comma-separated words in the **Synonyms**
field. Each synonym is rendered as an extra filter link beside the category
name so shoppers can select it directly. These synonyms only appear in the page
content and are not included in the JSON‑LD schema or meta tags.

## Primary Category

Each product category has a **Canonical Category** dropdown. When you assign a
category here, that selection becomes the "Primary Category" used for canonical
URLs. If visitors filter products within this term, the `<link rel="canonical">`
tag points to the chosen primary category rather than the subcategory currently
being viewed.

Apply this option to alternate branches or duplicate categories that should
consolidate SEO signals. Leave the main category unset so other terms can
reference it. For example, if "Steel Wheels" appears under both **By Vehicle**
and **By Brand**, pick one branch as authoritative and set it as the Primary
Category for the other branch. When filters are active, both pages will list the
canonical URL of the designated category.

## Security
AJAX filtering uses a nonce exposed to JavaScript as `gm2CategorySort.nonce`.
If you customize the script, include this value in your requests.

## License
This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.

## Testing

To run the PHPUnit test suite:

1. Install PHPUnit (version 9 or newer).
2. From the project root run:

```bash
phpunit
```

The tests use stubbed WordPress functions so no WordPress installation is required.

