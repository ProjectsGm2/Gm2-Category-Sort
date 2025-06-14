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

Synonyms can be specified by appending them in parentheses after a category
name within the same cell. If the synonyms contain commas, wrap the cell in
quotes:

```
"Wheel Covers (hubcaps,wheel caps)",Accessories
```
These values are stored in the category's **Synonyms** field during import.

## Assign Product Categories

You can also bulk assign existing categories to products. Each CSV row should
begin with the product SKU followed by one or more category names:

```
SKU123,Accessories
SKU124,Wheel Simulators,By Brand & Model
```

Upload a CSV through **Tools → Assign Product Categories** or run
`wp gm2-category-sort assign-categories <file> --overwrite` to replace existing
categories (omit `--overwrite` to append). An example file is available at
`assets/example-product-categories.csv`.

When uploading through the admin screen a progress bar shows the import status
so large files can be processed incrementally. Each request handles about 50
rows. For huge imports consider WP‑CLI which displays a terminal progress bar.

### Generate Category Assignments from Research CSV

The `scripts/generate_product_categories.py` helper reads
`Research/wc-products.csv` and the category tree at
`Research/Category Tree With Synonyms-Auto Enhance-13 JUN - Category Tree With Synonyms-Auto Enhance-13 JUN.csv`.
It builds a mapping of category names and their synonyms, scans the product
data for matches and writes a CSV suitable for the **Assign Product Categories**
importer.

Run the script from the project root:

```bash
python3 scripts/generate_product_categories.py \
  --products Research/wc-products.csv \
  --categories "Research/Category Tree With Synonyms-Auto Enhance-13 JUN - Category Tree With Synonyms-Auto Enhance-13 JUN.csv" \
  --output product-categories.csv
```

The resulting `product-categories.csv` follows the same structure as
`assets/example-product-categories.csv` where the first column contains the SKU
and subsequent columns list category names.

Some rows in `Research/wc-products.csv` contain long HTML descriptions with line
breaks. Make sure to parse this file using a CSV library that supports quoted
fields spanning multiple lines and large field sizes. With Python you can open
the file using `newline=''` and call `csv.field_size_limit(sys.maxsize)` or use
`pandas.read_csv` so the description column is read correctly.

WooCommerce exports sometimes prefix the first column header with a UTF-8 byte
order mark. The helper script and importers detect this marker and strip it so
`SKU` columns work as expected. If you process the CSV yourself be sure to
remove the BOM before reading the headers.

## Auto Assign Categories

The plugin can analyze existing products and automatically assign categories
based on their titles, descriptions and attribute values. Run the tool from
**Tools → Auto Assign Categories** in the admin area and choose whether to
**Add categories** or **Overwrite categories** before clicking **Start Auto
Assign**. As each product is processed it appears in the log window:

```
SKU123 - Sample Product => Accessories, Wheel Covers
SKU124 - Another Item => Wheel Simulators
Auto assign complete.
```

For large catalogs the same process is available from WP‑CLI and displays a
progress bar:

```bash
$ wp gm2-category-sort auto-assign
Assigning categories  20/20 (100%)
Success: Auto assign complete.
```
Add `--overwrite` to replace existing categories instead of appending.

During analysis common negative phrases such as `not for`, `does not fit` or
`without` are detected and prevent category matches. The tool also performs
basic stemming so minor wording differences like `lugs` vs `lug`,
`holes`/`hh` vs `hole` still map to the correct terms.

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
Synonyms may also be imported via CSV using the `(syn1,syn2)` syntax described
above.

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

## Browser Compatibility

Gm2 Category Sort targets the latest versions of modern browsers and is
regularly tested with:

- **Chrome** – current and previous release
- **Firefox** – current and previous release
- **Safari** – version&nbsp;13 and newer
- **Edge** – version&nbsp;83 and newer

The frontend script relies on the `URL` API and `history.replaceState`. For
older browsers that lack these features the plugin enqueues
[url-polyfill](https://github.com/lifaon74/url-polyfill) automatically. If you
override script loading, ensure a compatible polyfill is present so filtering
continues to work.

## License
This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.

## Testing

To run the test suite:

1. Install PHP 7.3 or newer and ensure it is available in your `$PATH`. The
   `bin/install-phpunit.sh` script requires PHP, so it **must** be installed
   before running the script. On local setups you can install PHP with
   `apt-get install php` or your package manager.
2. Execute `bin/install-phpunit.sh` to download PHPUnit. If you have Composer
   installed you may alternatively run `composer install` which will fetch
   PHPUnit automatically using the provided `composer.json`.
3. From the project root run:

```bash
vendor/bin/phpunit
```

The tests use stubbed WordPress functions so no WordPress installation is required.


## Build

The frontend script is written with modern JavaScript syntax. To generate an ES5
compatible build for legacy browsers run:

```bash
npm install
npm run build
```

This uses Babel to transpile `assets/js/frontend.js` into
`assets/dist/frontend.js`. The plugin automatically enqueues this file instead of
the original when an outdated browser (like Internet Explorer) is detected.

