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

use Magento\Catalog\Pricing\Price\BasePrice;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Catalog\Helper\Data as CatalogData;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\CatalogGraphQl\Model\Resolver\Product\Options as CoreOptions;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Model\Product\Option\Value as OptionValue;
use Magento\Catalog\Pricing\Price\CalculateCustomOptionCatalogRule;

/**
 * Format a product's option information to conform to GraphQL schema representation
 */
class Options extends CoreOptions
{
    protected const OPTION_TYPE = 'custom-option';
    protected const DYNAMIC_TYPE = 'DYNAMIC';
    
    /**
     * @var PriceCurrencyInterface
     */
    protected PriceCurrencyInterface $priceCurrency;

    /**
     * @var CatalogData
     */
    protected $catalogData;

    /** @var Uid */
    protected $uidEncoder;

    /**
     * @var CalculateCustomOptionCatalogRule
     */
    protected $calculateCustomOptionCatalogRule;

    /**
     * @param PriceCurrencyInterface $priceCurrency
     * @param CatalogData $catalogData
     * @param Uid|null $uidEncoder
     * @param CalculateCustomOptionCatalogRule|null $calculateCustomOptionCatalogRule
     */
    public function __construct(
        PriceCurrencyInterface $priceCurrency,
        CatalogData $catalogData,
        Uid $uidEncoder = null,
        CalculateCustomOptionCatalogRule $calculateCustomOptionCatalogRule
    )
    {
        $this->calculateCustomOptionCatalogRule = $calculateCustomOptionCatalogRule;
        $this->priceCurrency = $priceCurrency;
        $this->catalogData = $catalogData;
        $this->uidEncoder = $uidEncoder ?: ObjectManager::getInstance()
            ->get(Uid::class);
    }

    /**
     * @param $price
     * @param $isPercent
     * @param $product
     * @return float
     */
    public function getPrice($price, $isPercent, $product)
    {
        $catalogPriceValue = $this->calculateCustomOptionCatalogRule->execute(
            $product,
            (float)$price,
            $isPercent
        );

        if ($catalogPriceValue!==null) {
            return $catalogPriceValue;
        }

        return $price;
    }

    /**
     * @param array $optonArray
     * @param $optionValue
     * @param $product
     * @param $currentCurrency
     */
    public function updateOptionPriceData(array &$optionArray, $optionValue, $product, $currentCurrency) {
        $optionArray['price_type'] = $optionValue->getPriceType() !== null
            ? strtoupper($optionValue->getPriceType())
            : self::DYNAMIC_TYPE;
        $optionArray['price'] = $this->getPrice(
            $optionArray['price'],
            strtolower($optionValue->getPriceType()) == OptionValue::TYPE_PERCENT,
            $product
        );

        $selectionPrice = $optionArray['price'];
        $optionArray['currency'] = $currentCurrency;

        // Calculate price including tax for option value
        $taxablePrice = strtolower($optionValue->getPriceType()) == OptionValue::TYPE_PERCENT
            ? $product->getFinalPrice() * $selectionPrice / 100
            : $selectionPrice;
        $taxablePrice = $this->priceCurrency->convert($taxablePrice);

        $optionArray['priceInclTax'] = $this->catalogData->getTaxPrice(
            $product, $taxablePrice, true, null, null, null, null, null, null
        );
        $optionArray['priceExclTax'] = $this->catalogData->getTaxPrice(
            $product, $taxablePrice, false, null, null, null, null, null, null
        );
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
                $options[$key]['uid'] = $this->uidEncoder->encode(
                    self::OPTION_TYPE . '/' . $option->getOptionId()
                );

                $values = $option->getValues() ?: [];

                /** @var Option\Value $optionValue */
                foreach ($values as $valueKey => $optionValue) {
                    $options[$key]['value'][$valueKey] = $optionValue->getData();
                    $this->updateOptionPriceData(
                        $options[$key]['value'][$valueKey], $optionValue, $product, $currentCurrency
                    );
                }

                if (empty($values)) {
                    $options[$key]['value'] = $option->getData();
                    $this->updateOptionPriceData($options[$key]['value'], $option, $product, $currentCurrency);
                }
            }
        }

        return $options;
    }
}
