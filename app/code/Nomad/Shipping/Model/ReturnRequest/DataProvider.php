<?php
namespace Nomad\Shipping\Model\ReturnRequest;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Nomad\Shipping\Model\ResourceModel\ReturnRequest\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData()
    {
        return ['items' => $this->collection->getData(), 'totalRecords' => $this->collection->getSize()];
    }
}
