<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Model\Link;

use DevStone\ImageProducts\Model\Product\Type;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Downloadable\Api\Data\LinkInterface;
use Magento\Downloadable\Api\LinkRepositoryInterface;

class CreateHandler extends \Magento\Downloadable\Model\Link\CreateHandler
{
    protected Config $mediaConfig;

    public function __construct(
        LinkRepositoryInterface $linkRepository,
        Config $mediaConfig
    ) {
        parent::__construct($linkRepository);
        $this->mediaConfig = $mediaConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function execute($entity, $arguments = [])
    {
        /** @var $entity Product */
        if ($entity->getTypeId() != Type::TYPE_ID) {
            return $entity;
        }

        /** @var LinkInterface[] $links */
        $links = $entity->getExtensionAttributes()->getDownloadableProductLinks() ?: [];
        foreach ($links as $link) {
            $link->setId(null);
            $this->linkRepository->save($entity->getSku(), $link, !(bool)$entity->getStoreId());
        }

        return $entity;
    }
}
