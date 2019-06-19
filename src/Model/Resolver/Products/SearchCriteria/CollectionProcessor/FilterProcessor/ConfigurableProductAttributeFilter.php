<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Viktors Pliska <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\SearchCriteria\CollectionProcessor\FilterProcessor;

use Magento\Framework\Api\SearchCriteria\CollectionProcessor\FilterProcessor\CustomFilterInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Api\Filter;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Framework\Registry;

/**
 * Category filter allows to filter products collection using custom defined filters from search criteria.
 */
class ConfigurableProductAttributeFilter implements CustomFilterInterface
{
    protected $configurable;
    protected $collectionFactory;
    protected $registry;

    public function __construct(
        Configurable $configurable,
        CollectionFactory $collectionFactory,
        Registry $registry
    ) {
        $this->registry = $registry;
        $this->configurable = $configurable;
        $this->collectionFactory = $collectionFactory;
    }

    public function apply(Filter $filter, AbstractDb $collection)
    {
        $conditionType = $filter->getConditionType();
        $attributeName = $filter->getField();
        $attributeValue = $filter->getValue();
        $category = $this->registry->registry('current_category');

        $simpleSelect = $this->collectionFactory->create()
            ->addAttributeToFilter($attributeName, [$conditionType => $attributeValue])
            ->addAttributeToFilter('status',
                \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addCategoriesFilter(['in' => (int)$category->getId()]);

        $simpleSelect->getSelect()
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns(['e.entity_id']);

        $configurableProductsCollection = $this->collectionFactory->create();
        $select = $configurableProductsCollection->getConnection()
            ->select()
            ->distinct()
            ->from(['l' => 'catalog_product_super_link'], 'l.product_id')
            ->join(
                ['k' => $configurableProductsCollection->getTable('catalog_product_entity')],
                'k.entity_id = l.parent_id'
            )->where($configurableProductsCollection->getConnection()->prepareSqlCondition(
                'l.product_id', ['in' => $simpleSelect->getSelect()]
            ))
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns(['l.parent_id']);

        $collection->getSelect()->where($collection->getConnection()->prepareSqlCondition(
            'e.entity_id', ['in' => $select]
        ));

        return true;
    }
}
