<?php

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\SearchCriteria\CollectionProcessor\FilterProcessor;

use Magento\CatalogWidget\Model\Rule;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\SearchCriteria\CollectionProcessor\FilterProcessor\CustomFilterInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Rule\Model\Condition\Sql\Builder;
use Magento\Widget\Helper\Conditions;

class ConditionsFilter implements CustomFilterInterface
{
    /**
     * @var Conditions
     */
    protected $conditionsHelper;

    /**
     * @var Rule
     */
    protected $rule;

    /**
     * @var Builder
     */
    protected $sqlBuilder;
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * ConditionsFilter constructor.
     * @param Conditions $conditionsHelper
     * @param Rule $rule
     * @param Builder $sqlBuilder
     */
    public function __construct(
        Conditions $conditionsHelper,
        Rule $rule,
        Builder $sqlBuilder,
        CollectionFactory $collectionFactory
    ) {
        $this->conditionsHelper = $conditionsHelper;
        $this->rule = $rule;
        $this->sqlBuilder = $sqlBuilder;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Get conditions
     *
     * @return \Magento\Rule\Model\Condition\Combine
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
     * @inheritDoc
     */
    public function apply(Filter $filter, AbstractDb $collection)
    {
        $conditionType = $filter->getConditionType();
        $rawFilterField = $filter->getField();

        if ($conditionType !== 'eq') {
            throw new LocalizedException(__($rawFilterField . " only supports 'eq' condition type."));
        }

        $conditions = base64_decode($filter->getValue());
        $conditions = $this->getConditions($conditions);

        $simpleSelect = clone $collection;
        $conditions->collectValidatedAttributes($simpleSelect);
        $this->sqlBuilder->attachConditionToCollection($simpleSelect, $conditions);

        $simpleSelect->addFieldToFilter('status', Status::STATUS_ENABLED);
        $simpleSelect->getSelect()
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns(['e.entity_id']);

        $configurableProductCollection = $this->collectionFactory->create();
        $select = $configurableProductCollection->getConnection()
            ->select()
            ->distinct()
            ->from(['l' => 'catalog_product_super_link'], 'l.product_id')
            ->join(
                ['k' => $configurableProductCollection->getTable('catalog_product_entity')],
                `k.entity_id = l.parent_id`
            )
            ->where($configurableProductCollection->getConnection()->prepareSqlCondition(
                'l.product_id',
                ['in' => $simpleSelect->getSelect()]
            ))
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns(['l.parent_id']);

        $unionCollection = $this->collectionFactory->create();
        $unionSelect = $unionCollection->getConnection()
            ->select()
            ->union([$simpleSelect->getSelect(), $select]);

        $collection->getSelect()
            ->where($collection->getConnection()->prepareSqlCondition(
                'e.entity_id',
                ['in' => $unionSelect]
            ));

        return true;
    }
}
