<?php

/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Alfreds Genkins <info@scandipwa.com>
 * @copyright   Copyright (c) 2021 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\CatalogGraphQl\SearchAdapter\Dynamic;

use Magento\Elasticsearch\SearchAdapter\Dynamic\DataProvider as CoreDataProvider;
use Magento\Elasticsearch\SearchAdapter\QueryContainer;

/**
 * Elastic search data provider
 *
 * @api
 * @since 100.1.0
 */
class DataProvider extends CoreDataProvider
{
    /**
     * @param \Magento\Elasticsearch\SearchAdapter\ConnectionManager $connectionManager
     * @param \Magento\Elasticsearch\Model\Adapter\FieldMapperInterface $fieldMapper
     * @param \Magento\Catalog\Model\Layer\Filter\Price\Range $range
     * @param \Magento\Framework\Search\Dynamic\IntervalFactory $intervalFactory
     * @param \Magento\Elasticsearch\Model\Config $clientConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Elasticsearch\SearchAdapter\SearchIndexNameResolver $searchIndexNameResolver
     * @param string $indexerId
     * @param \Magento\Framework\App\ScopeResolverInterface $scopeResolver
     * @param QueryContainer|null $queryContainer
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Elasticsearch\SearchAdapter\ConnectionManager $connectionManager,
        \Magento\Elasticsearch\Model\Adapter\FieldMapperInterface $fieldMapper,
        \Magento\Catalog\Model\Layer\Filter\Price\Range $range,
        \Magento\Framework\Search\Dynamic\IntervalFactory $intervalFactory,
        \Magento\Elasticsearch\Model\Config $clientConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Elasticsearch\SearchAdapter\SearchIndexNameResolver $searchIndexNameResolver,
        $indexerId,
        \Magento\Framework\App\ScopeResolverInterface $scopeResolver,
        QueryContainer $queryContainer = null
    )
    {
        parent::__construct(
            $connectionManager,
            $fieldMapper,
            $range,
            $intervalFactory,
            $clientConfig,
            $storeManager,
            $searchIndexNameResolver,
            $indexerId,
            $scopeResolver,
            $queryContainer
        );
    }

    /**
     * @inheritdoc
     * @since 100.1.0
     */
    public function prepareData($range, array $dbRanges)
    {
        $data = [];

        if (!empty($dbRanges)) {
            $array_keys = array_keys($dbRanges);
            $last_key = end($array_keys);
            $first_key = $array_keys[0];

            foreach ($dbRanges as $index => $count) {
                $fromPrice = $index == 1 ? 0 : ($index - 1) * $range;
                $toPrice = $index == $last_key && $last_key != $first_key ? '*' : $index * $range - 0.01;
                $data[] = [
                    'from' => $fromPrice,
                    'to' => $toPrice,
                    'count' => $count,
                ];
            }
        }

        return $data;
    }
}
