<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Model\Link;

use DevStone\ImageProducts\Model\Product\Type;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Downloadable\Api\Data\LinkInterface;
use Magento\Downloadable\Api\LinkRepositoryInterface;
use Magento\Framework\EntityManager\Operation\ExtensionInterface;

/**
 * Class UpdateHandler
 */
class UpdateHandler implements ExtensionInterface
{
    protected LinkRepositoryInterface $linkRepository;

    protected $mediaConfig;

    /**
     * @param LinkRepositoryInterface $linkRepository
     * @param Config $mediaConfig
     */
    public function __construct(
        LinkRepositoryInterface                     $linkRepository,
        Config $mediaConfig
    ) {
        $this->linkRepository = $linkRepository;
        $this->mediaConfig = $mediaConfig;
    }

    /**
     * @param object $entity
     * @param array $arguments
     * @return ProductInterface|object
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute($entity, $arguments = [])
    {
        /** @var $entity ProductInterface */
        if ($entity->getTypeId() != Type::TYPE_ID) {
            return $entity;
        }

        /** @var LinkInterface[] $links */
        $links = $entity->getExtensionAttributes()->getDownloadableProductLinks() ?: [];
        $updatedLinks = [];
        $oldLinks = $this->linkRepository->getList($entity->getSku());

        foreach ($links as $link) {
            if ($link->getId()) {
                $updatedLinks[$link->getId()] = true;
            }
            $this->linkRepository->save($entity->getSku(), $link, !(bool)$entity->getStoreId());
        }

        /** @var ProductInterface $entity */
        foreach ($oldLinks as $link) {
            if (!isset($updatedLinks[$link->getId()])) {
                $this->linkRepository->delete($link->getId());
            }
        }


        return $entity;
    }
}
