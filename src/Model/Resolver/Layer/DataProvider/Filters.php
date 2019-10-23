<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Artjoms Travkovs <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */
declare (strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Layer\DataProvider;

use Magento\CatalogGraphQl\Model\Resolver\Layer\FiltersProvider;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;

/**
 * Layered navigation filters data provider.
 */
class Filters extends \Magento\CatalogGraphQl\Model\Resolver\Layer\DataProvider\Filters
{
    /**
     * @var FiltersProvider
     */
    private $filtersProvider;

    /**
     * @var array
     */
    private $requiredAttributes = [];

    /**
     * Filters constructor.
     * @param FiltersProvider $filtersProvider
     */
    public function __construct(
        FiltersProvider $filtersProvider
    ) {
        $this->filtersProvider = $filtersProvider;
    }

    /**
     * Sets attributes that are being used by products
     *
     * @param array $items
     * @return void
     */
    public function setRequiredAttributesFromItems(array $items)
    {
        $attributes = [];

        foreach ($items as $item) {
            $model = $item['model'];

            $itemAttributeCodes = [];
            foreach ($model->getAttributes() as $attribute) {
                if ($attribute->getIsFilterable()) {
                    $itemAttributeCodes[] = $attribute->getAttributeCode();
                }
            }

            $attributes = array_unique(array_merge($attributes, $itemAttributeCodes));
        }

        $this->requiredAttributes = $attributes;
    }

    /**
     * Get layered navigation filters data
     *
     * @param string $layerType
     * @return array
     */
    public function getData(string $layerType): array
    {
        $filtersData = [];
        /** @var AbstractFilter $filter */
        foreach ($this->filtersProvider->getFilters($layerType) as $filter) {
            if ($filter->getItemsCount() && $this->hasFilterContents($filter)) {
                $filterGroup = [
                    'name' => (string) $filter->getName(),
                    'filter_items_count' => $filter->getItemsCount(),
                    'request_var' => $filter->getRequestVar(),
                ];
                /** @var \Magento\Catalog\Model\Layer\Filter\Item $filterItem */
                foreach ($filter->getItems() as $filterItem) {
                    $filterGroup['filter_items'][] = [
                        'label' => (string) $filterItem->getLabel(),
                        'value_string' => $filterItem->getValueString(),
                        'items_count' => $filterItem->getCount(),
                    ];
                }
                $filtersData[] = $filterGroup;
            }
        }

        return $filtersData;
    }

    /**
     * Checks whether filter attribute is present in products
     *
     * @param AbstractFilter $filter
     * @return boolean
     */
    protected function hasFilterContents(AbstractFilter $filter)
    {
        if (count($this->requiredAttributes) <= 0) {
            return true;
        }

        return in_array($filter->getRequestVar(), $this->requiredAttributes);
    }
}
