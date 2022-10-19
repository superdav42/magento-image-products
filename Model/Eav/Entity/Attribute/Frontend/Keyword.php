<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Model\Eav\Entity\Attribute\Frontend;

use Magento\Eav\Model\Entity\Attribute\Frontend\AbstractFrontend;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;

/**
 * OverRides default frontend display so it doesn't check input type but renders all keywords.
 *
 * @author dave
 */
class Keyword extends AbstractFrontend
{

    /**
     * Retrieve attribute value
     *
     * @param DataObject $object
     * @return mixed
     */
    public function getValue(DataObject $object)
    {
        $value = $object->getData($this->getAttribute()->getAttributeCode());

        $value = $this->getOption($value);

        if (empty($value)) {
            return $value;
        }
        if (!is_array($value)) {
            $value = [$value];
        }

        $objectManager = ObjectManager::getInstance();

        $builder = $objectManager->get(
            UrlInterface::class
        );
        $escaper = $objectManager->get(
            Escaper::class
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
