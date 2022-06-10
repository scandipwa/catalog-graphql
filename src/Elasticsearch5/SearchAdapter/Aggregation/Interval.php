<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Elasticsearch5\SearchAdapter\Aggregation;

use Magento\Elasticsearch\SearchAdapter\ConnectionManager;
use Magento\Elasticsearch\Model\Adapter\FieldMapperInterface;
use Magento\Elasticsearch\Model\Config;
use Magento\Elasticsearch\SearchAdapter\SearchIndexNameResolver;
use Magento\CatalogSearch\Model\Indexer\Fulltext;
use Magento\Elasticsearch\Elasticsearch5\SearchAdapter\Aggregation\Interval as CoreInterval;

/**
 * Aggregate price intervals for search query result.
 */
class Interval extends CoreInterval
{
    /**
     * Minimal possible value
     */
    const DELTA = 0.005;

    /**
     * @var ConnectionManager
     */
    protected $connectionManager;

    /**
     * @var FieldMapperInterface
     */
    protected $fieldMapper;

    /**
     * @var Config
     */
    protected $clientConfig;

    /**
     * @var string
     */
    protected $fieldName;

    /**
     * @var string
     */
    protected $storeId;

    /**
     * @var array
     */
    protected $entityIds;

    /**
     * @var SearchIndexNameResolver
     */
    protected $searchIndexNameResolver;

    /**
     * @param ConnectionManager $connectionManager
     * @param FieldMapperInterface $fieldMapper
     * @param Config $clientConfig
     * @param SearchIndexNameResolver $searchIndexNameResolver
     * @param string $fieldName
     * @param string $storeId
     * @param array $entityIds
     */
    public function __construct(
        ConnectionManager $connectionManager,
        FieldMapperInterface $fieldMapper,
        Config $clientConfig,
        SearchIndexNameResolver $searchIndexNameResolver,
        string $fieldName,
        string $storeId,
        array $entityIds
    ) {
        $this->connectionManager = $connectionManager;
        $this->fieldMapper = $fieldMapper;
        $this->clientConfig = $clientConfig;
        $this->fieldName = $fieldName;
        $this->storeId = $storeId;
        $this->entityIds = $entityIds;
        $this->searchIndexNameResolver = $searchIndexNameResolver;

        parent::__construct(
            $connectionManager,
            $fieldMapper,
            $clientConfig,
            $searchIndexNameResolver,
            $fieldName,
            $storeId,
            $entityIds,

        );
    }

    /**
     * @inheritdoc
     */
    public function load($limit, $offset = null, $lower = null, $upper = null)
    {

        $from = ['gte' => 0];       //Added this because in some situations the $lower is null and $from is not declared
        $to = ['lt' => 0];          //Added this because in some situations the $data is null and $to is not declared

        if ($lower) {
            $from = ['gte' => $lower - self::DELTA];
        }

        if ($upper) {
            $to = ['lt' => $upper - self::DELTA];
        }

        $requestQuery = $this->prepareBaseRequestQuery($from, $to);
        $requestQuery = array_merge_recursive(
            $requestQuery,
            ['body' => ['stored_fields' => [$this->fieldName], 'size' => $limit]]
        );

        if ($offset) {
            $requestQuery['body']['from'] = $offset;
        }

        $queryResult = $this->connectionManager->getConnection()
            ->query($requestQuery);

        return $this->arrayValuesToFloat($queryResult['hits']['hits'], $this->fieldName);
    }


    /**
     * @inheritdoc
     */
    public function loadPrevious($data, $index, $lower = null)
    {

        $from = ['gte' => 0];       //Added this because in some situations the $lower is null and $from is not declared
        $to = ['lt' => 0];          //Added this because in some situations the $data is null and $to is not declared

        if ($lower) {
            $from = ['gte' => $lower - self::DELTA];
        }
        if ($data) {
            $to = ['lt' => $data - self::DELTA];
        }

        $requestQuery = $this->prepareBaseRequestQuery($from, $to);
        $requestQuery = array_merge_recursive(
            $requestQuery,
            ['size' => 0]
        );

        $queryResult = $this->connectionManager->getConnection()
            ->query($requestQuery);

        $offset = $queryResult['hits']['total'];
        if (!$offset) {
            return false;
        }

        if (is_array($offset)) {
            $offset = $offset['value'];
        }

        return $this->load($index - $offset + 1, $offset - 1, $lower);
    }

    /**
     * Conver array values to float type.
     *
     * @param array $hits
     * @param string $fieldName
     *
     * @return float[]
     */
    protected function arrayValuesToFloat(array $hits, string $fieldName): array
    {
        $returnPrices = [];
        foreach ($hits as $hit) {
            $returnPrices[] = (float)$hit['fields'][$fieldName][0];
        }

        return $returnPrices;
    }

    /**
     * Prepare base query for search.
     *
     * @param array|null $from
     * @param array|null $to
     * @return array
     */
    protected function prepareBaseRequestQuery($from = null, $to = null): array
    {
        $requestQuery = [
            'index' => $this->searchIndexNameResolver->getIndexName($this->storeId, Fulltext::INDEXER_ID),
            'type' => $this->clientConfig->getEntityType(),
            'body' => [
                'stored_fields' => [
                    '_id',
                ],
                'query' => [
                    'bool' => [
                        'must' => [
                            'match_all' => new \stdClass(),
                        ],
                        'filter' => [
                            'bool' => [
                                'must' => [
                                    [
                                        'terms' => [
                                            '_id' => $this->entityIds,
                                        ],
                                    ],
                                    [
                                        'range' => [
                                            $this->fieldName => array_merge($from, $to),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'sort' => [
                    $this->fieldName,
                ],
            ],
        ];

        return $requestQuery;
    }
}
