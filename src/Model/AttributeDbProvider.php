<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_CatalogGraphQl
 * @author      Ilja Lapkovskis <ilja@scandiweb.com | info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */


namespace ScandiPWA\CatalogGraphQl\Model;


use Magento\Framework\App\ResourceConnection;
use Magento\Framework\GraphQl\Query\Fields;
use Magento\Framework\GraphQl\Query\Resolver\Argument\AstConverter;

/**
 * Provide correct attribute list for request parameter validation (attribute)
 * @see AstConverter
 *
 * Class AttributeDbProvider
 * @package ScandiPWA\CatalogGraphQl
 */
class AttributeDbProvider
{
    /**
     * @var ResourceConnection
     */
    private $connection;
    
    /**
     * @var Fields
     */
    private $queryFields;
    
    /**
     * AttributeDbProvider constructor.
     * @param ResourceConnection $connection
     * @param Fields             $queryFields
     */
    public function __construct(
        ResourceConnection $connection,
        Fields $queryFields
    )
    {
        $this->connection = $connection;
        $this->queryFields = $queryFields;
    }
    
    /**
     * Returns array of valid attributes, corresponding to request
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Db_Statement_Exception
     * @return array
     */
    public function getProductAttributes(): array
    {
        $fieldsUsedInQuery = $this->queryFields->getFieldsUsedInQuery();
        $connection = $this->connection->getConnection();
        $placeHolders = str_repeat('?,', count($fieldsUsedInQuery) - 1) . '?';
        $eavAttribute = $this->connection->getTableName('eav_attribute');
        $catalogEavAttribute = $this->connection->getTableName('catalog_eav_attribute');
        $sql = "SELECT {$eavAttribute}.attribute_code
        FROM {$eavAttribute}
        WHERE {$eavAttribute}.attribute_id IN (
            SELECT {$catalogEavAttribute}.attribute_id
            FROM {$catalogEavAttribute}
            WHERE {$catalogEavAttribute}.is_filterable = 1
        ) AND {$eavAttribute}.attribute_code IN ($placeHolders)";
        $query = $connection->query($sql, array_keys($fieldsUsedInQuery));

        return $query->fetchAll(\PDO::FETCH_COLUMN);
    }
}