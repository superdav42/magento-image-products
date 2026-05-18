<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Model\Eav\Entity\Attribute\Source;

class Table extends \Magento\Eav\Model\Entity\Attribute\Source\Table
{

    private array $cacheForIndexOptions;

    /**
     * Get a text for index option value
     *
     * @param string|int $value
     * @return string|bool
     * @codeCoverageIgnore
     */
    #[\Override]
    public function getIndexOptionText($value)
    {
        return parent::getIndexOptionText($value);
        $isMultiple = false;
        if (strpos((string) $value, ',')) {
            $isMultiple = true;
            $value = explode(',', (string) $value);
        }
        $storeId = $this->getAttribute()->getStoreId();

        if (!isset($this->cacheForIndexOptions[$storeId])) {
            unset($this->cacheForIndexOptions);
            $this->cacheForIndexOptions[$storeId] = [];

            $options = $this->getAllOptions($withEmpty = false, $defaultValues = false);

            $this->cacheForIndexOptions[$storeId] = array_column($options, 'label', 'value');
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        $optionsText = array_intersect_key($this->cacheForIndexOptions[$storeId], array_flip($value));

        if ($isMultiple) {
            return $optionsText;
        } elseif ($optionsText) {
            return reset($optionsText);
        }
    }
}
