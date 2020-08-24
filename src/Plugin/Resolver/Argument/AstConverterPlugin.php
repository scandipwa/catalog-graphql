<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Viktors Pliska <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Plugin\Resolver\Argument;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogWidget\Model\Rule;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\Resolver\Argument\AstConverter;
use Magento\Framework\GraphQl\Query\Resolver\Argument\FieldEntityAttributesPool;
use Magento\Framework\GraphQl\Query\Resolver\Argument\Filter\ClauseFactory;
use Magento\Rule\Model\Condition\Combine;
use Magento\Rule\Model\Condition\Sql\Builder;
use Magento\Widget\Helper\Conditions;

class AstConverterPlugin {
    /** @var Conditions */
    protected $conditionsHelper;

    /** @var Rule */
    protected $rule;

    /** @var FieldEntityAttributesPool */
    protected $fieldEntityAttributesPool;

    /** @var CollectionFactory */
    protected $productCollectionFactory;

    /** @var Builder */
    protected $sqlBuilder;

    /** @var ClauseFactory */
    protected $clauseFactory;

    /**
     * AstConverterPlugin constructor.
     * @param Conditions $conditionsHelper
     * @param Rule $rule
     * @param ClauseFactory $clauseFactory
     * @param FieldEntityAttributesPool $fieldEntityAttributesPool
     * @param Builder $sqlBuilder
     * @param CollectionFactory $productCollectionFactory
     */
    public function __construct(
        Conditions $conditionsHelper,
        Rule $rule,
        ClauseFactory $clauseFactory,
        FieldEntityAttributesPool $fieldEntityAttributesPool,
        Builder $sqlBuilder,
        CollectionFactory $productCollectionFactory
    ) {
        $this->fieldEntityAttributesPool = $fieldEntityAttributesPool;
        $this->conditionsHelper = $conditionsHelper;
        $this->clauseFactory = $clauseFactory;
        $this->rule = $rule;
        $this->sqlBuilder = $sqlBuilder;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    /**
     * Get conditions
     *
     * @param $conditions
     * @return Combine
     */
    protected function getConditions($conditions)
    {
        $conditions = $this->conditionsHelper->decode($conditions);

        foreach ($conditions as $key => $condition) {
            if (!empty($condition['attribute'])
                && in_array($condition['attribute'], ['special_from_date', 'special_to_date'])
            ) {
                $conditions[$key]['value'] = date('Y-m-d H:i:s', strtotime($condition['value']));
            }
        }

        $this->rule->loadPost(['conditions' => $conditions]);
        return $this->rule->getConditions();
    }

    /**
     * @param $conditionValue
     * @return array
     * @throws LocalizedException
     */
    protected function loadProductSKUs($conditionValue): array {
        $conditionDecodedValue = base64_decode($conditionValue);
        $collection = $this->productCollectionFactory->create();
        $conditions = $this->getConditions($conditionDecodedValue);
        $conditions->collectValidatedAttributes($collection);
        $this->sqlBuilder->attachConditionToCollection($collection, $conditions);
        $collection->addAttributeToSelect('sku');

        $SKUs = [];
        foreach ($collection->getItems() as $item) {
            $SKUs[] = $item->getSku();
        }

        return $SKUs;
    }

    /**
     * @param AstConverter $subject
     * @param callable $next
     * @param string $fieldName
     * @param array $arguments
     * @return array
     * @throws LocalizedException
     */
    public function aroundGetClausesFromAst(
        AstConverter $subject,
        callable $next,
        string $fieldName,
        array $arguments
    ): array {
        if (!array_key_exists('conditions', $arguments)) {
            return $next($fieldName, $arguments);
        }

        $conditionArgument = $arguments['conditions'];
        $conditionArgumentType = array_key_first($conditionArgument);

        if ($conditionArgumentType !== 'eq') {
            throw new LocalizedException(__("'conditions' field only supports 'eq' condition type."));
        }

        /**
         * This I think, might be in-efficient, or even dangerous. This will loop-over
         * all the product to match the criteria... Not sure how will this work in 2.4.x
         * Magento. HOWEVER, this seems the only reliable option we have!
         */
        $SKUs = $this->loadProductSKUs($conditionArgument[$conditionArgumentType]);
        unset($arguments['conditions']); // drop conditions from filters

        $conditions = $next($fieldName, $arguments);
        array_push($conditions, $this->clauseFactory->create(
            'sku',
            'in',
            $SKUs
        ));

        return $conditions;
    }
}
