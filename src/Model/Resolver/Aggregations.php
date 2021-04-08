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
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;

class Aggregations extends AggregationsBase {
    const PRICE_ATTR_CODE = 'price';

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
}
