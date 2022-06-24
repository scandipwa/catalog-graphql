<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/catalog-graphql
 * @link https://github.com/scandipwa/catalog-graphql
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Currency;

use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use ScandiPWA\QuoteGraphQl\Model\Resolver\CartResolver;
use Magento\Webapi\Controller\Rest\ParamOverriderCustomerId;

/**
 * Class SaveCartItem
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class SaveSelectedCurrency extends CartResolver
{
    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * SaveSelectedCurrency constructor.
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ParamOverriderCustomerId $overriderCustomerId,
        CartManagementInterface $quoteManagement,
        GuestCartRepositoryInterface $guestCartRepository
    ) {
        parent::__construct(
            $guestCartRepository,
            $overriderCustomerId,
            $quoteManagement
        );

        $this->storeManager = $storeManager;
    }

    /**
     * Fetches the data from persistence models and format it according to the GraphQL schema.
     *
     * @param Field            $field
     * @param ContextInterface $context
     * @param ResolveInfo      $info
     * @param array|null       $value
     * @param array|null       $args
     * @return mixed|Value
     * @throws Exception
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $currency = $args['currency'];

        if ($currency) {
            $this->storeManager->getStore()->setCurrentCurrencyCode($currency);

            // Rebuilds active quotes all values (price, currency, etc.)
            try {
                $quote = $this->getCart($args);
                $quote->collectTotals()->save();
            } catch (NoSuchEntityException $exception) {
                // Ignore if quote is not set
            }
        }

        return [];
    }
}
