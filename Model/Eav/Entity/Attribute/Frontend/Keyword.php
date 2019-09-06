<?php

namespace DevStone\ImageProducts\Model\Eav\Entity\Attribute\Frontend;

/**
 * OverRides default frontend display so it doesn't check input type but renders all keywords.
 *
 * @author dave
 */
class Keyword extends \Magento\Eav\Model\Entity\Attribute\Frontend\AbstractFrontend
{

    /**
     * Retrieve attribute value
     *
     * @param \Magento\Framework\DataObject $object
     * @return mixed
     */
    public function getValue(\Magento\Framework\DataObject $object)
    {
        $value = $object->getData($this->getAttribute()->getAttributeCode());

        $value = $this->getOption($value);

        if (empty($value)) {
            return $value;
        }
        if (!is_array($value)) {
            $value = [$value];
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $builder = $objectManager->get(
            \Magento\Framework\UrlInterface::class
        );
        $escaper = $objectManager->get(
            \Magento\Framework\Escaper::class
        );
        $rendered = '';
        foreach ($value as $keyword) {
            $rendered .= '<a href="' .
                $escaper->escapeHtmlAttr($builder->getUrl('catalogsearch/result/index', ['q' => $keyword]))
                . '">' . $escaper->escapeHtml($keyword) . '</a> &nbsp; ';
        }

        return $rendered;
    }
}
