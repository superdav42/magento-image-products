<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Model\Eav\Entity\Attribute\Source;

use Magento\Eav\Model\Entity\Collection\AbstractCollection;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\OptionFactory;
use Magento\Framework\DB\Select;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Search\Model\Query\Collection;
use Magento\Search\Model\ResourceModel\Query\CollectionFactory as QueryCollectionFactory;

/**
 * @api
 * @since 100.0.2
 */
class Keyword extends Table
{
    protected $_optionsDefault = [];
    protected $_attrOptionCollectionFactory;
    protected $_attrOptionFactory;

    public function __construct(
        CollectionFactory $attrOptionCollectionFactory,
        OptionFactory $attrOptionFactory,
        private readonly Escaper $escaper,
        private readonly StoreManagerInterface $storeManager,
        private readonly QueryCollectionFactory $queryCollectionFactory,
    ) {
        $this->_attrOptionCollectionFactory = $attrOptionCollectionFactory;
        $this->_attrOptionFactory = $attrOptionFactory;
        parent::__construct($attrOptionCollectionFactory, $attrOptionFactory);
    }

    /**
     * Retrieve Full Option values array
     *
     * @param bool $withEmpty       Add empty option to array
     * @param bool $defaultValues
     * @return array
     */
    public function getAllOptions($withEmpty = true, $defaultValues = false, $unlimited = false)
    {
        $storeId = $this->getAttribute()->getStoreId();
        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }
        if (!is_array($this->_options)) {
            $this->_options = [];
        }
        if (!is_array($this->_optionsDefault)) {
            $this->_optionsDefault = [];
        }
        $attributeId = $this->getAttribute()->getId();
        $attributeId = 141;
        if (!isset($this->_options[$storeId][$attributeId])) {
            /** @var $collection \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection */
            $collection = $this->_attrOptionCollectionFactory->create();
            $collection->setAttributeFilter(
                $attributeId
            )->setStoreFilter(
                $storeId
            );
            if (!$unlimited) {
                /** @var \Magento\Search\Model\ResourceModel\Query\Collection $queryCollection */
                $queryCollection = $this->queryCollectionFactory->create();
                $queryCollection->setPopularQueryFilter(1);
                $queryCollection->getSelect()->where('main_table.num_results < 500');
                $queryCollection->setPageSize(100);
                $terms = ['devotion','devotions','symbol','america','americana','jesus'];
                foreach ($queryCollection as $query) {
                    $terms[] = $query->getQueryText();
                }
                $collection
                    ->setPositionOrder('asc', true)
                    ->setPageSize(100+6)
                    ->addFieldToFilter('sort_alpha_value.value', ['in' =>
                        $terms
                    ]);
                ;
            } else {
                $collection->setPositionOrder(
                    'asc',
                )->setPageSize(false);
            }
            $collection->load();
            $this->_options[$storeId][$attributeId] = $collection->toOptionArray();
            $this->_optionsDefault[$storeId][$attributeId] = $collection->toOptionArray('default_value');
        }
        $options = $defaultValues
            ? $this->_optionsDefault[$storeId][$attributeId]
            : $this->_options[$storeId][$attributeId];
        if ($withEmpty) {
            $options = $this->addEmptyOption($options);
        }

