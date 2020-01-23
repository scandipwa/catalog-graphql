<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Jegors Batovs <info@scandiweb.com>
 * @copyright   Copyright (c) 2020 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductTierPriceInterface;
use Magento\Catalog\Api\ScopedProductTierPriceManagementInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;

use Magento\Customer\Model\Session;

class ProductTierPrices implements ResolverInterface
{
    /**
     * @var ScopedProductTierPriceManagementInterface
     */
    protected $tierPriceManagement;

    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * ProductTierPrices constructor.
     * @param ScopedProductTierPriceManagementInterface $tierPriceManagement
     * @param Session $customerSession
     */
    public function __construct(
        /*Scoped*/
        ScopedProductTierPriceManagementInterface $tierPriceManagement,
        Session $customerSession
    ) {
        $this->tierPriceManagement = $tierPriceManagement;
        $this->customerSession = $customerSession;
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array|Value|mixed
     * @throws NoSuchEntityException
     * @throws GraphQlInputException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ){
        if(!isset($value['model'])) throw new GraphQlInputException(__('Cannot get tier prices for product without sku'));

        /** @var ProductInterface $product */
        $product = $value['model'];

        /** @var ProductTierPriceInterface[] $result */
        $result = [];

        $tierPrices = $this->tierPriceManagement->getList($product->getSku(), 'all');
        foreach ($tierPrices as $tierPrice){
            array_push($result,[
                'quantity' => $tierPrice->getQty(),
                'value' => $tierPrice->getValue(),
                'ratio' => round((($product->getPrice() - $tierPrice->getValue()) / $product->getPrice()) * 100)
            ]);
        }

        return $result;
    }
}
