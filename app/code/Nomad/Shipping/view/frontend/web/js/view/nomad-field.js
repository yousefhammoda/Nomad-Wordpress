define(['uiComponent', 'ko', 'Magento_Checkout/js/model/quote'], function(Component, ko, quote){
    'use strict';
    return Component.extend({
        defaults:{
            template: 'Nomad_Shipping/nomad-field'
        },
        isVisible: function(){
            var method = quote.shippingMethod();
            return method && method.carrier_code === 'nomad';
        },
        nomadInfo: ko.observable('')
    });
});
