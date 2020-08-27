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

namespace ScandiPWA\CatalogGraphQl\Plugin\Resolver;

use Magento\CatalogGraphQl\Model\Resolver\Products as CoreProducts;
use Magento\Customer\Model\Session;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\Argument\SearchCriteria\Builder;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class Products {
    /** @var Builder */
    protected $searchCriteriaBuilder;

    /** @var Session */
    protected $customerSession;

    /**
     * Products constructor.
     * @param Builder $searchCriteriaBuilder
     * @param Session $customerSession
     */
    public function __construct(
        Builder $searchCriteriaBuilder,
        Session $customerSession
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->customerSession = $customerSession;
    }

    public function beforeResolve(
        CoreProducts $products,
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $searchCriteria = $this->searchCriteriaBuilder->build('products', $args);
        $context->getExtensionAttributes()->setSearchCriteria($searchCriteria);

        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                if ($filter->getField() === 'customer_group_id') {
                    $this->customerSession->setCustomerGroupId($filter->getValue());
                }
            }
        }

        return [
            $field,
            $context,
            $info,
            $value,
            $args
        ];
    }
}
