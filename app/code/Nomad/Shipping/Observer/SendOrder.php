<?php
namespace Nomad\Shipping\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class SendOrder implements ObserverInterface
{
    protected $curl;
    protected $logger;
    protected $scopeConfig;

    public function __construct(
        Curl $curl,
        LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->curl = $curl;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $carrierTitle = $this->scopeConfig->getValue('carriers/nomad/title');
        if ($order->getShippingDescription() !== $carrierTitle || $order->getData('nomad_synced')) {
            return;
        }
        $apiUrl = rtrim($this->scopeConfig->getValue('carriers/nomad/apiurl'), '/').'/api/orders/place';
        $apiKey = $this->scopeConfig->getValue('carriers/nomad/apikey');

        $data = [
            'order_id' => $order->getIncrementId(),
            'customer' => [
                'first_name' => $order->getCustomerFirstname(),
                'last_name' => $order->getCustomerLastname(),
                'email' => $order->getCustomerEmail(),
                'phone' => $order->getBillingAddress()->getTelephone(),
            ],
            'nomad_info' => $order->getData('nomad_info'),
            'destination_address' => [
                'contact_person_name' => $order->getShippingAddress()->getName(),
                'contact_person_number' => $order->getBillingAddress()->getTelephone(),
                'country' => $order->getShippingAddress()->getCountryId(),
                'state' => $order->getShippingAddress()->getRegionCode(),
                'postcode' => $order->getShippingAddress()->getPostcode(),
                'city' => $order->getShippingAddress()->getCity(),
                'address' => $order->getShippingAddress()->getStreetLine(1),
                'address1' => $order->getShippingAddress()->getStreetLine(2),
                'address2' => ''
            ]
        ];
        $items = [];
        foreach ($order->getAllItems() as $item) {
            $product = $item->getProduct();
            $dim = [
                'length_cm' => (float)$product->getData('length') ?: 0,
                'width_cm' => (float)$product->getData('width') ?: 0,
                'height_cm' => (float)$product->getData('height') ?: 0
            ];
            $items[] = [
                'name' => $item->getName(),
                'price' => $item->getPrice(),
                'quantity' => $item->getQtyOrdered(),
                'image_url' => '',
                'dimensions' => $dim,
                'weight_kg' => $product->getWeight()
            ];
        }
        $data['items'] = $items;
        $this->curl->addHeader('Authorization', 'Bearer '.$apiKey);
        $this->curl->addHeader('Content-Type', 'application/json');
        try {
            $this->curl->post($apiUrl, json_encode($data));
            $response = json_decode($this->curl->getBody());
            if ($response && isset($response->status) && $response->status == 1) {
                $order->setData('nomad_synced', 1);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
