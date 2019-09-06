<?php

namespace DevStone\ImageProducts\EntityManager\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class BeforeProductSaveKeywords implements ObserverInterface
{
    /**
     * @var \Magento\Eav\Api\AttributeOptionManagementInterface
     */
    protected $attributeOptionManagement;

    /**
     * @var \Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory
     */
    protected $optionLabelFactory;

    /**
     * @var \Magento\Eav\Api\Data\AttributeOptionInterfaceFactory
     */
    protected $optionFactory;

    /**
     * @var \Magento\Catalog\Api\ProductAttributeRepositoryInterface
     */
    protected $attributeRepository;

    /**
     * @param \Magento\Downloadable\Api\LinkRepositoryInterface $linkRepository
     */
    public function __construct(
        \Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository,
        \Magento\Eav\Api\AttributeOptionManagementInterface $attributeOptionManagement,
        \Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory $optionLabelFactory,
        \Magento\Eav\Api\Data\AttributeOptionInterfaceFactory $optionFactory
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
     * @throws \Magento\Framework\Validator\Exception
     * @return void
     */
    public function execute(Observer $observer)
    {
        $entity = $observer->getEvent()->getProduct();
        if ($entity instanceof \Magento\Catalog\Api\Data\ProductInterface &&
            $entity->getTypeId() === \DevStone\ImageProducts\Model\Product\Type::TYPE_ID) {
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
            /** @var \Magento\Eav\Model\Entity\Attribute\OptionLabel $optionLabel */
            $optionLabel = $this->optionLabelFactory->create();
            $optionLabel->setStoreId(0);
            $optionLabel->setLabel($label);

            $option = $this->optionFactory->create();
            $option->setLabel($label);
            $option->setStoreLabels([$optionLabel]);
            $option->setSortOrder(0);
            $option->setIsDefault(false);

            $this->attributeOptionManagement->add(
                \Magento\Catalog\Model\Product::ENTITY,
                $this->attributeRepository->get('keywords')->getAttributeId(),
                $option
            );

            $optionId = $attribute->getSource()->getOptionId($label);
        }
        return $optionId;
    }
}
