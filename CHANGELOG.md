# Changelog

## [Unreleased]

### Added
- Reset search button on the Auto Assign Categories page to clear previous results.
- Branch-rule keywords are evaluated for each matched category path.
- Branch CSV files are now generated for leaf categories so branch rules work for every category.
- Branch rule slugs now include the full category path so rules apply to deep branches.
- Export and import WooCommerce products via CSV including assigned categories.
### Fixed
- Product CSV export no longer reports WooCommerce missing when the WC_ABSPATH constant is undefined.

## [1.0.15] - 2025-06-15
### Added
- Icon size, spacing and background controls for expand, collapse and synonym icons.
- CSS variables provide default styling for these icons.
- Added root-level CSS variables for icon size, spacing and background settings.
### Changed
- Expanded documentation on styling options including widget box, per-depth layout and icon settings.

### Migration Notes
- Existing pages may display slightly different icon sizes. Adjust the new controls in Elementor if needed.
