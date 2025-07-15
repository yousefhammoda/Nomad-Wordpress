<?php
namespace Nomad\Shipping\Model\ResourceModel\ReturnRequest;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Nomad\Shipping\Model\ReturnRequest as Model;
use Nomad\Shipping\Model\ResourceModel\ReturnRequest as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
