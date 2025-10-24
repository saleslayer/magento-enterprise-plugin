# Changelog

## [2.7.5] - 2025-10-24

### Changed

- Compatibility with MG version 2.4.8-p2 and PHP 8.4.
- Clear cache for modified items only.

### Tested

- Tested on Magento 2.4.8-p2 / PHP 8.4.13

## [2.7.4] - 2025-06-27

### Changed

- Compatibility with MG version 2.4.8-p1 (PHP 8.3).

### Tested

- Tested on Magento 2.4.8-p1 / PHP 8.3.16

## [2.7.3] - 2025-02-07

### Changed

- Fix if image roles are not defined
- Stock update function modificated.
- Compatibility with MG version 2.4.7-p3

### Tested

- Tested on Magento 2.4.7-p3 / PHP 8.3.16

## [2.7.2] - 2024-09-03

### Added

- Added categories and products URL Key field to process.
- Added categories position field to process.
- Added multiple select fields to process.
- Added automatic incremental position to images during their processing.
- Added image roles field to process.
- Added FAQ page.
- Added note to configurable atributes set in product tab, in connector configuration.
- Added Analytics process to send data to SL API.

### Changed

- Fix for strip_tags with null values
- SL Logs Helper class added.
- Solved bug where additional images weren't beign processed.
- Fix when updating an existing image with same name but different content.

## [2.7.1] - 2024-04-26

### Added

- Configuration option to choose if variant ID are added to their name.
- Column in connector's grid to show items to sync recount and if it has happened, the last cron error.

### Changed

- Attributes read by attribute set id and stored in memory for validation process.
- Minor changes.

## [2.7.0] - 2023-10-18

## Changed

- Folder structure to be compatible with composer.

## [2.6.2] - 2023-07-24

### Added

- Function to obtain a non-used category URL Key when synchronizing category data on each store.

### Changed

- Auto Sync correction for connectors configured with 24h or more.
- Pagination improvements.
- Minor fixes.

## [2.6.1] - 2023-02-17

### Added

- API version and number of items for pagination options.
- Pagination compatibility when reading from Sales Layer API.

### Changed

- SalesLayer-Conn class version updated to 1.36.
- Minor fixes.

### Tested

- Tested on Magento 2.4.3 / PHP 7.4 

## [2.6.0] - 2023-01-19

### Changed

- Compatibility with MG version 2.4.5-p1 (PHP8).
- Attributes synchronization optimization.

## [2.5.5] - 2022-10-03

### Changed

- Optimized index names when processing variants as products.
- Minor fixes.

## [2.5.4] - 2022-08-03

### Changed

- Optimized category name assignation process.
- Minor fixes.

## [2.5.3] - 2022-06-30

### Changed

- Show table queries modified.

## [2.5.2] - 2022-03-31

### Added

- Is Anchor category field to process.

### Changed

- Minor fixes.

## [2.5.1] - 2022-03-18

### Added

- Layout category field to process.

### Changed

- Minor fixes.
