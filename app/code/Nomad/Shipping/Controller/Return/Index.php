<?php
namespace Nomad\Shipping\Controller\Return;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Customer\Model\Session;
use Nomad\Shipping\Model\ReturnRequestFactory;
use Magento\Framework\HTTP\Client\Curl;

class Index extends Action
{
    protected $resultFactory;
    protected $customerSession;
    protected $requestFactory;
    protected $curl;
    protected $scopeConfig;

    public function __construct(
        Context $context,
        ResultFactory $resultFactory,
        Session $customerSession,
        ReturnRequestFactory $requestFactory,
        Curl $curl,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->resultFactory = $resultFactory;
        $this->customerSession = $customerSession;
        $this->requestFactory = $requestFactory;
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute()
    {
        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPostValue();
            $model = $this->requestFactory->create();
            $model->setData([
                'order_id' => $data['order_id'],
                'customer_id' => $this->customerSession->getId(),
                'reason' => $data['reason'],
                'items' => isset($data['items']) ? json_encode($data['items']) : '',
                'status' => 'open'
            ])->save();

            $apiUrl = rtrim($this->scopeConfig->getValue('carriers/nomad/apiurl'), '/') . '/api/orders/return/create';
            $apiKey = $this->scopeConfig->getValue('carriers/nomad/apikey');
            $payload = [
                'order_id' => $data['order_id'],
                'reason' => $data['reason'],
            ];
            $this->curl->addHeader('Authorization', 'Bearer ' . $apiKey);
            $this->curl->addHeader('Content-Type', 'application/json');
            try {
                $this->curl->post($apiUrl, json_encode($payload));
            } catch (\Exception $e) {
                // ignore
            }

            $this->messageManager->addSuccessMessage(__('Return request submitted.'));
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setPath('sales/order/view', ['order_id' => $data['order_id']]);
            return $resultRedirect;
        }
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->set(__('Return Request'));
        return $resultPage;
    }
}
