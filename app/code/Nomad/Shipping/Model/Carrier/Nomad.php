<?php
namespace Nomad\Shipping\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Rate\Result\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class Nomad extends AbstractCarrier implements CarrierInterface
{
    protected $_code = 'nomad';

    /** @var ResultFactory */
    protected $rateResultFactory;

    /** @var MethodFactory */
    protected $rateMethodFactory;

    /** @var Curl */
    protected $curl;

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        RateResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        Curl $curl,
        LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($scopeConfig, null, null, null, $data);
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->curl = $curl;
        $this->logger = $logger;
    }

    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $result = $this->rateResultFactory->create();
        $method = $this->rateMethodFactory->create();
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));

        $data = [];
        $dest = [
            'contact_person_name' => $request->getDestFirstname().' '.$request->getDestLastname(),
            'contact_person_number' => $request->getDestTelephone(),
            'country' => $request->getDestCountryId(),
            'state' => $request->getDestRegionCode(),
            'postcode' => $request->getDestPostcode(),
            'city' => $request->getDestCity(),
            'address' => is_array($request->getDestStreet()) ? $request->getDestStreet()[0] : $request->getDestStreet(),
            'address1' => '',
            'address2' => ''
        ];
        $data['destination_address'] = $dest;
        $items = [];
        foreach ($request->getAllItems() as $item) {
            $product = $item->getProduct();
            if (!$product) {
                continue;
            }
            $dim = [
                'length_cm' => (float)$product->getData('length') ?: 0,
                'width_cm' => (float)$product->getData('width') ?: 0,
                'height_cm' => (float)$product->getData('height') ?: 0
            ];
            $items[] = [
                'name' => $product->getName(),
                'price' => $product->getFinalPrice(),
                'quantity' => $item->getQty(),
                'image_url' => '',
                'dimensions' => $dim,
                'weight_kg' => $product->getWeight()
            ];
        }
        $data['items'] = $items;

        $apiUrl = rtrim($this->getConfigData('apiurl'), '/').'/api/rates/get';
        $this->curl->addHeader('Authorization', 'Bearer '.$this->getConfigData('apikey'));
        $this->curl->addHeader('Content-Type', 'application/json');
        try {
            $this->curl->post($apiUrl, json_encode($data));
            $response = json_decode($this->curl->getBody());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }

        if ($response && isset($response->status) && $response->status == 1 && isset($response->data->total_flat_rate)) {
            $method->setMethod('nomad');
            $method->setMethodTitle($this->getConfigData('title'));
            $method->setPrice($response->data->total_flat_rate);
            $method->setCost($response->data->total_flat_rate);
            $result->append($method);
        }
        return $result;
    }

    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('title')];
    }
}