        return $options;
    }

    /**
     * Retrieve Option values array by ids
     *
     * @param string|array $ids
     * @param bool $withEmpty Add empty option to array
     * @return array
     */
    public function getSpecificOptions($ids, $withEmpty = true)
    {
        $options = $this->_attrOptionCollectionFactory->create()
            ->setPositionOrder('asc')
            ->setAttributeFilter(141)
            ->addFieldToFilter('main_table.option_id', ['in' => $ids])
            ->setStoreFilter($this->getAttribute()->getStoreId())
            ->load()
            ->toOptionArray();
        if ($withEmpty) {
            $options = $this->addEmptyOption($options);
        }
        return $options;
    }

    /**
     * @param array $options
     * @return array
     */
    private function addEmptyOption(array $options)
    {
        array_unshift($options, ['label' => $this->getAttribute()->getIsRequired() ? '' : ' ', 'value' => '']);
        return $options;
    }

    /**
     * Get a text for option value
     *
     * @param string|integer $value
     * @return array|string|bool
     */
    public function getOptionText($value)
    {
        if (!$value) {
            return false;
        }
        $isMultiple = false;
        if (is_string($value) && str_contains($value, ',')) {
            $isMultiple = true;
            $value = explode(',', $value);
        }

        $options = $this->getSpecificOptions($value, false);

        if (!is_array($value)) {
            $value = [$value];
        }
        $optionsText = [];
        foreach ($options as $item) {
            if (in_array($item['value'], $value)) {
                $optionsText[] = $this->escaper->escapeHtml($item['label']);
            }
        }

        if ($isMultiple) {
            return $optionsText;
        } elseif ($optionsText) {
            return $optionsText[0];
        }

        return false;
    }

    /**
     * Add Value Sort To Collection Select
     *
     * @param AbstractCollection $collection
     * @param string $dir
     *
     * @return $this
     */
    public function addValueSortToCollection($collection, $dir = Select::SQL_ASC)
    {
        $attribute = $this->getAttribute();
        $valueTable1 = $attribute->getAttributeCode() . '_t1';
        $valueTable2 = $attribute->getAttributeCode() . '_t2';
        $linkField = $attribute->getEntity()->getLinkField();
        $collection->getSelect()->joinLeft(
            [$valueTable1 => $attribute->getBackend()->getTable()],
            "e.{$linkField}={$valueTable1}." . $linkField .
            " AND {$valueTable1}.attribute_id='{$attribute->getId()}'" .
            " AND {$valueTable1}.store_id=0",
            []
        )->joinLeft(
            [$valueTable2 => $attribute->getBackend()->getTable()],
            "e.{$linkField}={$valueTable2}." . $linkField .
            " AND {$valueTable2}.attribute_id='{$attribute->getId()}'" .
            " AND {$valueTable2}.store_id='{$collection->getStoreId()}'",
            []
        );
        $valueExpr = $collection->getSelect()->getConnection()->getCheckSql(
            "{$valueTable2}.value_id > 0",
            "{$valueTable2}.value",
            "{$valueTable1}.value"
        );

        $this->_attrOptionFactory->create()->addOptionValueToCollection(
            $collection,
            $attribute,
            $valueExpr
        );

        $collection->getSelect()->order("{$attribute->getAttributeCode()} {$dir}");

        return $this;
    }

    /**
     * Retrieve Column(s) for Flat
     *
     * @return array
     */
    public function getFlatColumns()
    {
        $columns = [];
        $attributeCode = $this->getAttribute()->getAttributeCode();
        $isMulti = $this->getAttribute()->getFrontend()->getInputType() == 'multiselect';

        $type = $isMulti ? \Magento\Framework\DB\Ddl\Table::TYPE_TEXT : \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER;
        $columns[$attributeCode] = [
            'type' => $type,
            'length' => $isMulti ? '255' : null,
            'unsigned' => false,
            'nullable' => true,
            'default' => null,
            'extra' => null,
            'comment' => $attributeCode . ' column',
        ];
        if (!$isMulti) {
            $columns[$attributeCode . '_value'] = [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length' => 255,
                'unsigned' => false,
                'nullable' => true,
                'default' => null,
                'extra' => null,
                'comment' => $attributeCode . ' column',
            ];
        }

        return $columns;
    }

    /**
     * Retrieve Indexes for Flat
     *
     * @return array
     */
    public function getFlatIndexes()
    {
        $indexes = [];

        $index = sprintf('IDX_%s', strtoupper($this->getAttribute()->getAttributeCode()));
        $indexes[$index] = ['type' => 'index', 'fields' => [$this->getAttribute()->getAttributeCode()]];

        $sortable = $this->getAttribute()->getUsedForSortBy();
        if ($sortable && $this->getAttribute()->getFrontend()->getInputType() != 'multiselect') {
            $index = sprintf('IDX_%s_VALUE', strtoupper($this->getAttribute()->getAttributeCode()));

            $indexes[$index] = [
                'type' => 'index',
                'fields' => [$this->getAttribute()->getAttributeCode() . '_value'],
            ];
        }

        return $indexes;
    }

    /**
     * Retrieve Select For Flat Attribute update
     *
     * @param int $store
     * @return Select|null
     */
    public function getFlatUpdateSelect($store)
    {
        return $this->_attrOptionFactory->create()->getFlatUpdateSelect($this->getAttribute(), $store);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getOptionId($label): ?string
    {
        $storeId = $this->getAttribute()->getStoreId();
        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }
        $attributeId = $this->getAttribute()->getId();
        $attributeId = 141;
        $collection = $this->_attrOptionCollectionFactory->create()
            ->setAttributeFilter(
                $attributeId
            )->setStoreFilter(
                $storeId
            )->addFieldToFilter(
                'tsv.value',
                $label
            )->setPageSize(
                1
            )->load();
        $options = $collection->toOptionArray();
        //        var_dump($options);
        if (empty($options[0]['value'])) {
            return null;
        }
        return $options[0]['value'];
    }
}
