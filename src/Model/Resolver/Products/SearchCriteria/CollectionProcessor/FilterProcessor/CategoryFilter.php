<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Viktors Pliska <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\SearchCriteria\CollectionProcessor\FilterProcessor;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryResourceCollection;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\SearchCriteria\CollectionProcessor\FilterProcessor\CustomFilterInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;

/**
 * Category filter allows to filter products collection using custom defined filters from search criteria.
 */
class CategoryFilter implements CustomFilterInterface
{
    /**
     * @var CategoryFactory
     */
    private $categoryFactory;

    /**
     * @var CategoryResourceModel
     */
    private $categoryResourceModel;

    /**
     * @var CategoryResourceCollection
     */
    private $categoryResourceCollection;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * CategoryFilter constructor.
     * @param CategoryFactory $categoryFactory
     * @param CategoryResourceModel $categoryResourceModel
     * @param CategoryResourceCollection $categoryResourceCollection
     * @param Registry $registry
     */
    public function __construct(
        CategoryFactory $categoryFactory,
        CategoryResourceModel $categoryResourceModel,
        CategoryResourceCollection $categoryResourceCollection,
        Registry $registry
    ) {
        $this->categoryFactory = $categoryFactory;
        $this->categoryResourceModel = $categoryResourceModel;
        $this->categoryResourceCollection = $categoryResourceCollection;
        $this->registry = $registry;
    }

    /**
     * Apply filter by custom field to product collection.
     *
     * For anchor categories, the products from all children categories will be present in the result.
     *
     * @param Filter $filter
     * @param AbstractDb $collection
     * @return bool Whether the filter is applied
     * @throws LocalizedException
     */
    public function apply(Filter $filter, AbstractDb $collection)
    {
        if ($this->registry->registry('current_category')) {
            return true;
        }

        $conditionType = $filter->getConditionType();
        $rawFilterField = $filter->getField();

        if ($conditionType !== 'eq') {
            throw new LocalizedException(__($rawFilterField . " only supports 'eq' condition type."));
        }

        $filterField = str_replace("category_", "", $rawFilterField);
        $filterValue = $filter->getValue();

        /** @var Collection $collection */
        $category = $this->categoryFactory->create();

        if ($filterField !== 'id') {
            $categoryId = $this->categoryResourceCollection
                ->addAttributeToFilter($filterField, $filterValue)
                ->addAttributeToSelect(['entity_id'])
                ->getFirstItem()
                ->getEntityId();

            $this->categoryResourceModel->load($category, $categoryId);
        } else {
            $this->categoryResourceModel->load($category, $filterValue);
        }

        $this->registry->register('current_category', $category);
        $collection->addCategoryFilter($category);

        return true;
    }
}
