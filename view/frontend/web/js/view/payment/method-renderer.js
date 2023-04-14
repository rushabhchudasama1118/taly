define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'taly',
                component: 'Taly_Taly/js/view/payment/method-renderer/taly'
            }
        );
        return Component.extend({});
    }
);