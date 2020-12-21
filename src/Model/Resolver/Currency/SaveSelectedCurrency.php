<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/module-customer-graph-ql
 * @link https://github.com/scandipwa/module-customer-graph-ql
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
use \Magento\Checkout\Model\Session as SessionManager;

/**
 * Class SaveCartItem
 * @package ScandiPWA\QuoteGraphQl\Model\Resolver
 */
class SaveSelectedCurrency implements ResolverInterface
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var SessionManager
     */
    protected $sessionManager;

    /**
     * SaveSelectedCurrency constructor.
     * @param StoreManagerInterface $storeManager
     * @param SessionManager $sessionManager
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        SessionManager $sessionManager
    ) {
        $this->storeManager = $storeManager;
        $this->sessionManager = $sessionManager;
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
                $this->sessionManager->getQuote()->collectTotals()->save();
            } catch (NoSuchEntityException $exception) {
                // Ignore if quote is not set
            }
        }

        return [];
    }
}
