<?php
/**
 * ScandiPWA_CatalogGraphQl
 *
 * @category ScandiPWA
 * @package ScandiPWA_CatalogGraphQl
 * @author Daniels Puzina <info@scandiweb.com>
 * @copyright Copyright (c) 2020 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\CatalogGraphQl\SearchAdapter\Query\Builder;

use Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\AttributeProvider;
use Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\FieldProvider\FieldType\ResolverInterface as TypeResolver;
use Magento\Elasticsearch\SearchAdapter\Query\ValueTransformerPool;
use Magento\Elasticsearch\Model\Adapter\FieldMapperInterface;
use Magento\Elasticsearch\SearchAdapter\Query\Builder\Match as CoreMatch;
use Magento\Framework\App\ObjectManager;

/**
 * Class Match
 * @package ScandiPWA\CatalogGraphQl\SearchAdapter\Query\Builder
 */
class Match extends CoreMatch
{
    /**
     * Define fuzziness level of search query
     */
    public const FUZZINESS_LEVEL = 'AUTO';

    /**
     * @var FieldMapperInterface
     */
    private $fieldMapper;

    /**
     * @var AttributeProvider
     */
    private $attributeProvider;

    /**
     * @var TypeResolver
     */
    private $fieldTypeResolver;

    /**
     * @var ValueTransformerPool
     */
    private $valueTransformerPool;

    /**
     * Match constructor.
     *
     * @param FieldMapperInterface $fieldMapper
     * @param array $preprocessorContainer
     * @param AttributeProvider|null $attributeProvider
     * @param TypeResolver|null $fieldTypeResolver
     * @param ValueTransformerPool|null $valueTransformerPool
     */
    public function __construct(
        FieldMapperInterface $fieldMapper,
        array $preprocessorContainer,
        AttributeProvider $attributeProvider = null,
        TypeResolver $fieldTypeResolver = null,
        ValueTransformerPool $valueTransformerPool = null
    ) {
        $this->fieldMapper = $fieldMapper;

        $this->attributeProvider = $attributeProvider ?? ObjectManager::getInstance()
                ->get(AttributeProvider::class);
        $this->fieldTypeResolver = $fieldTypeResolver ?? ObjectManager::getInstance()
                ->get(TypeResolver::class);
        $this->valueTransformerPool = $valueTransformerPool ?? ObjectManager::getInstance()
                ->get(ValueTransformerPool::class);

        parent::__construct($fieldMapper, $preprocessorContainer, $attributeProvider, $fieldTypeResolver,
            $valueTransformerPool);
    }

    /**
     * {@inheritDoc}
     */
    protected function buildQueries(array $matches, array $queryValue): array
    {
        $conditions = [];

        // Checking for quoted phrase \"phrase test\", trim escaped surrounding quotes if found
        $count = 0;
        $value = preg_replace('#^"(.*)"$#m', '$1', $queryValue['value'], -1, $count);
        $condition = ($count) ? 'match_phrase' : 'match';

        $transformedTypes = [];
        foreach ($matches as $match) {
            $attributeAdapter = $this->attributeProvider->getByAttributeCode($match['field']);
            $fieldType = $this->fieldTypeResolver->getFieldType($attributeAdapter);
            $valueTransformer = $this->valueTransformerPool->get($fieldType ?? 'text');
            $valueTransformerHash = \spl_object_hash($valueTransformer);
            if (!isset($transformedTypes[$valueTransformerHash])) {
                $transformedTypes[$valueTransformerHash] = $valueTransformer->transform($value);
            }

            $transformedValue = $transformedTypes[$valueTransformerHash];
            if (null === $transformedValue) {
                //Value is incompatible with this field type.
                continue;
            }

            $resolvedField = $this->fieldMapper->getFieldName(
                $match['field'],
                ['type' => FieldMapperInterface::TYPE_QUERY]
            );

            $conditions[] = [
                'condition' => $queryValue['condition'],
                'body' => [
                    $condition => [
                        $resolvedField => [
                            'query' => $transformedValue,
                            'boost' => $match['boost'] ?? 1,
                            'fuzziness' => self::FUZZINESS_LEVEL
                        ],
                    ],
                ],
            ];
        }

        return $conditions;
    }
}
