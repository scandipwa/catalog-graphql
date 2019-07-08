<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Raivis Dejus <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products;

use phpDocumentor\Reflection\Types\Float_;

/**
 * Container for a product search holding the item result and the array in the GraphQL-readable product type format.
 * This class adds support for min and max price
 */
class SearchResult
{
    /**
     * @var int
     */
    private $totalCount;

    /**
     * @var float
     */
    private $minPrice;

    /**
     * @var float
     */
    private $maxPrice;

    /**
     * @var array
     */
    private $productsSearchResult;

    /**
     * @param int $totalCount
     * @param float $minPrice
     * @param float $maxPrice
     * @param array $productsSearchResult
     */
    public function __construct(
        int $totalCount,
        float $minPrice,
        float $maxPrice,
        array $productsSearchResult
    ) {
        $this->totalCount = $totalCount;
        $this->minPrice = $minPrice;
        $this->maxPrice = $maxPrice;
        $this->productsSearchResult = $productsSearchResult;
    }

    /**
     * Return total count of search and filtered result
     *
     * @return int
     */
    public function getTotalCount() : int
    {
        return $this->totalCount;
    }

    /**
     * Return min price of search and filtered result
     *
     * @return float
     */
    public function getMinPrice() : float
    {
        return $this->minPrice;
    }

    /**
     * Return max price of search and filtered result
     *
     * @return float
     */
    public function getMaxPrice() : float
    {
        return $this->maxPrice;
    }

    /**
     * Retrieve an array in the format of GraphQL-readable type containing product data.
     *
     * @return array
     */
    public function getProductsSearchResult() : array
    {
        return $this->productsSearchResult;
    }
}
