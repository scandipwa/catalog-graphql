<?php
namespace ScandiPWA\CatalogGraphQl\Model\Rule\Condition;

use Magento\CatalogWidget\Model\Rule\Condition\Product as ProductCondition;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\Collection;

/**
 * Class Product
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Product extends ProductCondition
{
    /**
     * @param Attribute $attribute
     * @param Collection $collection
     * @return $this
     */
    protected function addGlobalAttribute(
        Attribute $attribute,
        Collection $collection
    ) {
        switch ($attribute->getBackendType()) {
            case 'decimal':
            case 'datetime':
            case 'int':
                $alias = 'at_' . $attribute->getAttributeCode();
                $collection->addAttributeToSelect($attribute->getAttributeCode(), 'left');
                break;
            default:
                $alias = 'at_' . sha1($this->getId()) . $attribute->getAttributeCode();

                $connection = $this->_productResource->getConnection();
                $storeId = $connection->getIfNullSql($alias . '.store_id', $this->storeManager->getStore()->getId());
                $linkField = $attribute->getEntity()->getLinkField();

                $collection->getSelect()->join(
                    [$alias => $collection->getTable('catalog_product_entity_varchar')],
                    "($alias.$linkField = e.$linkField) AND ($alias.store_id = $storeId)" .
                    " AND ($alias.attribute_id = {$attribute->getId()})",
                    []
                );
        }

        $this->joinedAttributes[$attribute->getAttributeCode()] = $alias . '.value';

        return $this;
    }
}
