<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      <info@scandiweb.com>
 * @copyright   Copyright (c) 2018 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\CatalogGraphQl\Model\AttributesJoiner;
use Magento\CatalogGraphQl\Model\Category\DepthCalculator;
use Magento\CatalogGraphQl\Model\Category\Hydrator;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\CatalogGraphQl\Model\Category\LevelCalculator;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\CategoryTree as ExtendedCategoryTree;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use GraphQL\Language\AST\FieldNode;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Extract data from category tree
 */
class CategoryTree extends ExtendedCategoryTree
{
    /**
     * In depth we need to calculate only children nodes, so the first wrapped node should be ignored
     */
    const DEPTH_OFFSET = 1;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var AttributesJoiner
     */
    private $attributesJoiner;

    /**
     * @var DepthCalculator
     */
    private $depthCalculator;

    /**
     * @var LevelCalculator
     */
    private $levelCalculator;

    /**
     * @var MetadataPool
     */
    private $metadata;

    /**
     * @var Hydrator
     */
    private $hydrator;

    /**
     * CategoryTree constructor.
     * @param CollectionFactory $collectionFactory
     * @param AttributesJoiner $attributesJoiner
     * @param DepthCalculator $depthCalculator
     * @param LevelCalculator $levelCalculator
     * @param MetadataPool $metadata
     * @param Hydrator $hydrator
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        AttributesJoiner $attributesJoiner,
        DepthCalculator $depthCalculator,
        LevelCalculator $levelCalculator,
        MetadataPool $metadata,
        Hydrator $hydrator
    ) {
        parent::__construct(
            $collectionFactory,
            $attributesJoiner,
            $depthCalculator,
            $levelCalculator,
            $metadata
        );

        $this->hydrator = $hydrator;
    }

    /**
     * @param ResolveInfo $resolveInfo
     * @param int $rootCategoryId
     * @return array
     * @throws \Exception
     */
    public function getTree(ResolveInfo $resolveInfo, int $rootCategoryId) : \Iterator
    {
        $categoryQuery = $resolveInfo->fieldNodes[0];
        $collection = $this->collectionFactory->create();
        $this->joinAttributesRecursively($collection, $categoryQuery);
        $depth = $this->depthCalculator->calculate($categoryQuery);
        $level = $this->levelCalculator->calculate($rootCategoryId);
        //Search for desired part of category tree
        $collection->addPathFilter(sprintf('.*/%s/[/0-9]*$', $rootCategoryId));
        $collection->addFieldToFilter('level', ['gt' => $level]);
        $collection->addFieldToFilter('level', ['lteq' => $level + $depth - self::DEPTH_OFFSET]);
        $collection->setOrder('level');
        $collection->getSelect()->orWhere(
            $this->metadata->getMetadata(CategoryInterface::class)->getIdentifierField() . ' = ?',
            $rootCategoryId
        );
        return $collection->getIterator();
    }

    /**
     * Extract data from category tree
     *
     * @param \Iterator $iterator
     * @return array
     */
    public function processTree(\Iterator $iterator): array
    {

        $tree = [];
        $referenceList = []; // A list of pointer to parents, used to know where to insert new children

        // First item is laways root, create root node
        /** @var CategoryInterface $category */
        $category = $iterator->current();
        $tree[$category->getId()] = $this->hydrator->hydrateCategory($category);
        $tree[$category->getId()]['model'] = $category;
        $referenceList[$category->getId()] = &$tree[$category->getId()];

        $iterator->next();

        // Fill the rest of tree
        while ($iterator->valid()) {
            /** @var CategoryInterface $category */
            $category = $iterator->current();

            $categoryData = $this->hydrator->hydrateCategory($category);
            $categoryData['model'] = $category;

            $newItemIdAtItsParent = count($referenceList[$category->getParentId()]['children']);
            $referenceList[$category->getParentId()]['children'][$newItemIdAtItsParent] = $categoryData;
            $referenceList[$category->getId()] = &$referenceList[$category->getParentId()]['children'][$newItemIdAtItsParent];

            $iterator->next();
        }

        return $tree;
    }

    /**
     * @param Collection $collection
     * @param FieldNode $fieldNode
     * @return void
     */
    private function joinAttributesRecursively(Collection $collection, FieldNode $fieldNode) : void
    {
        if (!isset($fieldNode->selectionSet->selections)) {
            return;
        }

        $subSelection = $fieldNode->selectionSet->selections;
        $this->attributesJoiner->join($fieldNode, $collection);

        /** @var FieldNode $node */
        foreach ($subSelection as $node) {
            if ($node->kind === 'InlineFragment') {
                continue;
            }

            $this->joinAttributesRecursively($collection, $node);
        }
    }
}
