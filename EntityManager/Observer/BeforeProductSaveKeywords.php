<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\EntityManager\Observer;

use DevStone\ImageProducts\Model\Product\Type;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory;
use Magento\Eav\Model\Entity\Attribute\OptionLabel;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class BeforeProductSaveKeywords implements ObserverInterface
{
    protected AttributeOptionManagementInterface $attributeOptionManagement;
    protected AttributeOptionLabelInterfaceFactory $optionLabelFactory;
    protected AttributeOptionInterfaceFactory $optionFactory;
    protected ProductAttributeRepositoryInterface $attributeRepository;

    public function __construct(
        ProductAttributeRepositoryInterface $attributeRepository,
        AttributeOptionManagementInterface $attributeOptionManagement,
        AttributeOptionLabelInterfaceFactory $optionLabelFactory,
        AttributeOptionInterfaceFactory $optionFactory
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->attributeOptionManagement = $attributeOptionManagement;
        $this->optionLabelFactory = $optionLabelFactory;
        $this->optionFactory = $optionFactory;
    }

    /**
     * Apply model save operation
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $entity = $observer->getEvent()->getProduct();
        if ($entity instanceof ProductInterface &&
            $entity->getTypeId() === Type::TYPE_ID) {
            $keywords = $entity->getKeywords();

            if (!empty($keywords) && is_iterable($keywords)) {

                foreach ($keywords as &$keywordId) {
                    if (!is_numeric($keywordId)) {
                        $keywordId = $this->createOrGetId(trim($keywordId));
                    }
                }

                $entity->setKeywords(array_unique($keywords));
            }
        }
    }

    private function createOrGetId($label)
    {
        $attribute = $this->attributeRepository->get('keywords');

        $optionId = $attribute->getSource()->getOptionId($label);

        if (!$optionId) {
            /** @var OptionLabel $optionLabel */
            $optionLabel = $this->optionLabelFactory->create();
            $optionLabel->setStoreId(0);
            $optionLabel->setLabel($label);

            $option = $this->optionFactory->create();
            $option->setLabel($label);
            $option->setStoreLabels([$optionLabel]);
            $option->setSortOrder(0);
            $option->setIsDefault(false);

            $this->attributeOptionManagement->add(
                Product::ENTITY,
                $this->attributeRepository->get('keywords')->getAttributeId(),
                $option
            );

            $optionId = $attribute->getSource()->getOptionId($label);
        }
        return $optionId;
    }
}
