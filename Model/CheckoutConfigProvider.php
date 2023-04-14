<?php
namespace Taly\Taly\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Taly\Taly\Helper\Data as zDataHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\UrlInterface;

class CheckoutConfigProvider implements ConfigProviderInterface
{
    protected $_checkoutSession;
    /**
     * @var zDataHelper
     */
    protected $_zDataHelper;
    protected $_priceHelper;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $_url;

    public function __construct(
        CheckoutSession $checkoutSession,
        zDataHelper $zDataHelper,
        Curl $curl,
        UrlInterface $url
    ) {
        $this->_checkoutSession = $checkoutSession;
        $this->_zDataHelper = $zDataHelper;
        $this->curl = $curl;
        $this->_url = $url;
    }

    public function getConfig()
    {
        $config = [];
        if ($this->_zDataHelper->getConfigData(zDataHelper::XML_MERCHANT_ACTIVE)) {
            $config = array_merge_recursive($config, [
                'payment' => [
                    \Taly\Taly\Model\PaymentMethod::CODE => [
                        'talypayAvailableService' => $this->gettalypayAvailableService(),
                        'talypayTCURL' => $this->_zDataHelper->getConfigData(zDataHelper::XML_TC_URL),
                        'talypayCallBackURL' => $this->_url->getBaseUrl() . 'talypay/Checkout/RedirectPage/',
                        'title' => $this->_zDataHelper->getConfigData('payment/taly/title'),
                    ],
                ],
            ]);
        } else {
            $config = array_merge_recursive($config, [
                'payment' => [
                    \Taly\Taly\Model\PaymentMethod::CODE => [
                        'talypayAvailableService' => "",
                        'talypayTCURL' => "",
                        'talypayCallBackURL' => $this->_url->getBaseUrl() . 'talypay/Checkout/RedirectPage/',
                        'title' => $this->_zDataHelper->getConfigData('payment/taly/title'),
                    ],
                ],
            ]);
        }

        return $config;
    }

    /**
     * @inheritDoc
     */

    public function gettalypayAvailableService()
    {
        $currencycode = $this->_zDataHelper->getDefaultCurrencyCode();
        $quote = $this->_checkoutSession->getQuote();
        $quote->setTotalsCollectedFlag(false);
        $quoteTotal = $quote->getTotals()['grand_total']['value'];
        file_put_contents(BP . '/var/log/getdata2.log', ' ::DATA:: ' . print_r($quoteTotal, true) . PHP_EOL, FILE_APPEND);

        $accessTokens = $this->_zDataHelper->accessToken();
        $fetchConfigResponse = $this->getPlan($accessTokens->access_token) ?? [];
        $k = 0;
        $planEmi = [];
        $availableServiceResult = [];
        // file_put_contents(BP . '/var/log/talysuccess1.log', ' ::gettalypayAvailableService:: ' . print_r(json_decode(json_encode($fetchConfigResponse,1),1), true) . PHP_EOL, FILE_APPEND);
        // $fetchConfigResponse = json_decode(json_encode($fetchConfigResponse,1),1);
        if (isset($fetchConfigResponse->{'status'}) && $fetchConfigResponse->{'status'} == 'UNPROCESSABLE_ENTITY') {
            $availableServiceResult[0] = [
                'id' => $fetchConfigResponse->{'status'},
                "service_code" => $fetchConfigResponse->{'message'},
                "total" => $quoteTotal,
                "plan_emi" => [],
                "monthsPeriod" => 0,
                "service_installment_bool" => true,
                "service_type" => $fetchConfigResponse->{'status'},
                "service_monthly_text" => $quoteTotal,
            ];
        } else {
            for ($i = 0, $iMax = count($fetchConfigResponse) ?? 0; $i < $iMax; $i++) {
                $planEmi = $this->_zDataHelper->getCalculatedInstallmentForPaymentPlans($fetchConfigResponse[$i]->{'id'}, $accessTokens->access_token, $quoteTotal, $currencycode);
                $availableServiceResult[$k] = [
                    'id' => $fetchConfigResponse[$i]->{'id'},
                    "service_code" => $fetchConfigResponse[$i]->{'name'},
                    "total" => $quoteTotal,
                    "plan_emi" => $planEmi,
                    "monthsPeriod" => $fetchConfigResponse[$i]->{'monthsPeriod'},
                    "service_installment_bool" => true,
                    "service_type" => $fetchConfigResponse[$i]->{'id'},
                    "service_monthly_text" => $quoteTotal,
                ];
                $k++;
            }
        }
        // unset($availableServiceResult[1]);
        // unset($availableServiceResult[0]['plan_emi']);
        file_put_contents(BP . '/var/log/talysuccess1.log', ' ::gettalypayAvailableService:: ' . print_r($availableServiceResult, true) . PHP_EOL, FILE_APPEND);
        // $availableServiceResult[0]['plan_emi'] = [];

        return $availableServiceResult;
    }

    public function getPlan($authtoken)
    {
        $paymentmode = $this->_zDataHelper->getConfigData(zDataHelper::XML_PAYMENT_MODE);
        $planurl = $this->_zDataHelper->getLiveUrlValue() . 'accounts/payment/plans';
        if ($paymentmode == 0) {
            $planurl = $this->_zDataHelper->getTestUrlValue() . 'accounts/payment/plans';
        }
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $planurl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Bearer ' . $authtoken,
            ),
        ));

        $response = curl_exec($curl);
        file_put_contents(BP.'/var/log/talysuccess1.log', ' ::getPlan:: '.print_r($response,true).PHP_EOL,FILE_APPEND);

        return json_decode($response);
    }

    public function getCalculatedInstallmentForPaymentPlans($planId, $token, $quoteTotal, $currencycode)
    {
        $paymentmode = $this->_zDataHelper->getConfigData(zDataHelper::XML_PAYMENT_MODE);
        $url = $this->_zDataHelper->getLiveUrlValue() . 'accounts/payment/expectedInstallments';
        if ($paymentmode == 0) {
            $url = $this->_zDataHelper->getLiveUrlValue() . 'accounts/payment/expectedInstallments';
        }
        $request = [
            'paymentPlanId' => $planId,
            'amount' => $quoteTotal,
            'currency' => $currencycode,
        ];
        $request = json_encode($request, true);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $request,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ),
        ));

        $response = curl_exec($curl);

        return json_decode($response);
    }
}
