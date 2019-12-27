<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Artjoms Travkovs <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\ResourceModel\Layer\Filter;

use Magento\Framework\Registry;
use Magento\Catalog\Model\Layer\Filter\FilterInterface;

/**
 * Class Attribute
 * @package ScandiPWA\CatalogGraphQl\Model\ResourceModel\Layer\Filter
 */
class Attribute extends \Magento\Catalog\Model\ResourceModel\Layer\Filter\Attribute
{
    private $searchCriteria;

    protected $filterProcessor;

    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        $connectionName = null,
        \ScandiPWA\CatalogGraphQl\Model\Resolver\Products\SearchCriteria\CollectionProcessor\ExclusiveFilterProcessor $filterProcessor
    ) {
        parent::__construct($context, $connectionName);
        $this->filterProcessor = $filterProcessor;
    }

    public function setSearchCriteria($searchCriteria)
    {
        $this->searchCriteria = $searchCriteria;
        return $this;
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
        $attribute = $filter->getAttributeModel();
        $productCollection = clone $filter->getLayer()->getProductCollection();

        // echo 'Couont: '  . count($this->searchCriteria->ge

        $this->filterProcessor
            ->setIgnoredFilters([$attribute->getAttributeCode()])
            ->process($this->searchCriteria, $productCollection);

        $select = $productCollection->getSelect();
        // reset columns, order and limitation conditions
        $select->reset(\Magento\Framework\DB\Select::COLUMNS);
        $select->reset(\Magento\Framework\DB\Select::ORDER);
        $select->reset(\Magento\Framework\DB\Select::LIMIT_COUNT);
        $select->reset(\Magento\Framework\DB\Select::LIMIT_OFFSET);

        $connection = $this->getConnection();
        $tableAlias = sprintf('%s_idx', $attribute->getAttributeCode());
        $conditions = [
            "{$tableAlias}.entity_id = e.entity_id",
            $connection->quoteInto("{$tableAlias}.attribute_id = ?", $attribute->getAttributeId()),
            $connection->quoteInto("{$tableAlias}.store_id = ?", $filter->getStoreId()),
        ];

        $select->join(
            [$tableAlias => $this->getMainTable()],
            join(' AND ', $conditions),
            ['value', 'entity_id']
        );

        $results = $connection->fetchAll($select);

        return $this->countFilters($results);
    }

    /**
     * Counts filters
     *
     * @param array $results
     * @return array
     */
    protected function countFilters(array $results): array
    {
        $resultArr = [];
        $existingEntities = [];

        foreach ($results as list('value' => $key, 'entity_id' => $entityId)) {
            if (!array_key_exists($key, $resultArr)) {
                $resultArr[$key] = 1;
                $existingEntities[$key][] = $entityId;
            } elseif (!in_array($entityId, $existingEntities[$key])) {
                $resultArr[$key]++;
                $existingEntities[$key][] = $entityId;
            }
        }

        return $resultArr;
    }
}
