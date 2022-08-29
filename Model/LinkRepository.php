<?php

namespace DevStone\ImageProducts\Model;

use \Magento\Downloadable\Api\Data\LinkInterface;
use Magento\Downloadable\Model\Product\Type;
use Magento\Downloadable\Model\Product\TypeHandler\Link as LinkHandler;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Exception\InputException;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class LinkRepository extends \Magento\Downloadable\Model\LinkRepository
{

    /**
     * @var MetadataPool
     */
    private $metadataPool;

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


    /**
     * Update existing Link.
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @param LinkInterface $link
     * @param bool $isGlobalScopeContent
     * @return mixed
     * @throws InputException
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function updateLink(
        \Magento\Catalog\Api\Data\ProductInterface $product,
        LinkInterface $link,
                                                   $isGlobalScopeContent
    ) {
        if( empty($link->getTitle())) {
            $link->setTitle($product->getSku());
        }
        /** @var $existingLink \Magento\Downloadable\Model\Link */
        $existingLink = $this->linkFactory->create()->load($link->getId());
        if (!$existingLink->getId()) {
            throw new NoSuchEntityException(
                __('No downloadable link with the provided ID was found. Verify the ID and try again.')
            );
        }
        $linkFieldValue = $product->getData(
            $this->getMetadataPool()->getMetadata(ProductInterface::class)->getLinkField()
        );
        if ($existingLink->getProductId() != $linkFieldValue) {
            throw new InputException(
                __("The downloadable link isn't related to the product. Verify the link and try again.")
            );
        }
        $this->validateLinkType($link);
        $this->validateSampleType($link);
        $validateSampleContent = $link->hasSampleType();
        if (!$this->contentValidator->isValid($link, true, $validateSampleContent)) {
            throw new InputException(__('The link information is invalid. Verify the link and try again.'));
        }
        if ($isGlobalScopeContent) {
            $product->setStoreId(0);
        }
        $title = $link->getTitle();
        if (empty($title)) {
            if ($isGlobalScopeContent) {
                throw new InputException(__('The link title is empty. Enter the link title and try again.'));
            }
        }
        if (!$validateSampleContent) {
            $this->resetLinkSampleContent($link, $existingLink);
        }
        $this->saveLink($product, $link, $isGlobalScopeContent);

        return $existingLink->getId();
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

        if (!in_array($link->getLinkType(), ['url', 'file', 'gal_10', 'gal_12','gal_14', 'gal_16', 'gal_20', 'gal_24', 'gal_30', 'gal_36', 'gal_40'], true)) {
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

    /**
     * Reset Sample type and file.
     *
     * @param LinkInterface $link
     * @param LinkInterface $existingLink
     * @return void
     */
    private function resetLinkSampleContent(LinkInterface $link, LinkInterface $existingLink): void
    {
        $existingType = $existingLink->getSampleType();
        $link->setSampleType($existingType);
        if ($existingType === 'file') {
            $link->setSampleFile($existingLink->getSampleFile());
        } else {
            $link->setSampleUrl($existingLink->getSampleUrl());
        }
    }


    /**
     * Get MetadataPool instance
     *
     * @deprecated 100.1.0 MAGETWO-52273
     * @return MetadataPool
     */
    private function getMetadataPool()
    {
        if (!$this->metadataPool) {
            $this->metadataPool = ObjectManager::getInstance()->get(MetadataPool::class);
        }

        return $this->metadataPool;
    }
}
