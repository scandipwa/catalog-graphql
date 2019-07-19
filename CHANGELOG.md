# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.4] - 2019-07-19
### Changed
- fixed logic for recognizing attributes (broken in 1.4.0 due to attributes are not auto-imported to the schema)
- fixed a bug when products could not be filtered with custom attribute w/o a category in the same request


## [1.4.1] - 2019-07-08
### Added
- `min_price, max_price` fields for accurate price filter

### Changed
- filterable attributes are added via `di.xml`
- layered navigation (filters) now utilizes the same collection (avoiding loading unnecessary data)

## [1.4.0] - 2019-07-02
### Changed
- attributes are not auto-injected into schema (reduce bootstrap time) 

## [1.0.0] - 2019-03-08
### Added
- custom resolver to `category` field
- type `ProductThumbnails` to `ProductInterface` interface

### Changed
- elasticsearch page size
- `category` field input options with `url_path`
- `products` field input options with:
- - Configurable products attributes (`color`, `size`, `shoes_size`)
- - Category URL (`category_url_key`, `category_url_path`)
