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

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use ScandiPWA\CatalogGraphQl\Helper\FiltersHelper;

/**
 * Class Attribute
 * @package ScandiPWA\CatalogGraphQl\Model\ResourceModel\Layer\Filter
 */
class Attribute extends \Magento\Catalog\Model\ResourceModel\Layer\Filter\Attribute
{
    /**
     * @var FiltersHelper
     */
    protected $filtersHelper;

    /**
     * @var CatalogHelper
     */
    protected $catalogHelper;

    /**
     * @param CatalogHelper $catalogHelper
     * @param FiltersHelper $filtersHelper
     * @param Context $context
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        CatalogHelper $catalogHelper,
        FiltersHelper $filtersHelper,
        $connectionName = null
    )
    {
        $this->catalogHelper = $catalogHelper;
        $this->filtersHelper = $filtersHelper;
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
            "{$tableAlias}.category_id = {$this->catalogHelper->getCategory()->getId()}"
        ];

        $select->join(
            [$tableAlias => $this->getTable('catalog_category_product')],
            join(' AND ', $queryConditions),
            null
        );
    }

    /**
     * Join attribute to select statement
     *
     * @param $select
     * @param $attribute
     * @param $storeId
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _joinAttribute(&$select, $attribute, $storeId)
    {
        $tableAlias = 'attribute_idx';
        $connection = $this->getConnection();
        $activeFilters = $this->filtersHelper->getFilters();

        $queryConditions = [
            "{$tableAlias}.entity_id = e.entity_id",
            $connection->quoteInto("{$tableAlias}.attribute_id = ?", $attribute->getAttributeId()),
            $connection->quoteInto("{$tableAlias}.store_id = ?", $storeId)
        ];

        if (count($activeFilters) > 0) {
            // add current active filters' join statement
            if (isset($activeFilters[$attribute->getAttributeCode()])) {
                $currentActiveFiltersQuery = $connection->select()
                    ->from($this->getMainTable(), 'entity_id')
                    ->where('value IN(?)', $activeFilters[$attribute->getAttributeCode()]);
                $queryConditions[] = "{$tableAlias}.entity_id IN({$currentActiveFiltersQuery})";
                unset($activeFilters[$attribute->getAttributeCode()]);
            }

            // add the rest active filter join statements
            if (count($activeFilters) > 0) {
                foreach ($activeFilters as $activeFilter) {
                    $otherActiveFiltersSelect = $connection->select()
                        ->from($this->getMainTable(), 'entity_id')
                        ->where('value IN(?)', $activeFilter);
                    $queryConditions[] = "{$tableAlias}.entity_id IN({$otherActiveFiltersSelect})";
                }
            }
        }

        $select->join(
            [$tableAlias => $this->getMainTable()],
            join(' AND ', $queryConditions),
            ['value', 'count' => new \Zend_Db_Expr("COUNT({$tableAlias}.entity_id)")]
        )->group(["{$tableAlias}.value"]);
    }
}
