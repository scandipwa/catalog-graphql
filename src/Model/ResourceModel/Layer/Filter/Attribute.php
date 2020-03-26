<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/catalog-graphql
 * @link    https://github.com/scandipwa/catalog-graphql
 */

namespace ScandiPWA\CatalogGraphQl\Model\ResourceModel\Layer\Filter;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Layer\Filter\Attribute as CoreAttribute;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Zend_Db_Expr;

/**
 * Class Attribute
 * @package ScandiPWA\CatalogGraphQl\Model\ResourceModel\Layer\Filter
 */
class Attribute extends CoreAttribute
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
     * @throws LocalizedException
     */
    public function getCount(FilterInterface $filter)
    {
        // clone select from collection with filters
        $select = clone $filter->getLayer()->getProductCollection()->getSelect();

        // reset columns, order and limitation conditions
        $select->reset(Select::COLUMNS);
        $select->reset(Select::ORDER);
        $select->reset(Select::LIMIT_COUNT);
        $select->reset(Select::LIMIT_OFFSET);

        // join category and attribute statements
        $this->_joinCategory($select);
        $this->_joinAttribute($select, $filter->getAttributeModel(), $filter->getStoreId());

        return $this->getConnection()->fetchPairs($select);
    }

    /**
     * Join current category to select statement
     *
     * @param $select
     */
    protected function _joinCategory(&$select)
    {
        $tableAlias = 'category_product';
        $queryConditions = [
            "{$tableAlias}.product_id = e.entity_id",
            "{$tableAlias}.category_id = {$this->_getCurrentCategory()->getId()}"
        ];

        $select->join(
            [$tableAlias => $this->getTable('catalog_category_product')],
            implode(' AND ', $queryConditions),
            null
        );
    }

    /**
     * Join attribute to select statement
     *
     * @param $select
     * @param $attribute
     * @param $storeId
     * @throws LocalizedException
     */
    protected function _joinAttribute(&$select, $attribute, $storeId)
    {
        $tableAlias = 'attribute_idx';
        $connection = $this->getConnection();

        $queryConditions = [
            "{$tableAlias}.entity_id = e.entity_id",
            $connection->quoteInto("{$tableAlias}.attribute_id = ?", $attribute->getAttributeId()),
            $connection->quoteInto("{$tableAlias}.store_id = ?", $storeId)
        ];

        $select->join(
            [$tableAlias => $this->getMainTable()],
            implode(' AND ', $queryConditions),
            ['value', 'count' => new Zend_Db_Expr("COUNT({$tableAlias}.entity_id)")]
        )->group(["{$tableAlias}.value"]);
    }

    /**
     * Get current category
     *
     * @return Category
     */
    protected function _getCurrentCategory()
    {
        return $this->registry->registry('current_category');
    }
}
