<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Scandiweb_CatalogGraphQl
 *
 * @category    Scandiweb
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\CatalogGraphQl\Model\Layer\Filter;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Filter\DataProvider\Category as CategoryDataProvider;
use Magento\Catalog\Model\Layer\Filter\DataProvider\CategoryFactory;
use Magento\Catalog\Model\Layer\Filter\Item\DataBuilder;
use Magento\Catalog\Model\Layer\Filter\ItemFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Layer category filter
 */
class Category extends AbstractFilter
{
    /**
     * Active Category Id
     *
     * @var int
     */
    protected $_categoryId;

    /**
     * Applied Category
     *
     * @var \Magento\Catalog\Model\Category
     */
    protected $_appliedCategory;

    /**
     * Core data
     *
     * @var Escaper
     */
    protected $_escaper;

    /**
     * Core registry
     *
     * @var Registry
     */
    protected $_coreRegistry;

    /**
     * @var CategoryDataProvider
     */
    private $dataProvider;

    /**
     * @var CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * Construct
     *
     * @param ItemFactory $filterItemFactory
     * @param StoreManagerInterface $storeManager
     * @param Layer $layer
     * @param DataBuilder $itemDataBuilder
     * @param Escaper $escaper
     * @param CategoryFactory $categoryDataProviderFactory
     * @param CollectionFactory $categoryCollectionFactory
     * @param array $data
     * @throws LocalizedException
     */
    public function __construct(
        ItemFactory $filterItemFactory,
        StoreManagerInterface $storeManager,
        Layer $layer,
        DataBuilder $itemDataBuilder,
        Escaper $escaper,
        CategoryFactory $categoryDataProviderFactory,
        CollectionFactory $categoryCollectionFactory,
        array $data = []
    ) {
        parent::__construct($filterItemFactory, $storeManager, $layer, $itemDataBuilder, $data);
        $this->_escaper = $escaper;
        $this->_requestVar = 'cat';
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->dataProvider = $categoryDataProviderFactory->create(['layer' => $this->getLayer()]);
    }

    /**
     * Get filter value for reset current filter state
     *
     * @return mixed|null
     */
    public function getResetValue()
    {
        return $this->dataProvider->getResetValue();
    }

    /**
     * Apply category filter to layer
     *
     * @param RequestInterface $request
     * @return Category
     */
    public function apply(RequestInterface $request)
    {
        $categoryId = (int)$request->getParam($this->getRequestVar());
        if (!$categoryId) {
            return $this;
        }

        $this->dataProvider->setCategoryId($categoryId);

        if ($this->dataProvider->isValid()) {
            $category = $this->dataProvider->getCategory();
            $this->getLayer()->getProductCollection()->addCategoryFilter($category);
            $this->getLayer()->getState()->addFilter($this->_createItem($category->getName(), $categoryId));
        }

        return $this;
    }

    /**
     * Get filter name
     *
     * @return Phrase
     */
    public function getName()
    {
        return __('Category');
    }

    /**
     * Get data array for building category filter items
     *
     * @return array
     * @throws LocalizedException
     */
    protected function _getItemsData()
    {
        $category = $this->dataProvider->getCategory();

        $categories = $this->categoryCollectionFactory->create()
            ->addPathsFilter($category->getPath().'/')
            ->addLevelFilter($category->getLevel() + 1)
            ->addAttributeToSelect('*')
            ->addAttributeToSelect('is_active')
            ->addNameToResult();

        if ($category->getIsActive()) {
            foreach ($categories as $category) {
                if ($category->getIsActive() && $category->getProductCount()) {
                    $this->itemDataBuilder->addItemData(
                        $this->_escaper->escapeHtml($category->getName()),
                        parse_url($category->getUrl(), PHP_URL_PATH),
                        $category->getProductCount()
                    );
                }
            }
        }

        return $this->itemDataBuilder->build();
    }
}
