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

namespace ScandiPWA\CatalogGraphQl\Model\Resolver;

use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\CategoryTree as DataCategoryTree;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\ExtractDataFromCategoryTree;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Catalog\Model\CategoryFactory;

class CategoryTree implements ResolverInterface
{
    /**
     * @var CategoryFactory
     */
    private $categoryFactory;

    /**
     * @var DataCategoryTree
     */
    private $categoryTree;

    /**
     * @var
     */
    private $extractDataFromCategoryTree;

    /**
     * CategoryTree constructor.
     * @param DataCategoryTree $categoryTree
     * @param CategoryFactory $categoryFactory
     * @param ExtractDataFromCategoryTree $extractDataFromCategoryTree
     */
    public function __construct(
        DataCategoryTree $categoryTree,
        CategoryFactory $categoryFactory,
        ExtractDataFromCategoryTree $extractDataFromCategoryTree
    ) {
        $this->categoryTree = $categoryTree;
        $this->categoryFactory = $categoryFactory;
        $this->extractDataFromCategoryTree = $extractDataFromCategoryTree;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (isset($value[$field->getName()])) {
            return $value[$field->getName()];
        }

        $rootCategoryId = $this->getCategoryId($args);
        $categoriesTree = $this->categoryTree->getTree($info, $rootCategoryId);

        if ($categoriesTree !== null) {
            $result = $this->extractDataFromCategoryTree->execute($categoriesTree);
            $category = current($result);

            if (!$category) {
                throw new GraphQlNoSuchEntityException(__('Category with specified id does not exist.'));
            }

            return $category;
        }

        return null;
    }

    /**
     * @param array $args
     * @return int
     * @throws GraphQlInputException
     * @throws GraphQlNoSuchEntityException
     */
    private function getCategoryId(array $args): int
    {
        if (isset($args['id'])) {
            return (int) $args['id'];
        }

        if (isset($args['url_path'])) {
            $categoryFactory = $this->categoryFactory->create();
            $category = $categoryFactory->loadByAttribute('url_path', $args['url_path']);

            if (!$category) {
                throw new GraphQlNoSuchEntityException(__('Category with specified url path does not exist.'));
            }

            return (int) $category->getId();
        }

        throw new GraphQlInputException(__('id or url for category must be specified'));
    }
}

