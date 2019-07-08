# Scandiweb_CatalogGraphQl

This module extends Magento 2 Catalog GraphQl definitions.

## What is inside?

1. Fixes the `pageSizeBySearchEngine` argument in `PageSizeProvider` class. See more in [di.xml](./src/etc/di.xml)

2. Adds following fields to `ProductFilterInput`:

    - `category_url_key`, 

    - `category_url_path`,

    - `color`, 

    - `size`, 

    - `shoes_size`.

3. Defines custom resolver to get configurable products when filtering by custom attribute.

4. Defines custom resolver to filter products by category URL key or path.

5. Fixes the Layered Navigation when filtering by category.

6. Adds `thumbnail` to `MediaGalleryEntry` type. See more in [schema.graphqls](./src/etc/schema.graphqls).
 
7.  Adds support for product list `min_price` and `max_price` values.
