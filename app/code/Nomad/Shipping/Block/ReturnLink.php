<?php
namespace Nomad\Shipping\Block;

use Magento\Framework\View\Element\Html\Link;
use Magento\Sales\Model\OrderRepository;

class ReturnLink extends Link
{
    protected $orderRepository;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        OrderRepository $orderRepository,
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        parent::__construct($context, $data);
    }

    public function getHref()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        return $this->getUrl('nomad/return/index', ['order_id' => $orderId]);
    }
}
