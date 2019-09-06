<?php
/**
 * Created by PhpStorm.
 * User: dave
 * Date: 6/26/18
 * Time: 3:20 PM
 */

namespace DevStone\ImageProducts\Observer;

use Magento\ConfigurableProduct\Api\Data\ConfigurableItemOptionValueInterface;

class RewardCheckoutSubmitAllAfter implements \Magento\Framework\Event\ObserverInterface
{

    /**
     * @var \Magento\Catalog\Helper\Product\Configuration
     */
    private $configurationHelper;

    public function __construct(
        \BrainActs\RewardPoints\Model\HistoryFactory $historyFactory,
        \Psr\Log\LoggerInterface $loger,
        \BrainActs\RewardPoints\Observer\Message\AfterPlaceOrder $messageManager
    ) {
        $this->historyFactory = $historyFactory;
        $this->loger = $loger;
        $this->messageManager = $messageManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Api\Data\OrderInterface $order */
        $order = $observer->getData('order');
        /** @var \Magento\Quote\Api\Data\CartInterface $quote */
        $quote = $observer->getData('quote');

        $items = $quote->getItems();
        $totalPoints = 0;

        foreach ($items as $item) {
            /** @var \Magento\Quote\Model\Quote\ProductOption $productOption */
            if ($item->getProductOption() &&
                $item->getProductOption()->getExtensionAttributes() &&
                $item->getProductOption()->getExtensionAttributes()->getConfigurableItemOptions()
            ) {
               foreach ($item->getProductOption()->getExtensionAttributes()->getConfigurableItemOptions() as $option) {
                   if ($option->getOptionId() === 159) {
                        $totalPoints += $this->updateHistory($quote, $order, $option);
                   }
               }
            }
        }

        if ($totalPoints > 0) {
            $this->messageManager->showMessage($totalPoints);
        }
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param ConfigurableItemOptionValueInterface $option
     * @return float|int
     */
    private function updateHistory($quote, $order, $option)
    {
        $pointsMap = [
            '36273' => 5,
            '36274' => 10,
            '36275' => 25,
            '36276' => 100,
        ];
        $name = [$quote->getCustomer()->getFirstname(), $quote->getCustomer()->getLastname()];
        try {
            /** @var \BrainActs\RewardPoints\Model\History $model */
            $model = $this->historyFactory->create();
            $model->setCustomerId($quote->getCustomer()->getId());
            $model->setCustomerName(implode(', ', $name));

            $points = $pointsMap[$option->getOptionValue()];
            $model->setPoints($points);
            $model->setRuleName('Download Package Purchase');
            $model->setRuleSpendId($option->getOptionValue());
            $model->setOrderId($order->getId());
            $model->setOrderIncrementId($order->getIncrementId());
            $model->setStoreId($quote->getStoreId());
            $model->setTypeRule(2);

            $model->save();
        } catch (\Exception $e) {
            $this->loger->critical($e);

            return 0;
        }

        return $points;
    }
}