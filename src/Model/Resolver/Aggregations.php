<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver;

use Magento\CatalogGraphQl\Model\Resolver\Aggregations as AggregationsBase;
use Magento\CatalogGraphQl\Model\Resolver\Layer\DataProvider\Filters;
use Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\LayerBuilder;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;

class Aggregations extends AggregationsBase {
    /**
     * @var Attribute
     */
    protected $attribute;

    /**
     * Code of the price attribute in aggregations
     */
    public const PRICE_ATTR_CODE = 'price';

    /**
     * Code of the category id in aggregations
     */
    public const CATEGORY_ID_CODE = 'category_id';

    /**
     * @inheritdoc
     */
    public function __construct(
        Filters $filtersDataProvider,
        LayerBuilder $layerBuilder,
        Attribute $attribute
    )
    {
        parent::__construct(
            $filtersDataProvider,
            $layerBuilder
        );

        $this->attribute = $attribute;
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
        $result = parent::resolve($field, $context, $info, $value);

        $result = $this->processPriceFilter($result);
        $result = $this->enhanceAttributes($result);

        return $result;
    }

    /**
     * Process filters and set price filter last option value so it has no upper bound
     * @param array $result Filters
     * @return array
     */
    protected function processPriceFilter(array $result): array {
        return array_map(function ($item) {
            if ($item['attribute_code'] === self::PRICE_ATTR_CODE) {
                $lastIdx = count($item['options']) - 1;

                foreach ($item['options'] as $index => $option) {
                    if ($lastIdx != 0 && $index == $lastIdx) {
                        $item['options'][$index]['label'] = preg_replace('/(\d+)~\d+/', '$1~*', $option['label']);
                        $item['options'][$index]['value'] = preg_replace('/(\d+)_\d+/', '$1_*', $option['value']);
                    } else {
                        $item['options'][$index]['label'] = preg_replace_callback('/(\d+~)(\d+)/', function ($matches) {
                            return $matches[1].($matches[2]-0.01);
                        }, $option['label']);
                        $item['options'][$index]['value'] = preg_replace_callback('/(\d+_)(\d+)/', function ($matches) {
                            return $matches[1].($matches[2]-0.01);
                        }, $option['value']);
                    }
                }
            }

            return $item;
        }, $result);
    }

    /**
     * Process options and replace '1' and '0' labels for options having boolean type.
     * @param array $result Filters
     * @return array
     */
    protected function enhanceAttributes(array $result): array {
        foreach ($result as $attr => $attrGroup) {
            // Category ID is not a real attribute in Magento, so needs special handling
            if($attrGroup['attribute_code'] == self::CATEGORY_ID_CODE){
                $result[$attr]['is_boolean'] = false;
                $result[$attr]['position'] = 0;
                $result[$attr]['has_swatch'] = false;
                continue;
            }

            $attribute = $this->attribute->loadByCode('catalog_product', $attrGroup['attribute_code']);

            // Add flag to indicate that attribute is boolean (Yes/No, Enable/Disable, etc.)
            $result[$attr]['is_boolean'] = $attribute->getFrontendInput() === 'boolean';

            // Set position in the filters list
            $result[$attr]['position'] = $attribute->getPosition();

            // Add flag to indicate that attribute has swatch values (required to properly handle edge cases with removed colors/inconsistent data)
            $additionalData = $attribute->getAdditionalData();
            if(is_null($additionalData)){
                $result[$attr]['has_swatch'] = false;
            } else {
                $additionalDataParsed = json_decode($additionalData, true);
                $result[$attr]['has_swatch'] = isset($additionalDataParsed['swatch_input_type']);
            }
        }

        return $result;
    }
}
