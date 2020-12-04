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
                $lastOpt = $item['options'][$lastIdx];
                $lastOpt['label'] = preg_replace('/-\d+/', '', $lastOpt['label']);
                $lastOpt['value'] = preg_replace('/(\d+)_\d+/', '$1_*', $lastOpt['value']);
                $item['options'][$lastIdx] = $lastOpt;
            }

            return $item;
        }, $result);
    }
}
