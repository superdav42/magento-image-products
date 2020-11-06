<?php

namespace DevStone\ImageProducts\Model\Product;

use Magento\Customer\Api\GroupManagementInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Catalog\Api\Data\ProductTierPriceExtensionFactory;

/**
 * Price class for calculated prices
 *
 * @author David Stone
 */
class Price extends \Magento\Catalog\Model\Product\Type\Price
{
    /**
     * @var \DevStone\UsageCalculator\Api\UsageRepositoryInterface
     */
    private $usageRepository;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $serializer;

    /**
     * Constructor
     *
     * @param \Magento\CatalogRule\Model\ResourceModel\RuleFactory $ruleFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param GroupManagementInterface $groupManagement
     * @param \Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory $tierPriceFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param ProductTierPriceExtensionFactory|null $tierPriceExtensionFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\CatalogRule\Model\ResourceModel\RuleFactory $ruleFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        PriceCurrencyInterface $priceCurrency,
        GroupManagementInterface $groupManagement,
        \Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory $tierPriceFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \DevStone\UsageCalculator\Api\UsageRepositoryInterface $usageRepository,
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        ProductTierPriceExtensionFactory $tierPriceExtensionFactory = null
    ) {
        $this->usageRepository = $usageRepository;
        $this->serializer = $serializer;

        parent::__construct(
            $ruleFactory,
            $storeManager,
            $localeDate,
            $customerSession,
            $eventManager,
            $priceCurrency,
            $groupManagement,
            $tierPriceFactory,
            $config,
            $tierPriceExtensionFactory
        );
    }

    /**
     * Retrieve product final price
     *
     * @param integer $qty
     * @param \Magento\Catalog\Model\Product $product
     * @return float
     */
    public function getFinalPrice($qty, $product)
    {
        if ($qty === null && $product->getCalculatedFinalPrice() !== null) {
            return $product->getCalculatedFinalPrice();
        }

        $finalPrice = parent::getFinalPrice($qty, $product);

        if ($product->getCustomOption('print')) {
            return $this->getPriceForPrint($product);
        }

        if ($product->getCustomOption('template_options')) {
            return $this->getPriceForTemplate($product);
        }

        if (null === $product->getCustomOption('usage_id')) {
            return $finalPrice;
        }

        $usageId = $product->getCustomOption('usage_id')->getValue();

        $usage = $this->usageRepository->getById($usageId);

        $price = $usage->getPrice();

        foreach ($usage->getOptions() as $option) {

            $selectedOptions = $this->serializer->unserialize($product->getCustomOption('usage_options')->getValue());

            if ($option->hasValues()) {
                $value = $option->getValueById($selectedOptions[$option->getId()]);

                if ($value->getPriceType() == \DevStone\UsageCalculator\Model\Usage\Option\Value::TYPE_PERCENT) {
                    $price *= ($value->getPrice() / 100);
                } else {
                    $price += $value->getPrice();
                }
            } elseif ($option->getPrice()) {
                $value = $selectedOptions[$option->getId()];

                if ($option->getPriceType() == \DevStone\UsageCalculator\Model\Usage\Option\Value::TYPE_PERCENT) {
                    $price *= ($option->getPrice() / 100);
                } else {
                    $price += $option->getPrice();
                }
            }
        }

        if (95 !== (($price * 100) % 100)) { // round prices which don't end in $.95
            $price = round($price);
        }

        $paymentTypeOption = $product->getCustomOption('payment_type');
        if ($usage->getCredits() && $paymentTypeOption && 'credits' === $paymentTypeOption->getValue()) {
            // Price must be set to the price the customer paid so royalties are calculated correctly.
            $price = $usage->getCredits() * 4;
        }

        $product->setData('final_price', $price);
        return max(0, $product->getData('final_price'));
    }

    /**
     * Retrieve product final price for print product.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return float
     */
    private function getPriceForPrint($product)
    {
        $options = $this->serializer->unserialize($product->getCustomOption('print_options')->getValue());

//        $prices = $this->graphikClient->getProductGroupPrice($options);

        $product->setData('final_price', $prices['finalPrice']);
        return $prices['finalPrice'];
    }

    /**
     * Retrieve product final price for image in template.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return float
     */
    private function getPriceForTemplate($product)
    {
        $options = $this->serializer->unserialize($product->getCustomOption('template_options')->getValue());

        if (!empty($options)) {
            $price = 0;
        }

        $product->setData('final_price', 0);
        return 0;
    }
}
