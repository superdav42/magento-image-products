<?php

namespace DevStone\ImageProducts\Model;

use \Magento\Downloadable\Api\Data\LinkInterface;
use Magento\Framework\Exception\InputException;
use Magento\Catalog\Api\Data\ProductInterface;

class LinkRepository extends \Magento\Downloadable\Model\LinkRepository
{

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function save($sku, LinkInterface $link, $isGlobalScopeContent = true)
    {
        $product = $this->productRepository->get($sku, true);
        if ($link->getId() !== null) {
            return $this->updateLink($product, $link, $isGlobalScopeContent);
        } else {
            if (!$product->getTypeInstance() instanceof \Magento\Downloadable\Model\Product\Type) {
                throw new InputException(__('Provided product must be type \'downloadable\' or \'image\'.'));
            }
            $validateLinkContent = !($link->getLinkType() === 'file' && $link->getLinkFile());
            $validateSampleContent = !($link->getSampleType() === 'file' && $link->getSampleFile());
            if (!$this->contentValidator->isValid($link, $validateLinkContent, $validateSampleContent)) {
                throw new InputException(__('Provided link information is invalid.'));
            }

            if (!in_array($link->getLinkType(), ['url', 'file'], true)) {
                throw new InputException(__('Invalid link type.'));
            }
            $title = $link->getTitle();
            if (empty($title)) {
                $link->setTitle($sku);
            }

            return $this->saveLink($product, $link, $isGlobalScopeContent);
        }
    }

    protected function updateLink(ProductInterface $product, LinkInterface $link, $isGlobalScopeContent)
    {
        if( empty($link->getTitle())) {
            $link->setTitle($product->getSku());
        }
        return parent::updateLink($product, $link, $isGlobalScopeContent);
    }
}
