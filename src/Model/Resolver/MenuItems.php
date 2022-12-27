<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Denis Protassoff <info@scandiweb.com>
 * @copyright   Copyright (c) 2022 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Helper\Category as CategoryHelper;
use Magento\Catalog\Model\ResourceModel\Category\StateDependentCollectionFactory;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Data\Collection;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class MenuItems
 *
 * @package ScandiPWA\CatalogGraphQl\Model\Resolver
 */
class MenuItems implements ResolverInterface
{
    /**
     * @var CategoryHelper
     */
    protected $catalogCategory;

    /**
     * @var StateDependentCollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @param CategoryHelper $catalogCategory
     * @param StateDependentCollectionFactory $categoryCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        CategoryHelper $catalogCategory,
        StateDependentCollectionFactory $categoryCollectionFactory,
        StoreManagerInterface $storeManager,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->catalogCategory = $catalogCategory;
        $this->collectionFactory = $categoryCollectionFactory;
        $this->storeManager = $storeManager;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * Standard Magento menu logic
     * Magento\Catalog\Plugin\Block\Topmenu
     *
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $rootId = $this->storeManager->getStore()->getRootCategoryId();
        $storeId = $this->storeManager->getStore()->getId();

        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $collection = $this->getCategoryTree($storeId, $rootId);
        // Creating root category for FE compatibility
        $mapping = [
            [
                'category_id' => 0,
                'item_id' => $rootId,
                'parent_id' => 0,
                'title' => '',
                'url' => '/'
            ]
        ];

        foreach ($collection as $category) {
            $categoryParentId = $category->getParentId();
            if (!isset($mapping[$categoryParentId])) {
                $parentIds = $category->getParentIds();
                foreach ($parentIds as $parentId) {
                    if (isset($mapping[$parentId])) {
                        $categoryParentId = $parentId;
                    }
                }
            }

            $categoryArray = $this->getCategoryAsArray($category);
            $mapping[$category->getId()] = $categoryArray;
        }

        return array_values($mapping);
    }

    /**
     * Get Category Tree
     *
     * @param int $storeId
     * @param int $rootId
     * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getCategoryTree($storeId, $rootId)
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect('name');
        $collection->addFieldToFilter('path', ['like' => '1/' . $rootId . '/%']); //load only from store root
        $collection->addAttributeToFilter('include_in_menu', 1);
        $collection->addIsActiveFilter();
        $collection->addNavigationMaxDepthFilter();
        $collection->addUrlRewriteToResult();
        $collection->addOrder('level', Collection::SORT_ORDER_ASC);
        $collection->addOrder('position', Collection::SORT_ORDER_ASC);
        $collection->addOrder('parent_id', Collection::SORT_ORDER_ASC);
        $collection->addOrder('entity_id', Collection::SORT_ORDER_ASC);

        return $collection;
    }

    /**
     * Convert category to array
     *
     * @param \Magento\Catalog\Model\Category $category
     * @param int $itemId
     * @return array
     */
    protected function getCategoryAsArray($category)
    {
        return [
            'title' => $category->getName(),
            'item_id' => $category->getId(),
            'category_id' => $category->getId(),
            'url' => $this->catalogCategory->getCategoryUrl($category),
            'parent_id' => $category->getParentId(),
            'position' => $category->getPosition(),
            // For correct placeholders on FE
            // Default value is Products because if
            // display mode wasn't changed it returns null
            'display_mode' => $this->getCategoryDisplayMode($category) ?? Category::DM_PRODUCT
        ];
    }

    /**
     * Get category display mode
     * $category from collection doesn't have this
     *
     * @param Category $categoryData
     * @return string
     */
    protected function getCategoryDisplayMode($categoryData)
    {
        $category = $this->categoryRepository->get($categoryData->getId());

        return $category->getDisplayMode();
    }
}
