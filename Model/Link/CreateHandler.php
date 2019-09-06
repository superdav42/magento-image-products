<?php

namespace DevStone\ImageProducts\Model\Link;


class CreateHandler extends \Magento\Downloadable\Model\Link\CreateHandler 
{
    /**
     *
     * @var \Magento\Catalog\Model\Product\Media\Config 
     */
    protected $mediaConfig;
    
    /**
     * @param \Magento\Downloadable\Api\LinkRepositoryInterface $linkRepository
     */
    public function __construct(
        \Magento\Downloadable\Api\LinkRepositoryInterface $linkRepository, 
        \Magento\Catalog\Model\Product\Media\Config $mediaConfig
    ) {
        parent::__construct($linkRepository);
        $this->mediaConfig = $mediaConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function execute($entity, $arguments = [])
    {
        /** @var $entity \Magento\Catalog\Model\Product */
        if ($entity->getTypeId() != \DevStone\ImageProducts\Model\Product\Type::TYPE_ID) {
            return $entity;
        }

        /** @var \Magento\Downloadable\Api\Data\LinkInterface[] $links */
        $links = $entity->getExtensionAttributes()->getDownloadableProductLinks() ?: [];
        foreach ($links as $link) {
            $link->setId(null);
            $this->linkRepository->save($entity->getSku(), $link, !(bool)$entity->getStoreId());
        }
        
        return $entity;
    }
}
