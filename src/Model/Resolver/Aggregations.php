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
use Magento\Store\Api\Data\StoreInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;

class Aggregations extends AggregationsBase {

    /**
     * @var Attribute
     */
    private Attribute $attribute;

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

    const PRICE_ATTR_CODE = 'price';

    protected array $booleanLabels = [
        0 => 'No',
        1 => 'Yes'
    ];

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
        $result = $this->normalizeBooleanLabels($result);
        return $result;
    }

    /**
     * Process filters and set price filter last option value so it has no upper bound
     * @param array $result Filters
     * @return array
     */
    private function processPriceFilter(array $result): array {
        return array_map(function ($item) {
            if ($item['attribute_code'] === self::PRICE_ATTR_CODE) {
                $lastIdx = count($item['options']) - 1;
                $lastOpt = $item['options'][$lastIdx];
                $lastOpt['label'] = preg_replace('/-\d+/', '', $lastOpt['label']);
                $lastOpt['value'] = preg_replace('/(\d+)_\d+/', '$1_*', $lastOpt['value']);
                $item['options'][$lastIdx] = $lastOpt;
            }

            return $item;
        }, $result);
    }

    /**
     * Process options and replace '1' and '0' labels for options having boolean type.
     * @param array $result Filters
     * @return array
     */
    private function normalizeBooleanLabels(array $result): array {
        foreach ($result as $attr => $attrGroup) {
            $attrType = $this->attribute->loadByCode('catalog_product', $attrGroup['attribute_code'])->getFrontendInput();
            if ($attrType == 'boolean') {
                foreach ($attrGroup['options'] as $option => $attrOption){
                    $result[$attr]['options'][$option]['label'] = $this->booleanLabels[$attrOption['value']];
                }
            }
        }
        return $result;
    }
}
