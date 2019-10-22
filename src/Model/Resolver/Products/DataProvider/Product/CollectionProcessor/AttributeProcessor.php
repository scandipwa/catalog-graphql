<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Adds passed in attributes to product collection results
 *
 * {@inheritdoc}
 */
class AttributeProcessor implements CollectionProcessorInterface
{
    const ATTRIBUTES_FIELD = 'attributes';

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * FilterableAttributeList constructor
     *
     * @param CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
    }

    protected function getAttributesVisibleOnFrontend() {
        $collection = $this->collectionFactory->create();
        $collection->setItemObjectClass(\Magento\Catalog\Model\ResourceModel\Eav\Attribute::class)
            ->addStoreLabel($this->storeManager->getStore()->getId())
            ->setOrder('position', 'ASC');

        // Add filter by storefront visibility
        $collection->addFieldToFilter('additional_table.is_visible_on_front', ['gt' => 0]);
        $collection->load();

        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function process(
        Collection $collection,
        SearchCriteriaInterface $searchCriteria,
        array $attributeNames
    ): Collection {
        foreach ($attributeNames as $name) {
            if ($name != self::ATTRIBUTES_FIELD) {
                $collection->addAttributeToSelect($name);
                continue;
            }

            $attributesVisibleOnFront = $this->getAttributesVisibleOnFrontend();

            $attributeCodes = array_map(function($attr) {
                return $attr->getAttributeCode();
            }, $attributesVisibleOnFront->getItems());

            $collection->addAttributeToSelect($attributeCodes);
        }

        return $collection;
    }
}
