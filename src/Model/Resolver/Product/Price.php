<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Yefim Butrameev <info@scandiweb.com>
 * @copyright   Copyright (c) 2020 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Pricing\Adjustment\AdjustmentInterface;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\CatalogGraphQl\Model\Resolver\Product\Price\ProviderPool as PriceProviderPool;
use Magento\CatalogGraphQl\Model\Resolver\Product\PriceRange as CorePrice;

/**
 * Format a product's price information to conform to GraphQL schema representation
 */
class Price extends CorePrice
{
    /**
     * @var PriceProviderPool
     */
    private $priceProviderPool;

    /**
     * @param PriceProviderPool $priceProviderPool
     */

    public function __construct(
        PriceProviderPool $priceProviderPool
    ) {
        $this->priceProviderPool = $priceProviderPool;
    }

    /**
     * @inheritdoc
     *
     * Format product's tier price data to conform to GraphQL schema
     *
     * @param \Magento\Framework\GraphQl\Config\Element\Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @throws \Exception
     * @return array
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        /** @var Product $product */
        $product = $value['model'];
        $priceProvider = $this->priceProviderPool->getProviderByProductType($product->getTypeId());

        $minimalPriceAmount = $priceProvider->getMinimalFinalPrice($product)->getValue();
        $regularPriceAmount = $priceProvider->getRegularPrice($product)->getValue();
        $maximalPriceAmount = $priceProvider->getMaximalFinalPrice($product)->getValue();

        $store = $context->getExtensionAttributes()->getStore();

        return $this->formatPrice($minimalPriceAmount, $regularPriceAmount, $maximalPriceAmount, $store);
    }

    /**
     * Format price for GraphQl output
     *
     * @param $minimalPrice
     * @param $regularPrice
     * @param $maximalPrice
     * @param StoreInterface $store
     * @return array
     */
    private function formatPrice($minimalPrice, $regularPrice, $maximalPrice, StoreInterface $store): array
    {
        return [
            'minimalPrice' => [
                'amount' => [
                    'value' => $minimalPrice,
                    'currency' => $store->getCurrentCurrencyCode()
                ]
            ],
            'regularPrice' => [
                'amount' => [
                    'value' => $regularPrice,
                    'currency' => $store->getCurrentCurrencyCode()
                ]
            ],
            'maximalPrice' => [
                'amount' => [
                    'value' => $maximalPrice,
                    'currency' => $store->getCurrentCurrencyCode()
                ]
            ]
        ];
    }
}
