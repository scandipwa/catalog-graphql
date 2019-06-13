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

use Magento\Framework\ObjectManagerInterface;

/**
 * Generate SearchResult based off of total count from query and array of products and their data.
 * This class adds support for min and max price
 */
class SearchResultFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Instantiate SearchResult
     *
     * @param int $totalCount
     * @param float $minPrice
     * @param float $maxPrice
     * @param array $productsSearchResult
     * @return SearchResult
     */
    public function create(
        int $totalCount,
        float $minPrice,
        float $maxPrice,
        array $productsSearchResult
    ) : SearchResult
    {
        return $this->objectManager->create(
            SearchResult::class,
            [
                'totalCount' => $totalCount,
                'minPrice' => $minPrice,
                'maxPrice' => $maxPrice,
                'productsSearchResult' => $productsSearchResult
            ]
        );
    }
}
