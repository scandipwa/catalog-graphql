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
    /**
     * @var Configurable
     */
    protected $configurable;
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * ConfigurableProductAttributeFilter constructor.
     * @param Configurable $configurable
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Configurable $configurable,
        CollectionFactory $collectionFactory
    ) {
        $this->configurable = $configurable;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @param Filter $filter
     * @param AbstractDb $collection
     * @return bool
     */
    public function apply(Filter $filter, AbstractDb $collection)
    {
        $attributeName = $filter->getField();
        $attributeValue = $filter->getValue();
        $conditionType = $filter->getConditionType();

        $simpleSelect = $this->collectionFactory->create()
            ->addAttributeToFilter($attributeName, [$conditionType => $attributeValue])
            ->addAttributeToFilter('status', Status::STATUS_ENABLED);


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
