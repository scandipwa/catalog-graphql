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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;
use Magento\CatalogGraphQl\Model\Resolver\Product\Price\Discount;
use Magento\CatalogGraphQl\Model\Resolver\Product\Price\ProviderPool as PriceProviderPool;
use Magento\CatalogGraphQl\Model\Resolver\Product\PriceRange as CorePrice;

/**
 * Format a product's price information to conform to GraphQL schema representation
 */
class PriceRange extends CorePrice
{
    /**
     * @var PriceProviderPool
     */
    private $priceProviderPool;

    /**
     * @var Discount
     */
    private $discount;

    /**
     * @param PriceProviderPool $priceProviderPool
     * @param Discount $discount
     */
    public function __construct(
        PriceProviderPool $priceProviderPool,
        Discount $discount
    ) {
        $this->priceProviderPool = $priceProviderPool;
        $this->discount = $discount;
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
        /** @var StoreInterface $store */
        $store = $context->getExtensionAttributes()->getStore();

        /** @var Product $product */
        $product = $value['model'];

        $requestedFields = $info->getFieldSelection(10);
        $returnArray = [];

        if (isset($requestedFields['minimum_price'])) {
            $returnArray['minimum_price'] =  $this->getMinimumProductPrice($product, $store);
        }
        if (isset($requestedFields['maximum_price'])) {
            $returnArray['maximum_price'] =  $this->getMaximumProductPrice($product, $store);
        }
        return $returnArray;
    }

    /**
     * Get formatted minimum product price
     *
     * @param Product $product
     * @param StoreInterface $store
     * @return array
     */
    private function getMinimumProductPrice(Product $product, StoreInterface $store): array
    {
        $priceProvider = $this->priceProviderPool->getProviderByProductType($product->getTypeId());
        $regularPrice = $priceProvider->getMinimalRegularPrice($product)->getValue();
        $finalPrice = $priceProvider->getMinimalFinalPrice($product)->getValue();
        $minPriceArray = $this->formatPrice((float) $regularPrice, (float) $finalPrice, $store);
        $minPriceArray['model'] = $product;
        return $minPriceArray;
    }

    /**
     * Get formatted maximum product price
     *
     * @param Product $product
     * @param StoreInterface $store
     * @return array
     */
    private function getMaximumProductPrice(Product $product, StoreInterface $store): array
    {
        $priceProvider = $this->priceProviderPool->getProviderByProductType($product->getTypeId());
        $regularPrice = $priceProvider->getMaximalRegularPrice($product)->getValue();
        $finalPrice = $priceProvider->getMaximalFinalPrice($product)->getValue();
        $maxPriceArray = $this->formatPrice((float) $regularPrice, (float) $finalPrice, $store);
        $maxPriceArray['model'] = $product;
        return $maxPriceArray;
    }

    /**
     * Format price for GraphQl output
     *
     * @param float $regularPrice
     * @param float $finalPrice
     * @param StoreInterface $store
     * @return array
     */
    private function formatPrice(float $regularPrice, float $finalPrice, StoreInterface $store): array
    {
        return [
            'regular_price' => [
                'value' => $regularPrice,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'final_price' => [
                'value' => $finalPrice,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'discount' => $this->discount->getDiscountByDifference($regularPrice, $finalPrice),
        ];
    }
}
