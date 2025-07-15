<?php
namespace Nomad\Shipping\Plugin;

use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Quote\Api\Data\AddressInterface;

class SaveAddressInformation
{
    public function beforeSaveAddressInformation(
        ShippingInformationManagement $subject,
        $cartId,
        \Magento\Checkout\Api\Data\ShippingInformationInterface $addressInformation
    ) {
        $shippingAddress = $addressInformation->getShippingAddress();
        $ext = $shippingAddress->getExtensionAttributes();
        if ($ext && $ext->getNomadInfo()) {
            $shippingAddress->setData('nomad_info', $ext->getNomadInfo());
        }
        return [$cartId, $addressInformation];
    }
}
