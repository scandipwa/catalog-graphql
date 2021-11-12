<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @copyright   Copyright (c) 2021 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\Search\DocumentFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\Search\SearchResultFactory as FrameworkSearchResultFactory;

/**
 * Class EmulateSearchResult
 * @package ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product
 */
class EmulateSearchResult
{
    /**
     * @var AttributeValueFactory
     */
    protected $attributeValueFactory;

    /**
     * @var DocumentFactory
     */
    protected $documentFactory;

    /**
     * @var FrameworkSearchResultFactory
     */
    protected $frameworkSearchResultFactory;

    /**
     * EmulateSearchResult constructor.
     * @param AttributeValueFactory $attributeValueFactory
     * @param DocumentFactory $documentFactory
     * @param FrameworkSearchResultFactory $frameworkSearchResultFactory
     */
    public function __construct(
        AttributeValueFactory $attributeValueFactory,
        DocumentFactory $documentFactory,
        FrameworkSearchResultFactory $frameworkSearchResultFactory
    ) {
        $this->attributeValueFactory = $attributeValueFactory;
        $this->documentFactory = $documentFactory;
        $this->frameworkSearchResultFactory = $frameworkSearchResultFactory;
    }

    /**
     * Emulates ES search repsonse for specific ID query
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultInterface
     */
    public function execute(SearchCriteriaInterface $searchCriteria): SearchResultInterface
    {
        $idFilterValue = self::getIdFilterValue($searchCriteria);

        $scoreAttribute = $this->attributeValueFactory->create();
        $scoreAttribute->setAttributeCode('_score');
        $scoreAttribute->setValue(null);

        $document = $this->documentFactory->create();
        $document->setId($idFilterValue);
        $document->setCustomAttribute('score', $scoreAttribute);

        /** @var SearchResultInterface $itemsResults */
        $itemsResults = $this->frameworkSearchResultFactory->create();
        $itemsResults->setItems([$document]);
        $itemsResults->setTotalCount(1);
        $itemsResults->setSearchCriteria($searchCriteria);

        return $itemsResults;
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return int|null
     */
    static public function getIdFilterValue(SearchCriteriaInterface $searchCriteria)
    {
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $filters = $filterGroup->getFilters();

            foreach ($filters as $filter) {
                $type = $filter->getConditionType();
                $field = $filter->getField();

                if ($type === 'eq' && $field === 'id') {
                    return $filter->getValue();
                }
            }
        }

        return null;
    }
}
