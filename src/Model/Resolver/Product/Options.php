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
    private PriceCurrencyInterface $priceCurrency;

    public function __construct(
        PriceCurrencyInterface $priceCurrency
    )
    {
        $this->priceCurrency = $priceCurrency;
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

                /** @var Option\Value $value */
                foreach ($values as $valueKey => $value) {
                    $options[$key]['value'][$valueKey] = $value->getData();
                    $options[$key]['value'][$valueKey]['price_type']
                        = $value->getPriceType() !== null ? strtoupper($value->getPriceType()) : 'DYNAMIC';

                    $options[$key]['value'][$valueKey]['price']
                        = $this->priceCurrency->convert($options[$key]['value'][$valueKey]['price']);
                    $options[$key]['value'][$valueKey]['currency'] = $currentCurrency;
                }

                if (empty($values)) {
                    $options[$key]['value'] = $option->getData();
                    $options[$key]['value']['price_type']
                        = $option->getPriceType() !== null ? strtoupper($option->getPriceType()) : 'DYNAMIC';

                    $options[$key]['value']['price']
                        = $this->priceCurrency->convert($options[$key]['value']['price']);
                    $options[$key]['value']['currency'] = $currentCurrency;
                }
            }
        }

        return $options;
    }
}
