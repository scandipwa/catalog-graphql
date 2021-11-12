<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor;

use Magento\Catalog\Api\Data\EavAttributeInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\GraphQl\Model\Query\ContextInterface;

/**
 * Adds passed in attributes to product collection results
 *
 * {@inheritdoc}
 */
class AttributeProcessor implements CollectionProcessorInterface
{
    /**
     * Identifier for request type
     */
    const VARIANT_PLP_FIELD = 'variant_plp';

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * Existing product entity attribute codes
     * @var array
     */
    protected $validAttributeCodes = [];

    /**
     * AttributeProcessor constructor.
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * {@inheritdoc}
     */
    public function process(
        Collection $collection,
        SearchCriteriaInterface $searchCriteria,
        array $attributeNames,
        ContextInterface $context = null
    ): Collection {
        if (in_array(self::VARIANT_PLP_FIELD, $attributeNames)) {
            // for PLP variant load, attribute post processor is skipped
            // however, all visible on product list attribute data is still needed
            // what ends up being skipped is values such as attribute group data, swatches, etc
            // wildcard addition in this case seems to be the fastest
            $collection->addAttributeToSelect('*');

            return $collection;
        } else {
            return $this->processRegularCollection($collection, $attributeNames);
        }
    }

    /**
     * @param Collection $collection
     * @param array $attributeNames
     * @return Collection
     */
    public function processRegularCollection(Collection $collection, array $attributeNames): Collection
    {
        // $attributeNames is a list of all queried fields, rather than just attributes
        // load a list of valid attribute codes to skip individual attempts to load an attribute by non-existing key later
        $this->loadValidAttributeCodes();

        // returning individual addAttributeToSelect calls
        // while each of these runs a mysql query
        // it is still faster than adding all attributes to select
        // since that adds default+store-specific joins for each attribute in collection afterLoad
        // previous addAttibuteToSelect('*') simply transferred the bulk of load to a different point in time
        foreach ($attributeNames as $name) {
            if (array_key_exists($name, $this->validAttributeCodes)) {
                $collection->addAttributeToSelect($name);
            }
        }

        return $collection;
    }

    /**
     * Loads valid attribute code names
     * Using select rather than collection or repository,
     * because EAV Attribute collection or repository loads a lot of unnecessary data and takes 20-30 times longer
     * @return array
     */
    protected function loadValidAttributeCodes(): array
    {
        if (empty($this->validAttributeCodes)) {
            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select();
            $select->from(
                $connection->getTableName('eav_attribute'),
                AttributeInterface::ATTRIBUTE_CODE
            )->joinInner(
                ['type' => $connection->getTableName('eav_entity_type')],
                'type.entity_type_id=eav_attribute.entity_type_id',
                []
            )->where('type.entity_type_code = ?', ProductAttributeInterface::ENTITY_TYPE_CODE);

            $this->validAttributeCodes = array_flip($connection->fetchCol($select));
        }

        return $this->validAttributeCodes;
    }
}
