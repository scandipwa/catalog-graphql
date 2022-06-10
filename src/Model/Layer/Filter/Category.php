<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/module-customer-graph-ql
 * @link https://github.com/scandipwa/module-customer-graph-ql
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Layer\Filter;

use Magento\CatalogGraphQl\DataProvider\CategoryAttributesMapper;
use Magento\CatalogGraphQl\DataProvider\Category\Query\CategoryAttributeQuery;
use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\Builder\Aggregations\Category\IncludeDirectChildrenOnly;
use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\RootCategoryProvider;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\Formatter\LayerFormatter;
use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\Builder\Category as OriginalCategoryBuilder;
use ScandiPWA\CatalogGraphQl\Model\Layer\AttributeDataProvider;

/**
 * @inheritdoc
 */
class Category extends OriginalCategoryBuilder
{
    private static $CATEGORY_ATTRIBUTE_CODE = 'category_ids';

    /**
     * @var AttributeDataProvider
     */
    private $attributeDataProvider;

    /**
     * @param CategoryAttributeQuery $categoryAttributeQuery
     * @param CategoryAttributesMapper $attributesMapper
     * @param RootCategoryProvider $rootCategoryProvider
     * @param ResourceConnection $resourceConnection
     * @param LayerFormatter $layerFormatter
     * @param IncludeDirectChildrenOnly $includeDirectChildrenOnly
     */
    public function __construct(
        CategoryAttributeQuery $categoryAttributeQuery,
        CategoryAttributesMapper $attributesMapper,
        RootCategoryProvider $rootCategoryProvider,
        ResourceConnection $resourceConnection,
        LayerFormatter $layerFormatter,
        IncludeDirectChildrenOnly $includeDirectChildrenOnly,
        AttributeDataProvider $attributeDataProvider
    ) {
        parent::__construct(
          $categoryAttributeQuery,
          $attributesMapper,
          $rootCategoryProvider,
          $resourceConnection,
          $layerFormatter,
          $includeDirectChildrenOnly
        );

        $this->attributeDataProvider = $attributeDataProvider;
    }

    /**
     * @inheritdoc
     */
    public function build(AggregationInterface $aggregation, ?int $storeId): array
    {
        $result = parent::build($aggregation, $storeId);

        // Localize value of the category attribute
        if(count($result) > 0){
            $attributeData = $this->attributeDataProvider->getAttributeData(self::$CATEGORY_ATTRIBUTE_CODE, $storeId);
            $attributeLabel = $attributeData['attribute_store_label'] ?? $attributeData['frontend_label'];

            $result[0]['label'] = $attributeLabel;
        }

        return $result;
    }
}
