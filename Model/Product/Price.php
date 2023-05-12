<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Model\Product;

use DevStone\FramedPrints\NWFraming\Client;
use DevStone\UsageCalculator\Api\UsageRepositoryInterface;
use DevStone\UsageCalculator\Model\Usage\Option\Value;
use Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory;
use Magento\Catalog\Model\Product;
use Magento\CatalogRule\Model\ResourceModel\RuleFactory;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Catalog\Api\Data\ProductTierPriceExtensionFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Price class for calculated prices
 *
 * @author David Stone
 */
class Price extends Product\Type\Price
{
    private UsageRepositoryInterface $usageRepository;
    private Json $serializer;
    private Client $client;

    public function __construct(
        RuleFactory $ruleFactory,
        StoreManagerInterface $storeManager,
        TimezoneInterface $localeDate,
        Session $customerSession,
        ManagerInterface $eventManager,
        PriceCurrencyInterface $priceCurrency,
        GroupManagementInterface $groupManagement,
        ProductTierPriceInterfaceFactory $tierPriceFactory,
        ScopeConfigInterface $config,
        UsageRepositoryInterface $usageRepository,
        Json $serializer,
        Client $client,
        ProductTierPriceExtensionFactory $tierPriceExtensionFactory = null
    ) {
        $this->usageRepository = $usageRepository;
        $this->serializer = $serializer;
        $this->client = $client;

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
     * @param Product $product
     * @return float
     * @throws LocalizedException
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

        $usage = $this->usageRepository->getById((int)$usageId);

        $price = $usage->getPrice();

        foreach ($usage->getOptions() as $option) {

            $selectedOptions = $this->serializer->unserialize($product->getCustomOption('usage_options')->getValue());

            if ($option->hasValues()) {
                $value = $option->getValueById($selectedOptions[$option->getId()]);

                if ($value->getPriceType() == Value::TYPE_PERCENT) {
                    $price *= ($value->getPrice() / 100);
                } else {
                    $price += $value->getPrice();
                }
            } elseif ($option->getPrice()) {
                $value = $selectedOptions[$option->getId()];

                if ($option->getPriceType() == Value::TYPE_PERCENT) {
                    $price *= ($option->getPrice() / 100);
                } else {
                    $price += $option->getPrice();
                }
            }
        }
        if ($price > 0 && 95 !== (int)fmod(($price * 100), 100)) { // round prices which don't end in $.95
            $price = round((float)$price);
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
     * @param Product $product
     * @return float
     */
    private function getPriceForPrint($product): float
    {
        $options = $this->serializer->unserialize($product->getCustomOption('print_options')->getValue());

        $price = $this->client->getPrice($options);

        $product->setData('final_price', $price);
        return $price;
    }

    /**
     * Retrieve product final price for image in template.
     *
     * @param Product $product
     * @return float
     */
    private function getPriceForTemplate($product): float|int
    {
        $options = $this->serializer->unserialize($product->getCustomOption('template_options')->getValue());

        if (!empty($options)) {
            $price = 0;
        }

        $product->setData('final_price', 0);
        return 0;
    }
}
