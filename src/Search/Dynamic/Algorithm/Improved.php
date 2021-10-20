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
namespace ScandiPWA\CatalogGraphQl\Search\Dynamic\Algorithm;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Search\Adapter\OptionsInterface;
use Magento\Framework\Search\Dynamic\Algorithm;
use Magento\Framework\Search\Dynamic\Algorithm\AlgorithmInterface;
use Magento\Framework\Search\Dynamic\DataProviderInterface;
use Magento\Framework\Search\Dynamic\EntityStorage;
use Magento\Framework\Search\Request\BucketInterface;

class Improved implements AlgorithmInterface
{
    /**
     * @var Algorithm
     */
    protected $algorithm;

    /**
     * @var DataProviderInterface
     */
    protected $dataProvider;

    /**
     * @var OptionsInterface
     */
    protected $options;

    /**
     * @param DataProviderInterface $dataProvider
     * @param Algorithm $algorithm
     * @param OptionsInterface $options
     */
    public function __construct(
        DataProviderInterface $dataProvider,
        Algorithm $algorithm,
        OptionsInterface $options
    ) {
        $this->algorithm = $algorithm;
        $this->dataProvider = $dataProvider;
        $this->options = $options;
    }

    /**
     * @inheritdoc
     */
    public function getItems(
        BucketInterface $bucket,
        array $dimensions,
        EntityStorage $entityStorage
    ) {
        $aggregations = $this->dataProvider->getAggregations($entityStorage);

        $options = $this->options->get();
        if ($aggregations['count'] < $options['interval_division_limit']) {
            return [[
                'from' => $aggregations['min'],
                'to' => $aggregations['max'],
                'count' => $aggregations['count']
            ]];
        }
        $this->algorithm->setStatistics(
            $aggregations['min'],
            $aggregations['max'],
            $aggregations['std'],
            $aggregations['count']
        );

        $this->algorithm->setLimits($aggregations['min'], $aggregations['max']);

        $interval = $this->dataProvider->getInterval($bucket, $dimensions, $entityStorage);
        $data = $this->algorithm->calculateSeparators($interval);

        $data[0]['from'] = 0;

        foreach ($data as $key => $key){
            if (isset($data[$key + 1])) {
                $data[$key]['to'] = $data[$key + 1]['from'];
            }
        }

        return $data;
    }
}
