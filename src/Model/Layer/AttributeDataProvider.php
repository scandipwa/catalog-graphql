<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Layer;

use Magento\Framework\App\ResourceConnection;

/**
 * Fetch root category id for specified store id
 */
class AttributeDataProvider
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Get root category for specified store id
     *
     * @param int $attributeCode
     * @param int $storeId
     */
    public function getAttributeData($attributeCode, $storeId)
    {
        $connection = $this->resourceConnection->getConnection();

        $select = $connection->select()
            ->from(
                ['attribute' => $this->resourceConnection->getTableName('eav_attribute')]
            )
            ->joinLeft(
                ['attribute_label' => $this->resourceConnection->getTableName('eav_attribute_label')],
                "attribute.attribute_id = attribute_label.attribute_id AND attribute_label.store_id = {$storeId}",
                [
                    'attribute_store_label' => 'attribute_label.value',
                ]
            )
            ->where('attribute.attribute_code = ?', $attributeCode);

        return $connection->fetchRow($select);
    }
}
