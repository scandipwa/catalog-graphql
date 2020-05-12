<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Alfreds Genkins <info@scandipwa.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Plugin;

use Magento\Catalog\Model\Category as CoreCategory;
use  Magento\Catalog\Model\ResourceModel\Category as CoreResourceCategory;
/**
 * Class Category
 */
class Category {
    /**
     * @var CoreResourceCategory
     */
    protected $resource;

    /**
     * Category constructor.
     * @param CoreResourceCategory $resource
     */
    public function __construct(
        CoreResourceCategory $resource
    ) {
        $this->resource = $resource;
    }

    /**
     * @param CoreCategory $category
     * @param callable $next
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundGetProductCount(
        CoreCategory $category,
        callable $next
    ) {
        if (!$category->hasData(CoreCategory::KEY_PRODUCT_COUNT)) {
            $count = $this->resource->getProductCount($category);
            $category->setData(CoreCategory::KEY_PRODUCT_COUNT, $count);
        }

        return $category->getData(CoreCategory::KEY_PRODUCT_COUNT);
    }
}
