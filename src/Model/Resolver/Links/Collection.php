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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Links;

use Magento\Bundle\Model\Selection;
use Magento\Bundle\Model\ResourceModel\Selection\CollectionFactory;
use Magento\Bundle\Model\ResourceModel\Selection\Collection as LinkCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\GraphQl\Query\EnumLookup;
use ScandiPWA\Performance\Model\Resolver\Products\CollectionPostProcessor;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;
use ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait;
use Zend_Db_Select_Exception;

/**
 * Collection to fetch link data at resolution time.
 */
class Collection
{
    use ResolveInfoFieldsTrait;

    /** @var CollectionFactory */
    protected $linkCollectionFactory;

    /** @var EnumLookup */
    protected $enumLookup;

    /** @var int[] */
    protected $optionIds = [];

    /** @var int[] */
    protected $parentIds = [];

    /** @var array */
    protected $links = [];

    /** @var $resolveInfo */
    protected $resolveInfo;

    /** @var SearchCriteriaBuilder */
    protected $searchCriteriaBuilder;

    /** @var ProductCollectionFactory */
    protected $collectionFactory;

    /** @var CollectionProcessorInterface  */
    protected $collectionProcessor;

    /** @var CollectionPostProcessor  */
    protected $collectionPostProcessor;

    /** @var DataPostProcessor  */
    protected $dataPostProcessor;

    /**
     * Collection constructor.
     *
     * @param CollectionFactory $linkCollectionFactory
     * @param EnumLookup $enumLookup
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ProductCollectionFactory $collectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param CollectionPostProcessor $collectionPostProcessor
     * @param DataPostProcessor $dataPostProcessor
     */
    public function __construct(
        CollectionFactory $linkCollectionFactory,
        EnumLookup $enumLookup,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductCollectionFactory $collectionFactory,
        CollectionProcessorInterface $collectionProcessor,
        CollectionPostProcessor $collectionPostProcessor,
        DataPostProcessor $dataPostProcessor
    ) {
        $this->linkCollectionFactory = $linkCollectionFactory;
        $this->enumLookup = $enumLookup;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->collectionPostProcessor = $collectionPostProcessor;
        $this->dataPostProcessor = $dataPostProcessor;
    }

    /**
     * Add option and id filter pair to filter for fetch.
     *
     * @param int $optionId
     * @param int $parentId
     * @return void
     */
    public function addIdFilters(int $optionId, int $parentId) : void
    {
        if (!in_array($optionId, $this->optionIds)) {
            $this->optionIds[] = $optionId;
        }

        if (!in_array($parentId, $this->parentIds)) {
            $this->parentIds[] = $parentId;
        }
    }

    /**
     * @param $resolveInfo
     */
    public function addResolveInfo($resolveInfo) {
        $this->resolveInfo = $resolveInfo;
    }

    /**
     * Retrieve links for passed in option id.
     *
     * @param int $optionId
     * @return array
     * @throws RuntimeException
     * @throws Zend_Db_Select_Exception
     */
    public function getLinksForOptionId(int $optionId) : array
    {
        $linksList = $this->fetch();

        if (!isset($linksList[$optionId])) {
            return [];
        }

        return $linksList[$optionId];
    }

    /**
     * @param $productIds
     * @return array
     */
    protected function getProductMap($productIds): array
    {
        $attributeData = $this->getFieldsFromProductInfo(
            $this->resolveInfo,
            'options/product'
        );

        $collection = $this->collectionFactory->create();

        // build a search criteria based on original one and filter of product ids
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $productIds, 'in')
            ->create();

        $this->collectionProcessor->process(
            $collection,
            $searchCriteria,
            $attributeData
        );

        $collection->load();

        $this->collectionPostProcessor->process(
            $collection,
            $attributeData
        );

        return $this->dataPostProcessor->process(
            $collection->getItems(),
            'options/product',
            $this->resolveInfo
        );
    }

    /**
     * Fetch link data and return in array format. Keys for links will be their option Ids.
     *
     * @return array
     * @throws RuntimeException
     * @throws Zend_Db_Select_Exception
     */
    private function fetch() : array
    {
        if (empty($this->optionIds) || empty($this->parentIds) || !empty($this->links)) {
            return $this->links;
        }

        /** @var LinkCollection $linkCollection */
        $linkCollection = $this->linkCollectionFactory->create();
        $linkCollection->setOptionIdsFilter($this->optionIds);
        $field = 'parent_product_id';

        foreach ($linkCollection->getSelect()->getPart('from') as $tableAlias => $data) {
            if ($data['tableName'] === $linkCollection->getTable('catalog_product_bundle_selection')) {
                $field = $tableAlias . '.' . $field;
            }
        }

        $linkCollection->getSelect()
            ->where($field . ' IN (?)', $this->parentIds);

        $links = $linkCollection->getItems();
        $productIds = array_map(static function ($link) {
            /** @var Selection $link */
            return $link->getProductId();
        }, $links);

        $productMap = $this->getProductMap($productIds);

        /** @var Selection $link */
        foreach ($links as $link) {
            $data = $link->getData();
            $formattedLink = [
                'price' => $link->getSelectionPriceValue(),
                'position' => $link->getPosition(),
                'id' => $link->getSelectionId(),
                'qty' => (float)$link->getSelectionQty(),
                'quantity' => (float)$link->getSelectionQty(),
                'is_default' => (bool)$link->getIsDefault(),
                'price_type' => $this->enumLookup->getEnumValueFromField(
                    'PriceTypeEnum',
                    (string)$link->getSelectionPriceType()
                ) ?: 'DYNAMIC',
                'can_change_quantity' => $link->getSelectionCanChangeQty(),
                'product' => $productMap[$link->getProductId()]
            ];

            $data = array_replace($data, $formattedLink);

            if (!isset($this->links[$link->getOptionId()])) {
                $this->links[$link->getOptionId()] = [];
            }

            $this->links[$link->getOptionId()][] = $data;
        }

        return $this->links;
    }
}
