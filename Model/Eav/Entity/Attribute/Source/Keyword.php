<?php

namespace DevStone\ImageProducts\Model\Eav\Entity\Attribute\Source;

use Magento\Framework\App\ObjectManager;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Escaper;

/**
 * @api
 * @since 100.0.2
 */
class Keyword extends Table
{
    /**
     * Default values for option cache
     *
     * @var array
     */
    protected $_optionsDefault = [];

    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory
     */
    protected $_attrOptionCollectionFactory;

    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\OptionFactory
     */
    protected $_attrOptionFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $attrOptionCollectionFactory
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\OptionFactory $attrOptionFactory
     * @param Escaper|null $escaper
     * @codeCoverageIgnore
     */
    public function __construct(
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $attrOptionCollectionFactory,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\OptionFactory $attrOptionFactory,
        Escaper $escaper = null
    ) {
        $this->_attrOptionCollectionFactory = $attrOptionCollectionFactory;
        $this->_attrOptionFactory = $attrOptionFactory;
        $this->escaper = $escaper ?: ObjectManager::getInstance()->get(Escaper::class);

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
    	if ( ! $unlimited ) {
    		return [];
	    }
        $storeId = $this->getAttribute()->getStoreId();
        if ($storeId === null) {
            $storeId = $this->getStoreManager()->getStore()->getId();
        }
        if (!is_array($this->_options)) {
            $this->_options = [];
        }
        if (!is_array($this->_optionsDefault)) {
            $this->_optionsDefault = [];
        }
        $attributeId = $this->getAttribute()->getId();
        if (!isset($this->_options[$storeId][$attributeId])) {
            $collection = $this->_attrOptionCollectionFactory->create()->setPositionOrder(
                'asc'
            )->setAttributeFilter(
                $attributeId
            )->setStoreFilter(
                $storeId
			)->setPageSize(
				$unlimited ? false : 100
            )->load();
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
     * Get StoreManager dependency
     *
     * @return StoreManagerInterface
     * @deprecated 100.1.6
     */
    private function getStoreManager()
    {
        if ($this->storeManager === null) {
            $this->storeManager = ObjectManager::getInstance()->get(StoreManagerInterface::class);
        }
        return $this->storeManager;
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
        return parent::getSpecificOptions($ids, $withEmpty);
        $options = $this->_attrOptionCollectionFactory->create()
            ->setPositionOrder('asc')
            ->setAttributeFilter($this->getAttribute()->getId())
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
        if (strpos($value, ',')) {
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
     * @param \Magento\Eav\Model\Entity\Collection\AbstractCollection $collection
     * @param string $dir
     *
     * @return $this
     */
    public function addValueSortToCollection($collection, $dir = \Magento\Framework\DB\Select::SQL_ASC)
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
     * @return \Magento\Framework\DB\Select|null
     */
    public function getFlatUpdateSelect($store)
    {
        return $this->_attrOptionFactory->create()->getFlatUpdateSelect($this->getAttribute(), $store);
    }

	/**
     * @param string $value
     * @return null|string
     */
    public function getOptionId($label)
    {

		$storeId = $this->getAttribute()->getStoreId();
        if ($storeId === null) {
            $storeId = $this->getStoreManager()->getStore()->getId();
        }

        $attributeId = $this->getAttribute()->getId();

		$collection = $this->_attrOptionCollectionFactory->create()
			->setAttributeFilter(
				$attributeId
			)->setStoreFilter(
				$storeId
			)->addFieldToFilter(
				'tsv.value', $label
			)->setPageSize(
				1
			)->load();
		$options = $collection->toOptionArray();
//		var_dump($options);
		if ( empty($options[0]['value'] )) {
			return false;
		}
		return $options[0]['value'];



        foreach ($this->getAllOptions() as $option) {
            if (strcasecmp($option['label'], $value) == 0 || $option['value'] == $value) {
                return $option['value'];
            }
        }
        return null;
    }
}
