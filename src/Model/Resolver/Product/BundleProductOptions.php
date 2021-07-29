<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Product;

use Magento\Bundle\Model\Product\Price;
use Magento\Bundle\Model\Product\Type as Bundle;
use Magento\Catalog\Helper\Data as CatalogData;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Query\EnumLookup;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;

/**
 * @inheritdoc
 */
class BundleProductOptions implements ResolverInterface
{
    /**
     * Catalog data
     *
     * @var CatalogData
     */
    protected $catalogData;

    /**
     * @var EnumLookup
     */
    private $enumLookup;

    /**
     * @var PriceCurrencyInterface
     */
    private PriceCurrencyInterface $priceCurrency;

    /**
     * @param CatalogData $catalogData
     * @param EnumLookup $enumLookup
     */
    public function __construct(
        EnumLookup $enumLookup,
        CatalogData $catalogData,
        PriceCurrencyInterface $priceCurrency
    )
    {
        $this->enumLookup = $enumLookup;
        $this->catalogData = $catalogData;
        $this->priceCurrency = $priceCurrency;
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

        /** @var \Magento\Bundle\Model\Product\Type $product */
        $bundleProduct = $value['model'];

        if($bundleProduct->getTypeId() !== Bundle::TYPE_CODE){
            return [];
        }

        /** @var \Magento\Bundle\Model\Product\Price $priceModel */
        $priceModel = $bundleProduct->getPriceModel();

        $result = [];

        /** @var \Magento\Bundle\Model\Option $bundleOption */
        foreach ($priceModel->getOptions($bundleProduct) as $bundleOption) {

            $selectionsResult = [];

            /* @var \Magento\Bundle\Model\Selection $optionSelection */
            foreach (($bundleOption->getSelections() ?? []) as $optionSelection) {
                // For bundle with fix price taxes are calculated based on the bundle product itself
                // For bundle with dynamic price taxes are calculated based on the referenced products
                $taxableItem = $bundleProduct->getPriceType() == Price::PRICE_TYPE_FIXED ? $bundleProduct : $optionSelection;

                $selectionPrice = $priceModel->getSelectionPrice($bundleProduct, $optionSelection, 1);
                $selectionPriceInclTax = $this->catalogData->getTaxPrice(
                    $taxableItem, $selectionPrice, true, null, null, null, null, null, false
                );
                $selectionPriceExclTax = $this->catalogData->getTaxPrice(
                    $taxableItem, $selectionPrice, false, null, null, null, null, null, false
                );

                $selectionPriceType = $this->enumLookup->getEnumValueFromField(
                    'PriceTypeEnum',
                    (string) $optionSelection->getSelectionPriceType()
                ) ?: 'DYNAMIC';

                $regularPrice = $bundleProduct->getPriceType() == Price::PRICE_TYPE_FIXED
                    ? $selectionPriceType == 'PERCENT'
                        ? ($bundleProduct->getPrice() * ($optionSelection->getSelectionPriceValue() / 100))
                        : $optionSelection->getSelectionPriceValue()
                    : $optionSelection->getPrice();

                $regularPriceInclTax = $this->catalogData->getTaxPrice($taxableItem, $regularPrice, true);
                $regularPriceExclTax = $this->catalogData->getTaxPrice($taxableItem, $regularPrice, false);

                $selectionsResult[] = [
                    'selection_id' => $optionSelection->getSelectionId(),
                    'name' => $optionSelection->getName(),
                    'regular_option_price' => $this->priceCurrency->convert($regularPriceInclTax),
                    'regular_option_price_excl_tax' => $this->priceCurrency->convert($regularPriceExclTax),
                    'final_option_price' => $this->priceCurrency->convert($selectionPriceInclTax),
                    'final_option_price_excl_tax' => $this->priceCurrency->convert($selectionPriceExclTax)
                ];
            }

            $result[] = [
                'option_id' => $bundleOption->getId(),
                'selection_details' => $selectionsResult
            ];
        }

        return $result;
    }
}
