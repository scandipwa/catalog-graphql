<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Artjoms Travkovs <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\CatalogGraphQl\Helper;

class FiltersHelper
{
    private $filters = [];

    public function addFilter($filterName, $filterValues)
    {
        $this->filters[$filterName] = $filterValues;
    }

    public function getFilters()
    {
        return $this->filters;
    }
}
