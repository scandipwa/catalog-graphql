<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Product;

use Magento\CatalogGraphQl\Model\Resolver\Product\Price\Discount;
use Magento\CatalogGraphQl\Model\Resolver\Product\Price\ProviderPool as PriceProviderPool;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Helper\Data as TaxHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\CatalogGraphQl\Model\Resolver\Product\PriceRange as CorePriceRange;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * Format product's pricing information for price_range field
 */
class PriceRange extends CorePriceRange
{
    const XML_PRICE_INCLUDES_TAX = 'tax/calculation/price_includes_tax';
    const FINAL_PRICE = 'final_price';

    /**
     * @var float
     */
    protected $zeroThreshold = 0.0001;

    /**
     * @var Discount
     */
    protected $discount;

    /**
     * @var PriceProviderPool
     */
    protected $priceProviderPool;

    /**
     * @var PriceCurrencyInterface
     */
    protected PriceCurrencyInterface $priceCurrency;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var TaxHelper
     */
    protected $taxHelper;

    /**
     * @param PriceProviderPool $priceProviderPool
     * @param Discount $discount
     */
    public function __construct(
        PriceProviderPool $priceProviderPool,
        Discount $discount,
        PriceCurrencyInterface $priceCurrency,
        ScopeConfigInterface $scopeConfig,
        TaxHelper $taxHelper
    )
    {
        parent::__construct(
            $priceProviderPool,
            $discount
        );

        $this->priceProviderPool = $priceProviderPool;
        $this->discount = $discount;
        $this->priceCurrency = $priceCurrency;
        $this->scopeConfig = $scopeConfig;
        $this->taxHelper = $taxHelper;
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
        $finalPrice = 0;

        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            $finalPrice = $product->getPriceInfo()->getPrice(self::FINAL_PRICE)->getValue();
        } else {
            $finalPrice = (float) $priceProvider->getMinimalFinalPrice($product)->getValue();
        }

        $discount = $this->calculateDiscount($product, $regularPrice, $finalPrice);

        $regularPriceExclTax = (float) $priceProvider->getMinimalRegularPrice($product)->getBaseAmount();
        $finalPriceExclTax = (float) $priceProvider->getMinimalFinalPrice($product)->getBaseAmount();

        if($product->getTypeId() == ProductType::TYPE_SIMPLE) {
            $priceInfo = $product->getPriceInfo();
            $defaultRegularPrice = $priceInfo->getPrice(RegularPrice::PRICE_CODE)->getAmount()->getValue();
            $defaultFinalPrice = $priceInfo->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getValue();
            $defaultFinalPriceExclTax = $priceInfo->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getBaseAmount();

            $discount = $this->calculateDiscount($product, $defaultRegularPrice, $defaultFinalPrice);
        } else {
            $defaultRegularPrice = $this->taxHelper->getTaxPrice($product, $product->getPrice(), $this->isPriceIncludesTax());
            $defaultFinalPrice = (float) round($priceProvider->getRegularPrice($product)->getValue(), 2);
            $defaultFinalPriceExclTax = (float) $priceProvider->getRegularPrice($product)->getBaseAmount();
        }

        $defaultRegularPrice = isset($defaultRegularPrice) ? $defaultRegularPrice : 0;
        $defaultFinalPrice = isset($defaultFinalPrice) ? $defaultFinalPrice : 0;
        $defaultFinalPriceExclTax = isset($defaultFinalPriceExclTax) ? $defaultFinalPriceExclTax : 0;

        $minPriceArray = $this->formatPrice(
            $regularPrice, $regularPriceExclTax, $finalPrice, $finalPriceExclTax,
            $defaultRegularPrice, $defaultFinalPrice, $defaultFinalPriceExclTax, $discount, $store
        );
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

        $regularPriceExclTax = (float) $priceProvider->getMaximalRegularPrice($product)->getBaseAmount();
        $finalPriceExclTax = (float) $priceProvider->getMaximalFinalPrice($product)->getBaseAmount();

