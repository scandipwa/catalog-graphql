<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/module-customer-graph-ql
 * @link https://github.com/scandipwa/module-customer-graph-ql
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Layer\Filter;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\LayerBuilderInterface;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\BucketInterface;
use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\Formatter\LayerFormatter;

/**
 * Class Price
 * @package ScandiPWA\CatalogGraphQl\Model\Layer\Filter
 */
class Price implements LayerBuilderInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var string
     */
    private const PRICE_BUCKET = 'price_bucket';

    /**
     * @var LayerFormatter
     */
    private $layerFormatter;

    /**
     * @var array
     */
    private static $bucketMap = [
        self::PRICE_BUCKET => [
            'request_name' => 'price',
            'label' => 'Price'
        ],
    ];

    /**
     * @param LayerFormatter $layerFormatter
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        LayerFormatter $layerFormatter,
        StoreManagerInterface $storeManager
    )
    {
        $this->layerFormatter = $layerFormatter;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws NoSuchEntityException|LocalizedException
     */
    public function build(AggregationInterface $aggregation, ?int $storeId): array
    {
        $bucket = $aggregation->getBucket(self::PRICE_BUCKET);
        if ($this->isBucketEmpty($bucket)) {
            return [];
        }

        $result = $this->layerFormatter->buildLayer(
            self::$bucketMap[self::PRICE_BUCKET]['label'],
            \count($bucket->getValues()),
            self::$bucketMap[self::PRICE_BUCKET]['request_name']
        );

        // Gets cuurrent currency rate
        $currencyRate = $this->storeManager->getStore()->getCurrentCurrencyRate();

        // Loops through-out each price range option
        foreach ($bucket->getValues() as $value) {
            $metrics = $value->getMetrics();

            // Updates to correct currency
            $priceRange = [
                'from' => $this->getMetricValue($metrics['from'], $currencyRate),
                'to' => $this->getMetricValue($metrics['to'], $currencyRate)
            ];

            // Builds graph-ql response
            $result['options'][] = $this->layerFormatter->buildItem(
                $priceRange['from'] . '~' . $priceRange['to'],
                $metrics['value'],
                $metrics['count']
            );
        }

        return [$result];
    }

    /**
     * Converts price to correct currency base,
     * if notehing is set, then changes it to wildcard.
     *
     * @param $base
     * @param $rate
     * @return float|int|string
     */
    private function getMetricValue($base, $rate) {
        return ($base !== null && is_numeric($base)) ? $base * $rate : '*';
    }

    /**
     * Check that bucket contains data
     *
     * @param BucketInterface|null $bucket
     * @return bool
     */
    private function isBucketEmpty(?BucketInterface $bucket): bool
    {
        return null === $bucket || !$bucket->getValues();
    }
}
