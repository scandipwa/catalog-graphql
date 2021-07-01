<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      <info@scandiweb.com>
 * @copyright   Copyright (c) 2021 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Product;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Catalog\Helper\Data as CatalogData;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\CatalogGraphQl\Model\Resolver\Product\Options as CoreOptions;
use Magento\Framework\GraphQl\Config\Element\Field;

/**
 * Format a product's option information to conform to GraphQL schema representation
 */
class Options extends CoreOptions
{
    /**
     * @var PriceCurrencyInterface
     */
    protected PriceCurrencyInterface $priceCurrency;

    /**
     * @var CatalogData
     */
    protected $catalogData;

    /**
     * @param PriceCurrencyInterface $priceCurrency,
     * @param CatalogData $catalogData
     */
    public function __construct(
        PriceCurrencyInterface $priceCurrency,
        CatalogData $catalogData
    )
    {
        $this->priceCurrency = $priceCurrency;
        $this->catalogData = $catalogData;
    }

    /**
     * @inheritdoc
     *
     * Format product's option data to conform to GraphQL schema
     *
     * @param \Magento\Framework\GraphQl\Config\Element\Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @throws \Exception
     * @return null|array
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
        $store = $context->getExtensionAttributes()->getStore();
        $currentCurrency = $store->getCurrentCurrencyCode();

        $options = null;
        if (!empty($product->getOptions())) {
            $options = [];
            /** @var Option $option */
            foreach ($product->getOptions() as $key => $option) {
                $options[$key] = $option->getData();
                $options[$key]['required'] = $option->getIsRequire();
                $options[$key]['product_sku'] = $option->getProductSku();

                $values = $option->getValues() ?: [];

                /** @var Option\Value $optionValue */
                foreach ($values as $valueKey => $optionValue) {
                    $options[$key]['value'][$valueKey] = $optionValue->getData();
                    $options[$key]['value'][$valueKey]['price_type']
                        = $optionValue->getPriceType() !== null ? strtoupper($optionValue->getPriceType()) : 'DYNAMIC';

                    $selectionPrice = $options[$key]['value'][$valueKey]['price'];
                    $options[$key]['value'][$valueKey]['price'] = $selectionPrice;
                    $options[$key]['value'][$valueKey]['currency'] = $currentCurrency;

                    // Calculate price including tax for option value
                    $taxablePrice = strtoupper($optionValue->getPriceType()) == 'PERCENT'
                        ? $product->getFinalPrice() * $selectionPrice / 100
                        : $selectionPrice;
                    $taxablePrice = $this->priceCurrency->convert($taxablePrice);

                    $options[$key]['value'][$valueKey]['priceInclTax'] = $this->catalogData->getTaxPrice(
                        $product, $taxablePrice, true, null, null, null, null, null, null
                    );
                    $options[$key]['value'][$valueKey]['priceExclTax'] = $this->catalogData->getTaxPrice(
                        $product, $taxablePrice, false, null, null, null, null, null, null
                    );
                }

                if (empty($values)) {
                    $options[$key]['value'] = $option->getData();
                    $options[$key]['value']['price_type']
                        = $option->getPriceType() !== null ? strtoupper($option->getPriceType()) : 'DYNAMIC';

                    $selectionPrice = $options[$key]['value']['price'];
                    $options[$key]['value']['price'] = $selectionPrice;
                    $options[$key]['value']['currency'] = $currentCurrency;

                    // Calculate price including tax for option value
                    $taxablePrice = strtoupper($option->getPriceType()) == 'PERCENT'
                        ? $product->getFinalPrice() * $selectionPrice / 100
                        : $selectionPrice;
                    $taxablePrice = $this->priceCurrency->convert($taxablePrice);

                    $options[$key]['value']['priceInclTax'] = $this->catalogData->getTaxPrice(
                        $product, $taxablePrice, true, null, null, null, null, null, null
                    );
                    $options[$key]['value']['priceExclTax'] = $this->catalogData->getTaxPrice(
                        $product, $taxablePrice, false, null, null, null, null, null, null
                    );
                }
            }
        }

        return $options;
    }
}
