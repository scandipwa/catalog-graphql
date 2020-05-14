<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Artjoms Travkovs <info@scandiweb.com>
 * @author      Aivars Arbidans <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */
declare (strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use ScandiPWA\CatalogGraphQl\Model\Resolver\Layer\DataProvider\Filters;

/**
 * Layered navigation filters resolver, used for GraphQL request processing.
 */
class LayerFilters implements ResolverInterface
{
    /**
     * @var Layer\DataProvider\Filters
     */
    private $filtersDataProvider;

    /**
     * @param Filters $filtersDataProvider
     */
    public function __construct(
        Filters $filtersDataProvider
    ) {
        $this->filtersDataProvider = $filtersDataProvider;
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
        if (!isset($value['layer_type'])) {
            return null;
        }

        $attributes = $this->prepareAttributesResults($value);

        return $this->filtersDataProvider->getData($value['layer_type'], $attributes);
    }

    /**
     * Get attributes available to filtering from the search result
     *
     * @param array $value
     * @return array|null
     */
    private function prepareAttributesResults(array $value): ?array
    {
        $attributes = [];

        if (!empty($value['search_result'])) {
            $buckets = $value['search_result']->getSearchAggregation()->getBuckets();
            foreach ($buckets as $bucket) {
                if (!empty($bucket->getValues())) {
                    $attributes[] = str_replace('_bucket', '', $bucket->getName());
                }
            }
        } else {
            $attributes = null;
        }

        return $attributes;
    }
}
