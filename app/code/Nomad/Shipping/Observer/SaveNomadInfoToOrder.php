<?php
namespace Nomad\Shipping\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

class SaveNomadInfoToOrder implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $quote = $observer->getEvent()->getQuote();
        if ($quote && $quote->getShippingAddress()->getData('nomad_info')) {
            $order->setData('nomad_info', $quote->getShippingAddress()->getData('nomad_info'));
        }
    }
}
