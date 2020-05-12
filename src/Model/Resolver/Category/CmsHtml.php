<?php

namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Category;

use Magento\Catalog\Model\Category;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Cms\Api\BlockRepositoryInterface;

use Magento\Widget\Model\Template\FilterEmulate;
/**
 * Retrieves Landing Page / CMS blocks HTML
 */

/**
 * Example constructor.
 * @param \Magento\Cms\Model\Template\FilterProvider $filterProvider
 * @param \Magento\Catalog\Helper\Output $catalogOutputHelper
 * @param \Magento\Framework\View\Element\Template $context
 * @param array $data
 */
class CmsHtml implements ResolverInterface
{
    /**
     * @var FilterEmulate
     */
    private $widgetFilter;

    public function __construct(
        BlockRepositoryInterface $blockRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterEmulate $widgetFilter
    )
    {
        $this->blockRepository = $blockRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->widgetFilter = $widgetFilter;
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
    )
    {
        /* @var $category Category */
        $category = $value['model'];

        if($category->getLandingPage()) {
            $cmsHtml = $this->getCmsHtml($category->getLandingPage());
            return $this->widgetFilter->filter($cmsHtml['allBlocks'][$category->getLandingPage()]['content']);
        }else{
            return;
        }
    }

    function getCmsHtml($blockid): array
    {
        try {
            /* filter for all the pages */
            $searchCriteria = $this->searchCriteriaBuilder->addFilter('block_id', $blockid, 'gteq')->create();
            $blocks = $this->blockRepository->getList($searchCriteria)->getItems();

            $cmsBlocks['allBlocks'] = [];
            foreach ($blocks as $block) {
                $cmsBlocks['allBlocks'][$block->getId()]['identifier'] = $block->getIdentifier();
                $cmsBlocks['allBlocks'][$block->getId()]['name'] = $block->getTitle();
                $cmsBlocks['allBlocks'][$block->getId()]['content'] = $block->getContent();
            }
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
        }
        return $cmsBlocks;
    }
}
