<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\EntityManager\Observer;

use DevStone\ImageProducts\Model\Eav\Entity\Attribute\Backend\Keyword;
use DevStone\ImageProducts\Model\Product\Type;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory;
use Magento\Eav\Model\Entity\Attribute\OptionLabel;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class BeforeProductSaveKeywords implements ObserverInterface
{
    public function __construct(protected ProductAttributeRepositoryInterface $attributeRepository, protected AttributeOptionManagementInterface $attributeOptionManagement, protected AttributeOptionLabelInterfaceFactory $optionLabelFactory, protected AttributeOptionInterfaceFactory $optionFactory, protected RequestInterface $request)
    {
    }

    /**
     * Apply model save operation
     *
     * @param Observer $observer
     * @return void
     */
    #[\Override]
    public function execute(Observer $observer)
    {
        $entity = $observer->getEvent()->getProduct();
        if ($entity instanceof ProductInterface &&
            $entity->getTypeId() === Type::TYPE_ID) {
            foreach (Keyword::KEYWORDS_ATTRIBUTES as $keywordAttribute) {
                if ($this->request instanceof \Magento\Framework\HTTP\PhpEnvironment\Request &&
                    in_array($this->request->getModuleName(), ['catalog', 'csproduct']) &&
                    in_array($this->request->getControllerName(), ['product', 'vproducts']) &&
                    $this->request->getActionName() === 'save' &&
                    !isset($this->request->getParam('product')[$keywordAttribute])) {

					// This is an ugly hack to allow removing all keywords from input
                    $entity->setData($keywordAttribute, []);
                } else {
                    $keywords = $entity->getData($keywordAttribute);
                    if (!empty($keywords) && is_iterable($keywords)) {
                        foreach ($keywords as &$keywordId) {
                            if (!is_numeric($keywordId)) {
                                $keywordId = $this->createOrGetId($keywordAttribute, trim((string) $keywordId));
                            }
                        }

                        $entity->setData($keywordAttribute, array_unique($keywords));
                    }
                }
            }
        }
    }

    private function createOrGetId($attributeCode, $label)
    {
        $attribute = $this->attributeRepository->get($attributeCode);

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
                $this->attributeRepository->get($attributeCode)->getAttributeId(),
                $option
            );

            $optionId = $attribute->getSource()->getOptionId($label);
        }
        return $optionId;
    }
}
