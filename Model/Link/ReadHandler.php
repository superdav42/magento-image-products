<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Model\Link;

use Magento\Downloadable\Api\LinkRepositoryInterface;
use Magento\Framework\EntityManager\Operation\ExtensionInterface;

/**
 * Class ReadHandler
 */
class ReadHandler implements ExtensionInterface
{
    public function __construct(protected LinkRepositoryInterface $linkRepository)
    {
    }

    /**
     * @param object $entity
     * @param array $arguments
     * @return \Magento\Catalog\Api\Data\ProductInterface|object
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    #[\Override]
    public function execute($entity, $arguments = [])
    {
        /** @var $entity \Magento\Catalog\Api\Data\ProductInterface */
        if ($entity->getTypeId() != \DevStone\ImageProducts\Model\Product\Type::TYPE_ID) {
            return $entity;
        }
        $entityExtension = $entity->getExtensionAttributes();
        $links = $this->linkRepository->getLinksByProduct($entity);
        if ($links) {
            $entityExtension->setDownloadableProductLinks($links);
        }
        $entity->setExtensionAttributes($entityExtension);
        return $entity;
    }
}
