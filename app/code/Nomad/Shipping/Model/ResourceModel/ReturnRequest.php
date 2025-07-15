<?php
namespace Nomad\Shipping\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ReturnRequest extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('nomad_return_request', 'request_id');
    }
}
