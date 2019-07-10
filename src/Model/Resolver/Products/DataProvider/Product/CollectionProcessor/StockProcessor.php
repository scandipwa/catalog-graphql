<?php


namespace ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor;


use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor
\StockProcessor as MagentoStockProcessor;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Status as StockStatusResource;
use Magento\Framework\Api\SearchCriteriaInterface;

class StockProcessor extends MagentoStockProcessor implements CollectionProcessorInterface
{
    /**
     * @var StockConfigurationInterface
     */
    private $stockConfig;
    
    /**
     * @var StockStatusResource
     */
    private $stockStatusResource;
    
    /**
     * @param StockConfigurationInterface $stockConfig
     * @param StockStatusResource         $stockStatusResource
     */
    public function __construct(StockConfigurationInterface $stockConfig, StockStatusResource $stockStatusResource)
    {
        $this->stockConfig = $stockConfig;
        $this->stockStatusResource = $stockStatusResource;
    }
    
    public function process(
        $collection,
        SearchCriteriaInterface $searchCriteria,
        array $attributeNames
    ): Collection
    {
        $singleProduct = false;
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $filters = $filterGroup->getFilters();
            $type = $filters[0]->getConditionType();
            $field = $filters[0]->getField();
            if ($type === 'eq' && $field === 'url_key') {
                $singleProduct = true;
                break;
            }
        }
        if (!$singleProduct && !$this->stockConfig->isShowOutOfStock()) {
            $this->stockStatusResource->addIsInStockFilterToCollection($collection);
        }
    
        return $collection;
    }
}