        if($product->getTypeId() == ProductType::TYPE_SIMPLE) {
            $priceInfo = $product->getPriceInfo();
            $defaultRegularPrice = $priceInfo->getPrice(RegularPrice::PRICE_CODE)->getAmount()->getValue();
            $defaultFinalPrice = $priceInfo->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getValue();
            $defaultFinalPriceExclTax = $priceInfo->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getBaseAmount();

            $discount = $this->calculateDiscount($product, $defaultRegularPrice, $defaultFinalPrice);
        } else {
            $defaultRegularPrice = $this->taxHelper->getTaxPrice($product, $product->getPrice(), $this->isPriceIncludesTax());
            $defaultFinalPrice = (float) round($priceProvider->getRegularPrice($product)->getValue(), 2);
            $defaultFinalPriceExclTax = (float) $priceProvider->getRegularPrice($product)->getBaseAmount();
        }

        $defaultRegularPrice = isset($defaultRegularPrice) ? $defaultRegularPrice : 0;
        $defaultFinalPrice = isset($defaultFinalPrice) ? $defaultFinalPrice : 0;
        $defaultFinalPriceExclTax = isset($defaultFinalPriceExclTax) ? $defaultFinalPriceExclTax : 0;

        $maxPriceArray = $this->formatPrice(
            $regularPrice, $regularPriceExclTax, $finalPrice, $finalPriceExclTax,
            $defaultRegularPrice, $defaultFinalPrice, $defaultFinalPriceExclTax, $discount, $store
        );
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
    protected function formatPrice(
        float $regularPrice,
        float $regularPriceExclTax,
        float $finalPrice,
        float $finalPriceExclTax,
        float $defaultRegularPrice,
        float $defaultFinalPrice,
        float $defaultFinalPriceExclTax,
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
            'default_price' => [
                'value' => $defaultRegularPrice,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'default_final_price' => [
                'value' => $defaultFinalPrice,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'default_final_price_excl_tax' => [
                'value' => $defaultFinalPriceExclTax,
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
            // Calculate percent_off with higher precision to avoid +/- 0.01 price differences on frontend
            $priceDifference = $regularPrice - $finalPrice;

            return [
                'amount_off' => $this->getPriceDifferenceAsValue($regularPrice, $finalPrice),
                'percent_off' => $this->getPriceDifferenceAsPercent($regularPrice, $finalPrice)
            ];
        }

        // Bundle products have special price set in % (percents)
        $specialPricePrecentage = $this->getSpecialProductPrice($product);
        $percentOff = is_null($specialPricePrecentage) ? 0 : 100 - $specialPricePrecentage;

        return [
            'amount_off' => $regularPrice * ($percentOff / 100),
            'percent_off' => $percentOff
        ];
    }

    /**
     * Get value difference between two prices
     *
     * @param float $regularPrice
     * @param float $finalPrice
     * @return float
     */
    protected function getPriceDifferenceAsValue(float $regularPrice, float $finalPrice)
    {
        $difference = $regularPrice - $finalPrice;
        if ($difference <= $this->zeroThreshold) {
            return 0;
        }

        return round($difference, 2);
    }

    /**
     * Get percent difference between two prices
     *
     * @param float $regularPrice
     * @param float $finalPrice
     * @return float
     */
    protected function getPriceDifferenceAsPercent(float $regularPrice, float $finalPrice)
    {
        $difference = $this->getPriceDifferenceAsValue($regularPrice, $finalPrice);

        if ($difference <= $this->zeroThreshold || $regularPrice <= $this->zeroThreshold) {
            return 0;
        }

        return round(($difference / $regularPrice) * 100, 8);
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

        return ($now >= $from && $now <= $to) || ($now >= $from && is_null($to)) ? (float)$specialPrice : null;
    }

    protected function isPriceIncludesTax(){
        return $this->scopeConfig->getValue(
            self::XML_PRICE_INCLUDES_TAX,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES
        );
    }
}
