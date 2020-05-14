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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Layer\DataProvider;

use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\CatalogGraphQl\Model\Resolver\Layer\DataProvider\Filters as CoreFilters;
use Magento\CatalogGraphQl\Model\Resolver\Layer\FiltersProvider;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Framework\Exception\LocalizedException;

/**
 * Layered navigation filters data provider.
 */
class Filters extends CoreFilters
{
    /**
     * @var FiltersProvider
     */
    private $filtersProvider;

    /**
     * @var array
     */
    private $mappings;

    /**
     * Filters constructor.
     *
     * @param FiltersProvider $filtersProvider
     */
    public function __construct(
        FiltersProvider $filtersProvider
    ) {
        $this->filtersProvider = $filtersProvider;
        $this->mappings = [
            'Category' => 'category'
        ];
    }

    /**
     * Get layered navigation filters data
     *
     * @param string $layerType
     * @param array|null $attributesToFilter
     * @return array
     * @throws LocalizedException
     */
    public function getData(string $layerType, array $attributesToFilter = null): array
    {
        $filtersData = [];
        /** @var AbstractFilter $filter */
        foreach ($this->filtersProvider->getFilters($layerType) as $filter) {
            if ($this->isNeedToAddFilter($filter, $attributesToFilter)) {
                $filterGroup = [
                    'name' => (string) $filter->getName(),
                    'filter_items_count' => $filter->getItemsCount(),
                    'request_var' => $filter->getRequestVar(),
                ];
                /** @var Item $filterItem */
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
     * Check for adding filter to the list
     *
     * @param AbstractFilter $filter
     * @param  $attributesToFilter
     * @return bool
     * @throws LocalizedException
     */
    private function isNeedToAddFilter(AbstractFilter $filter, $attributesToFilter): bool
    {
        if ($attributesToFilter === null) {
            $result = (bool)$filter->getItemsCount();
        } else {
            if ($filter->hasAttributeModel()) {
                $filterAttribute = $filter->getAttributeModel();
                $result = in_array($filterAttribute->getAttributeCode(), $attributesToFilter);
            } else {
                $name = (string)$filter->getName();
                if (array_key_exists($name, $this->mappings)) {
                    $result = in_array($this->mappings[$name], $attributesToFilter);
                } else {
                    $result = true;
                }
            }
        }

        return $result;
    }
}
