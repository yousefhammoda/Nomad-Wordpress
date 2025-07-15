<?php
namespace Nomad\Shipping\Model;

use Nomad\Shipping\Api\OrderStatusManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class OrderStatusManagement implements OrderStatusManagementInterface
{
    protected $orderRepository;
    protected $scopeConfig;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->orderRepository = $orderRepository;
        $this->scopeConfig = $scopeConfig;
    }

    public function update($incrementId, $status, $token)
    {
        $apiKey = $this->scopeConfig->getValue('carriers/nomad/apikey');
        if ($token !== $apiKey) {
            return false;
        }
        $order = $this->orderRepository->get($incrementId);
        $order->setStatus($status);
        $this->orderRepository->save($order);
        return true;
    }
}
