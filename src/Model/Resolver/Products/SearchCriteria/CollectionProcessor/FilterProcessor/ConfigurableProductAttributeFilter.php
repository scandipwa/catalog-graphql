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

use Magento\Catalog\Model\Product\Attribute\Source\Status;
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
        $attributeName = $filter->getField();
        $attributeValue = $filter->getValue();
        $category = $this->registry->registry('current_category');
        $conditionType = $filter->getConditionType() ? $filter->getConditionType() : 'eq';

        $simpleSelect = $this->collectionFactory->create()
            ->addAttributeToFilter($attributeName, [$conditionType => $attributeValue])
            ->addAttributeToFilter('status', Status::STATUS_ENABLED);
        if ($category) {
            $simpleSelect->addCategoriesFilter(['in' => (int)$category->getId()]);
        }


        $simpleSelect->getSelect()
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns(['e.entity_id']);

        $configurableProductCollection = $this->collectionFactory->create();
        $select = $configurableProductCollection->getConnection()
            ->select()
            ->distinct()
            ->from(['l' => 'catalog_product_super_link'], 'l.product_id')
            ->join(
                ['k' => $configurableProductCollection->getTable('catalog_product_entity')],
                'k.entity_id = l.parent_id'
            )->where($configurableProductCollection->getConnection()->prepareSqlCondition(
                'l.product_id',
                ['in' => $simpleSelect->getSelect()]
            ))
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns(['l.parent_id']);

        $unionCollection = $this->collectionFactory->create();
        $unionSelect = $unionCollection->getConnection()
            ->select()
            ->union([$simpleSelect->getSelect(), $select]);

        $collection->getSelect()
            ->where($collection->getConnection()->prepareSqlCondition(
                'e.entity_id',
                ['in' => $unionSelect]
            ));

        return true;
    }
}
