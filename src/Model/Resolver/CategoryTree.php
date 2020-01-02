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
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
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
     * @var CategoryResourceModel
     */

    private $categoryResourceModel;
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
     * @param CategoryResourceModel $categoryResourceModel
     * @param CategoryFactory $categoryFactory
     * @param ExtractDataFromCategoryTree $extractDataFromCategoryTree
     */
    public function __construct(
        DataCategoryTree $categoryTree,
        CategoryResourceModel $categoryResourceModel,
        CategoryFactory $categoryFactory,
        ExtractDataFromCategoryTree $extractDataFromCategoryTree
    ) {
        $this->categoryTree = $categoryTree;
        $this->categoryResourceModel = $categoryResourceModel;
        $this->categoryFactory = $categoryFactory;
        $this->extractDataFromCategoryTree = $extractDataFromCategoryTree;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (isset($value[$field->getName()])) {
            return $value[$field->getName()];
        }

        $rootCategoryParameters = $this->getCategoryId($args);
        $rootCategoryId = $rootCategoryParameters['id'];
        $categoriesTree = $this->categoryTree->getTree($info, $rootCategoryId);
        if (!empty($categoriesTree)) {
            $result = $this->extractDataFromCategoryTree->execute($categoriesTree);
            $category = current($result);

            $active = $rootCategoryParameters['is_active'];
            return array_merge($category, ['is_active' => $active]);
            // return current($result);
        }

        return null;
    }

    /**
     * @param array $args
     * @return array
     * @throws GraphQlInputException
     */
    private function getCategoryParameters(array $args): int
    {
        if (isset($args['id'])) {
            $categoryFactory = $this->categoryFactory->create();
            // $category = $categoryFactory->load((int)$args['id']);
            // $category = $categoryFactory->load((int)$args['id']);
            // $category = $this->categoryResourceModel->load($category, $categoryId);

            return [
                'id' => (int)$args['id'],
                'is_active' => $category->getIsActive()
            ];
        }

        if (isset($args['url_path'])) {
            $categoryFactory = $this->categoryFactory->create();
            // $this->categoryResourceModel->load($category, $categoryId);
            $category = $categoryFactory->loadByAttribute('url_path', $args['url_path']);

            return [
                'id' => (int)$category->getId(),
                'is_active' => $category->getIsActive()
            ];
        }

        throw new GraphQlInputException(__('"id or url for category must be specified'));
    }
}

