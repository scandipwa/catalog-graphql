<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Product;

use Magento\CatalogGraphQl\Model\Resolver\Product\Price\Discount;
use Magento\CatalogGraphQl\Model\Resolver\Product\Price\ProviderPool as PriceProviderPool;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\CatalogGraphQl\Model\Resolver\Product\PriceRange as CorePriceRange;

/**
 * Format product's pricing information for price_range field
 */
class PriceRange extends CorePriceRange
{
    /**
     * @var Discount
     */
    protected $discount;

    /**
     * @var PriceProviderPool
     */
    protected $priceProviderPool;

    /**
     * @param PriceProviderPool $priceProviderPool
     * @param Discount $discount
     */
    public function __construct(
        PriceProviderPool $priceProviderPool,
        Discount $discount
    )
    {
        parent::__construct(
            $priceProviderPool,
            $discount
        );

        $this->priceProviderPool = $priceProviderPool;
        $this->discount = $discount;
    }

    /**
     * @inheritdoc
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
     * @param SaleableInterface $product
     * @param StoreInterface $store
     * @return array
     */
    protected function getMinimumProductPrice(SaleableInterface $product, StoreInterface $store): array
    {
        $priceProvider = $this->priceProviderPool->getProviderByProductType($product->getTypeId());
        $regularPrice = (float) $priceProvider->getMinimalRegularPrice($product)->getValue();
        $finalPrice = (float) $priceProvider->getMinimalFinalPrice($product)->getValue();
        $discount = $this->calculateDiscount($product, $regularPrice, $finalPrice);
        $regularPriceExclTax = (float) $priceProvider->getMinimalRegularPrice($product)->getBaseAmount();
        $finalPriceExclTax = (float) $priceProvider->getMinimalFinalPrice($product)->getBaseAmount();

        $minPriceArray = $this->formatPrice($regularPrice, $regularPriceExclTax, $finalPrice, $finalPriceExclTax, $discount, $store);
        $minPriceArray['model'] = $product;
        return $minPriceArray;
    }

    /**
     * Get formatted maximum product price
     *
     * @param SaleableInterface $product
     * @param StoreInterface $store
     * @return array
     */
    protected function getMaximumProductPrice(SaleableInterface $product, StoreInterface $store): array
    {
        $priceProvider = $this->priceProviderPool->getProviderByProductType($product->getTypeId());
        $regularPrice = (float) $priceProvider->getMaximalRegularPrice($product)->getValue();
        $finalPrice = (float) $priceProvider->getMaximalFinalPrice($product)->getValue();
        $discount = $this->calculateDiscount($product, $regularPrice, $finalPrice);
        $regularPriceExclTax = (float) $priceProvider->getMinimalRegularPrice($product)->getBaseAmount();
        $finalPriceExclTax = (float) $priceProvider->getMinimalFinalPrice($product)->getBaseAmount();

        $maxPriceArray = $this->formatPrice($regularPrice, $regularPriceExclTax, $finalPrice, $finalPriceExclTax, $discount, $store);
        $maxPriceArray['model'] = $product;
        return $maxPriceArray;
    }

    /**
     * Format price for GraphQl output
     *
     * @param float $regularPrice
     * @param float $regularPriceExclTax
     * @param float $finalPrice
     * @param float $finalPriceExclTax
     * @param array $discount
     * @param StoreInterface $store
     * @return array
     */
    protected function formatPrice(
        float $regularPrice,
        float $regularPriceExclTax,
        float $finalPrice,
        float $finalPriceExclTax,
        array $discount,
        StoreInterface $store
    ): array {
        return [
            'regular_price' => [
                'value' => $regularPrice,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'regular_price_excl_tax' => [
                'value' => $regularPriceExclTax,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'final_price' => [
                'value' => $finalPrice,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'final_price_excl_tax' => [
                'value' => $finalPriceExclTax,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'discount' => $discount,
        ];
    }

    /**
     * Calculates correct discount amount
     * - Bundle items can contain $regularPrice and $finalFrice from two different
     * - product instances, thus we are intersted in BE set special price procentage.
     *
     * @param Product $product
     * @param float $regularPrice
     * @param float $finalPrice
     * @return array
     */
    protected function calculateDiscount(Product $product, float $regularPrice, float $finalPrice) : array
    {
        if ($product->getTypeId() !== 'bundle') {
            return $this->discount->getDiscountByDifference($regularPrice, $finalPrice);
        }

        // Bundle products have special price set in % (percents)
        $specialPricePrecentage = $this->getSpecialProductPrice($product);
        $percentOff = $specialPricePrecentage === null ? 0 : 100 - $specialPricePrecentage;

        return [
            'amount_off' => $regularPrice * ($percentOff / 100),
            'percent_off' => $percentOff
        ];
    }

    /**
     * Gets [active] special price value
     *
     * @param Product $product
     * @return float
     */
    protected function getSpecialProductPrice(Product $product): ?float
    {
        $specialPrice = $product->getSpecialPrice();
        if (!$specialPrice) {
            return null;
        }

        // Special price range
        $from = strtotime($product->getSpecialFromDate());
        $to = $product->getSpecialToDate() === null ? null : strtotime($product->getSpecialToDate());
        $now = time();

        return ($now >= $from && $now <= $to) || ($now >= $from && $to === null) ? $specialPrice : null;
    }
}
