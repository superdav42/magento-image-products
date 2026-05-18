<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Model\Link;

use Magento\Downloadable\Api\LinkRepositoryInterface;
use Magento\Framework\EntityManager\Operation\ExtensionInterface;

/**
 * Class DeleteHandler
 */
class DeleteHandler implements ExtensionInterface
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
        if ($entity->getTypeId() != \DevStone\ImageProducts\Model\Product\Type::TYPE_ID) {
            return $entity;
        }
        /** @var \Magento\Catalog\Api\Data\ProductInterface $entity */
        foreach ($this->linkRepository->getList($entity->getSku()) as $link) {
            $this->linkRepository->delete($link->getId());
        }
        return $entity;
    }
}
