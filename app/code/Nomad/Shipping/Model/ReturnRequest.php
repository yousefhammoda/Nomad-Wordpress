<?php
namespace Nomad\Shipping\Model;

use Magento\Framework\Model\AbstractModel;

class ReturnRequest extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(ResourceModel\ReturnRequest::class);
    }
}
