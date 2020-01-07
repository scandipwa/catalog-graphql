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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Inventory;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\StockStatusRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;

/**
 * @inheritdoc
 */
class StockCount implements ResolverInterface {
    /**
     * @var StockStatusRepositoryInterface
     */
    private $stockStatusRepository;

    /**
     * @param StockStatusRepositoryInterface $stockStatusRepository
     */
    public function __construct(StockStatusRepositoryInterface $stockStatusRepository)
    {
        $this->stockStatusRepository = $stockStatusRepository;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!array_key_exists('model', $value) || !$value['model'] instanceof ProductInterface) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        /* @var $product ProductInterface */
        $product = $value['model'];

        $stockStatus = $this->stockStatusRepository->get($product->getId());
        $stockItem = $stockStatus->getStockItem();

        return [
            'qty' => $stockItem->getQty(),
            'min_sale_qty' => $stockItem->getMinSaleQty(),
            'max_sale_qty' => $stockItem->getMaxSaleQty()
        ];
    }
}
