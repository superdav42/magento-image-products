<?php

namespace DevStone\ImageProducts\Model;

use \Magento\Downloadable\Api\Data\LinkInterface;
use Magento\Downloadable\Model\Product\Type;
use Magento\Downloadable\Model\Product\TypeHandler\Link as LinkHandler;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\EntityManager\MetadataPool;
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
            $this->validateLinkType($link);
            $this->validateSampleType($link);
            if (!$this->contentValidator->isValid($link, true, $link->hasSampleType())) {
                throw new InputException(__('The link information is invalid. Verify the link and try again.'));
            }
            $title = $link->getTitle();
            if (empty($title)) {
                $link->setTitle('image');
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


    /**
     * Check that Link type exist.
     *
     * @param LinkInterface $link
     * @return void
     * @throws InputException
     */
    private function validateLinkType(LinkInterface $link): void
    {
        if (!in_array($link->getLinkType(), ['url', 'file'], true)) {
            throw new InputException(__('The link type is invalid. Verify and try again.'));
        }
    }

    /**
     * Check that Link sample type exist.
     *
     * @param LinkInterface $link
     * @return void
     * @throws InputException
     */
    private function validateSampleType(LinkInterface $link): void
    {
        if ($link->hasSampleType() && !in_array($link->getSampleType(), ['url', 'file'], true)) {
            throw new InputException(__('The link sample type is invalid. Verify and try again.'));
        }
    }
}
