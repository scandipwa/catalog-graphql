<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Kirils Scerba <kirill@scandiweb.com | info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\CatalogGraphQl\Model\ResourceModel\Layer\Filter;

use Magento\Framework\Registry;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Catalog\Model\Layer\Filter\FilterInterface;

/**
 * Class Attribute
 * @package ScandiPWA\CatalogGraphQl\Model\ResourceModel\Layer\Filter
 */
class Attribute extends \Magento\Catalog\Model\ResourceModel\Layer\Filter\Attribute
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @param Registry $registry
     * @param Context $context
     * @param null $connectionName
     */
    public function __construct(
        Registry $registry,
        Context $context,
        $connectionName = null
    )
    {
        $this->registry = $registry;
        parent::__construct($context, $connectionName);
    }

    /**
     * Retrieve array with products counts per attribute option
     *
     * @param FilterInterface $filter
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCount(FilterInterface $filter)
    {
        // clone select from collection with filters
        $select = clone $filter->getLayer()->getProductCollection()->getSelect();

        // reset columns, order and limitation conditions
        $select->reset(\Magento\Framework\DB\Select::COLUMNS);
        $select->reset(\Magento\Framework\DB\Select::ORDER);
        $select->reset(\Magento\Framework\DB\Select::LIMIT_COUNT);
        $select->reset(\Magento\Framework\DB\Select::LIMIT_OFFSET);

        $connection = $this->getConnection();
        $attribute = $filter->getAttributeModel();
        $attributeTableAlias = sprintf('%s_idx', $attribute->getAttributeCode());
        $categoryTableAlias = 'category_product';
        $category = $this->registry->registry('current_category');

        // join category
        $categoryQueryConditions = [
            "{$categoryTableAlias}.product_id = e.entity_id",
            "{$categoryTableAlias}.category_id = {$category->getId()}"
        ];
        $select->join(
            [$categoryTableAlias => $this->getTable('catalog_category_product')],
            join(' AND ', $categoryQueryConditions),
            null
        );

        // join attribute
        $attributeQueryConditions = [
            "{$attributeTableAlias}.entity_id = e.entity_id",
            $connection->quoteInto("{$attributeTableAlias}.attribute_id = ?", $attribute->getAttributeId()),
            $connection->quoteInto("{$attributeTableAlias}.store_id = ?", $filter->getStoreId()),
        ];
        $select->join(
            [$attributeTableAlias => $this->getMainTable()],
            join(' AND ', $attributeQueryConditions),
            ['value', 'count' => new \Zend_Db_Expr("COUNT({$attributeTableAlias}.entity_id)")]
        );

        $select->group(
            ["{$attributeTableAlias}.value", "{$categoryTableAlias}.category_id"]
        );

        return $connection->fetchPairs($select);
    }
}
