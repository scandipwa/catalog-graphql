<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Category\DataProvider;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\CatalogGraphQl\Model\Resolver\Category\DataProvider\Breadcrumbs as CoreBreadcrumbs;

/**
 * Breadcrumbs data provider
 */
class Breadcrumbs extends CoreBreadcrumbs
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($collectionFactory);
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Get breadcrumbs data
     *
     * @param string $categoryPath
     * @return array
     * @throws LocalizedException
     */
    public function getData(string $categoryPath): array
    {
        $breadcrumbsData = [];

        $pathCategoryIds = explode('/', $categoryPath);
        $parentCategoryIds = array_slice($pathCategoryIds, 2, -1);

        if (count($parentCategoryIds)) {
            $collection = $this->collectionFactory->create();
            $collection->addAttributeToSelect(['name', 'url_key', 'url_path', 'is_active']);
            $collection->addAttributeToFilter('entity_id', $parentCategoryIds);

            foreach ($collection as $category) {
                $breadcrumbsData[] = [
                    'category_id' => $category->getId(),
                    'category_name' => $category->getName(),
                    'category_level' => $category->getLevel(),
                    'category_url_key' => $category->getUrlKey(),
                    'category_url_path' => $category->getUrlPath(),
                    // the only change to fix breadcrumbs
                    'category_url' => parse_url($category->getUrl(), PHP_URL_PATH),
                    'category_is_active' => (bool) $category->getIsActive()
                ];
            }
        }
        return $breadcrumbsData;
    }
}
