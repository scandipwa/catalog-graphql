<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Artjoms Travkovs <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Config;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute as AttributeAlias;
use Magento\Framework\Config\ReaderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;

class ProductAttributeReader implements ReaderInterface
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
    }

    protected function getAttributesByField(string $field)
    {
        $collection = $this->collectionFactory->create();

        $collection->setItemObjectClass(AttributeAlias::class)
            ->addStoreLabel($this->storeManager->getStore()->getId())
            ->setOrder('position', 'ASC');

        // Add filter by storefront visibility
        $collection->addFieldToFilter($field, ['gt' => 0]);

        return $collection->load();
    }

    protected function configureProductFilterInput(array &$config)
    {
        $data = [];

        $filterableAttributes = $this->getAttributesByField('additional_table.is_filterable');

        foreach ($filterableAttributes->getItems() as $attribute) {
            $attributeCode = $attribute->getAttributeCode();
            $data['fields'][$attributeCode]['name'] = $attributeCode;
            $data['fields'][$attributeCode]['type'] = 'FilterTypeInput';
            $data['fields'][$attributeCode]['arguments'] = [];
        }

        $config['ProductFilterInput'] = $data;
    }

    protected function configureProductSortInput(array &$config)
    {
        $data = [];

        $filterableAttributes = $this->getAttributesByField('additional_table.used_for_sort_by');

        foreach ($filterableAttributes->getItems() as $attribute) {
            $attributeCode = $attribute->getAttributeCode();
            $data['fields'][$attributeCode]['name'] = $attributeCode;
            $data['fields'][$attributeCode]['type'] = 'SortEnum';
            $data['fields'][$attributeCode]['arguments'] = [];
        }

        $config['ProductSortInput'] = $data;
    }

    public function read($scope = null): array
    {
        $config = [];

        $this->configureProductFilterInput($config);
        $this->configureProductSortInput($config);

        return $config;
    }

}
