<?php
namespace Nomad\Shipping\Api;

interface OrderStatusManagementInterface
{
    /**
     * Update order status from Nomad
     * @param string $incrementId
     * @param string $status
     * @param string $token
     * @return bool
     */
    public function update($incrementId, $status, $token);
}
