<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      scandiweb <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\ResourceModel\Category;

use Magento\CatalogUrlRewrite\Model\ResourceModel\Category\Product as SourceProduct;

class Product extends SourceProduct
{
    const DUPLICATE_FIELDS = ['url_rewrite_id', 'category_id', 'product_id'];

    /**
     * Save multiple data
     *
     * @param array $insertData
     * @return int
     */
    public function saveMultiple(array $insertData)
    {
        $connection = $this->getConnection();
        if (count($insertData) <= self::CHUNK_SIZE) {
            return $connection->insertOnDuplicate($this->getTable(self::TABLE_NAME), $insertData, self::DUPLICATE_FIELDS);
        }
        $data = array_chunk($insertData, self::CHUNK_SIZE);
        $totalCount = 0;
        foreach ($data as $insertData) {
            $totalCount += $connection->insertOnDuplicate($this->getTable(self::TABLE_NAME), $insertData, self::DUPLICATE_FIELDS);
        }
        return $totalCount;
    }
}
