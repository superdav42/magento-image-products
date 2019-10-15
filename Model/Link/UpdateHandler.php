<?php

namespace DevStone\ImageProducts\Model\Link;

use Magento\Downloadable\Api\LinkRepositoryInterface as LinkRepository;
use Magento\Framework\EntityManager\Operation\ExtensionInterface;

/**
 * Class UpdateHandler
 */
class UpdateHandler implements ExtensionInterface
{
    /**
     * @var LinkRepository
     */
    protected $linkRepository;

    /**
     *
     * @var \Magento\Catalog\Model\Product\Media\Config
     */
    protected $mediaConfig;

    /**
     * @param LinkRepository $linkRepository
     * @param \Magento\Catalog\Model\Product\Media\Config $mediaConfig
     */
    public function __construct(
        LinkRepository $linkRepository,
        \Magento\Catalog\Model\Product\Media\Config $mediaConfig
    ) {
        $this->linkRepository = $linkRepository;
        $this->mediaConfig = $mediaConfig;
    }

    /**
     * @param object $entity
     * @param array $arguments
     * @return \Magento\Catalog\Api\Data\ProductInterface|object
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute($entity, $arguments = [])
    {
        /** @var $entity \Magento\Catalog\Api\Data\ProductInterface */
        if ($entity->getTypeId() != \DevStone\ImageProducts\Model\Product\Type::TYPE_ID) {
            return $entity;
        }

        /** @var \Magento\Downloadable\Api\Data\LinkInterface[] $links */
        $links = $entity->getExtensionAttributes()->getDownloadableProductLinks() ?: [];
        $updatedLinks = [];
        $oldLinks = $this->linkRepository->getList($entity->getSku());

        foreach ($links as $link) {
            if ($link->getId()) {
                $updatedLinks[$link->getId()] = true;
            }
            $this->linkRepository->save($entity->getSku(), $link, !(bool)$entity->getStoreId());
        }

        /** @var \Magento\Catalog\Api\Data\ProductInterface $entity */
        foreach ($oldLinks as $link) {
            if (!isset($updatedLinks[$link->getId()])) {
                $this->linkRepository->delete($link->getId());
            }
        }


        return $entity;
    }
}
