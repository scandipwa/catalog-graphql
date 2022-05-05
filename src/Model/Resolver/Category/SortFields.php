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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Category;

use Magento\Catalog\Model\Category\Attribute\Source\Sortby;
use Magento\Catalog\Model\Config;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;

/**
 * Retrieves the sort fields data
 */
class SortFields implements ResolverInterface
{
    /**
     * @var Config
     */
    private $catalogConfig;

    /**
     * @var Sortby
     */
    private $sortbyAttributeSource;

    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    /**
     * @param Config $catalogConfig
     * @param Sortby $sortbyAttributeSource
     * @param CategoryRepository $categoryRepository
     */
    public function __construct(
        Config $catalogConfig,
        Sortby $sortbyAttributeSource,
        CategoryRepository $categoryRepository
    ) {
        $this->catalogConfig = $catalogConfig;
        $this->sortbyAttributeSource = $sortbyAttributeSource;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        return [
            'default' => $this->getDefaultSortOption($context),
            'options' => $this->getSortOptions($context)
        ];
    }

    private function getSortOptions($context): array {
        $categoryId = $this->getCategoryId($context);
        $sortOptions = [];

        if ($categoryId) {
            $sortOptions = $this->getSortOptionsByCategory($categoryId);
        }

        if (!count($sortOptions)) {
            $sortOptions = $this->sortbyAttributeSource->getAllOptions();
        }

        array_walk(
            $sortOptions,
            function (&$option) {
                $option['label'] = (string)$option['label'];
            }
        );

        return $sortOptions;
    }

    private function getCategoryId($context): int {
        $categoryId = 0;
        $filterGroups = $context->getExtensionAttributes()->getSearchCriteria()->getFilterGroups();

        foreach ($filterGroups as $filterGroup) {
            $filters = $filterGroup->getFilters();

            foreach ($filters as $filter) {
                $field = $filter->getField();

                if ($field === 'category_id') {
                    $categoryId = (int)$filter->getValue();
                }
            }
        }

        return $categoryId;
    }

    private function getDefaultSortOption($context): string {
        return $this->catalogConfig->getProductListDefaultSortBy(
            (int)$context->getExtensionAttributes()->getStore()->getId()
        );
    }

    private function getSortOptionsByCategory(int $categoryId): array {
        $result = [];
        $category = $this->categoryRepository->get($categoryId);
        $sortBy = $category->getAvailableSortBy() ?? [];

        foreach ($sortBy as $sortItem) {
            $result[] = [
                'value' => $sortItem,
                'label' => $this->sortbyAttributeSource->getOptionText($sortItem)
            ];
        }

        return $result;
    }
}
