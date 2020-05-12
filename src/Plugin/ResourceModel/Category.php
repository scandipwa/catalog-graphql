<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Alfreds Genkins <info@scandipwa.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Plugin\ResourceModel;

use Magento\Catalog\Model\ResourceModel\Category as CoreCategory;
use Magento\Eav\Model\Entity\Context;

/**
 * Class Category
 */
class Category {
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resource;

    /**
     * Category constructor.
     * @param Context $context
     */
    public function __construct(
        Context $context
    ) {
        $this->resource = $context->getResource();
    }

    /**
     * @param CoreCategory $subject
     * @param callable $next
     * @param $category
     * @return int
     */
    public function aroundGetProductCount(
        CoreCategory $subject,
        callable $next,
        $category
    ) {
        // changed table name from catalog_category_product to catalog_category_product_index
        $productTable = $this->resource->getTableName('catalog_category_product_index');

        $select = $this->resource->getConnection()->select()->from(
            ['main_table' => $productTable],
            [new \Zend_Db_Expr('COUNT(main_table.product_id)')]
        )->where(
            'main_table.category_id = :category_id'
        );

        $bind = ['category_id' => (int) $category->getId()];
        $counts = $this->resource->getConnection()->fetchOne($select, $bind);

        return (int) $counts;
    }
}
