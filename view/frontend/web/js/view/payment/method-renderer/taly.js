define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/model/totals',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/view/payment/default',
        'Taly_Taly/js/action/set-payment-method-action',
        'Magento_Checkout/js/model/shipping-rate-registry',
        'jquery/jquery-storageapi',
        'mage/cookies',
    ],
    function (ko, $, totals, quote, Component, setPaymentMethodAction, rateReg) {
        'use strict';
        var address = quote.shippingAddress();
        rateReg.set(address.getKey(), null);
        rateReg.set(address.getCacheKey(), null);
        quote.shippingAddress(address);
        return Component.extend({
            defaults: {
                'redirectAfterPlaceOrder': false,
                'template': 'Taly_Taly/payment/taly',
                'selectedServiceJson' : ko.observable(0),
                'talypaySelectedService':ko.observable(true),
                'serviceCounter' : 0
            },
            talypayTermsChecked: ko.observable(false),
            selectedServiceCheck: ko.observable('Pay Later'),
            selectedServiceClick : function (data,event) {
                console.log(data.service_code);
                var service_data={"service_code":data.service_code,"service_type":data.service_type};
                jQuery('.detailbox div').hide();
                var main_id = '.detailbox .'+service_data.service_type+' div';
                var main_id_deep = '.detailbox .'+service_data.service_type;
                jQuery(main_id).show();
                jQuery(main_id_deep).show();
                //Setting cookie The Value of Selected Service by User
                this.serviceCounter++;
                if (this.serviceCounter > (window.checkoutConfig.payment.taly.talypayAvailableService).length) {
                    $.mage.cookies.set('selected_service',JSON.stringify(service_data));
                    this.talypaySelectedService(true);
                }
                if (data.service_type == 'UNPROCESSABLE_ENTITY') {
                    // this.selectedServiceCheck('UNPROCESSABLE_ENTITY')
                    this.talypaySelectedService(false);
                }

            },
            getTitle: function () {
                return window.checkoutConfig.payment.taly.title;
                // return 'Taly Pay';
            },
            /*totalamount: function () {
                var price = quote.getTotals()().base_grand_total;
                return price.toFixed(2);
            },*/

            /*getCurrentLocale: function(){
                //console.log(window.checkoutConfig.payment.Talyment.TalyLan);
                return  window.checkoutConfig.payment.Talyment.TalyLan;
            },*/

            gettalypayAvailableService: function() {
                console.log("2");

                //this.talypaySelectedService(false);

                let zas = window.checkoutConfig.payment.taly.talypayAvailableService;
                for (var i = 0; i < zas.length; ++i) {
		    if (zas[i]['plan_emi'].length === 0) {
                        this.selectedServiceCheck(zas[i]['service_code']);
			this.talypaySelectedService(false);
                    }
                    if (zas[i]['service_installment_bool']) {
                      let total = 0;
		      let order_total = 0;
                        if (totals.totals._latestValue['base_grand_total'] > 0) {
                            total = totals.totals._latestValue['base_grand_total'];
			    order_total = totals.totals._latestValue['grand_total'];
                        } else {
                            total = window.checkoutConfig.quoteData.base_grand_total;
			    order_total = window.checkoutConfig.quoteData.grand_total;
                        }
                       if (total>0) {
                            let d = new Date();
                            let month = d.getMonth()+1;
                            let day = d.getDate();
                            let planEmi = zas[i]['plan_emi'];
                            let crdate = ((''+day).length<2 ? '0' : '') + day + '/' +((''+month).length<2 ? '0' : '') + month + '/' + d.getFullYear();      
                            for (var m = 0; m < planEmi.length; ++m) {
                                let dueDate = planEmi[m]['dueDate'];                        
                                if (crdate==dueDate) {
                                    planEmi[m]['dueDate'] = 'Due today';
                                }

                                let final_amount = order_total/planEmi.length;
                                zas[i]['plan_emi'][m]['dueDate'] = planEmi[m]['dueDate'];
                                zas[i]['plan_emi'][m]['amount'] = final_amount.toFixed(3);
                            }
                        }
                    }
                }
                return ko.observableArray(zas);
            },

            onRenderComplete: function () {
                // call your script code
                jQuery('.plan-details-box input[type="radio"]:first').attr('checked', true);
                jQuery('.detailbox div:first').show();  
                jQuery('.detailbox div:first div').show();
            },
            /*getTalyTCURL: function() {
                return  window.checkoutConfig.payment.Talyment.TalyTCURL;
            },
            getTalyCallBackURL: function() {
                return  window.checkoutConfig.payment.Talyment.TalyCallBackURL;
            },*/
            afterPlaceOrder: function () {
                setPaymentMethodAction(this.messageContainer);
                return false;
            }
        });
    }
);
