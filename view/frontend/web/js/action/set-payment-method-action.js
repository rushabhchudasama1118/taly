define(
    [
        'jquery',
        'mage/storage',
        'jquery/jquery-storageapi',
        'mage/url'
    ],
    function ($)
    {
        'use strict';
        return function (messageContainer) {
            /*
                Redirect to  Talypay Payment UI
             */
            $.mage.redirect(window.checkoutConfig.payment.taly.talypayCallBackURL);
        };
    }
);