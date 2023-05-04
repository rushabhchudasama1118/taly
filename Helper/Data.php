<?php
namespace Talypay\Taly\Helper;

/**
 * Data
 *
 * @copyright Copyright © 2020 Taly Taly. All rights reserved.
 * @author
 */

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\PageCache\Version;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\Currency;
use Magento\Framework\Filesystem;
use Magento\Framework\HTTP\Client\Curl;

class Data extends AbstractHelper
{

    const XML_MERCHANT_ID = 'payment/taly/merchant_id';
    const XML_MERCHANT_KEY = 'payment/taly/merchant_key';
    const XML_LIVE_URL = 'payment/taly/live_url';
    const XML_TEST_URL = 'payment/taly/test_url';
    const XML_MERCHANT_SALT = 'payment/taly/merchant_salt';
    const XML_PAYMENT_MODE = 'payment/taly/list_mode';
    const XML_MERCHANT_ACTIVE = 'payment/taly/active';
    const XML_MERCHANT_STATUS = 'payment/taly/merchant_status';
    const XML_MERCHANT_TOKEN = 'payment/taly/merchant_token';
    const XML_API_URL = 'payment/taly/talypay_api_url';
    const XML_API_VER = 'payment/taly/talypay_api_ver';
    const XML_API_HEALTH = 'payment/taly/talypay_api_health';
    const XML_SERVICE_CONFIGURATION = 'payment/taly/talypay_service_configuration';
    const XML_DISPLAY_INFO_PRODUCTPAGE = 'payment/taly/talypay_display_info_checkbox';
    const XML_TC_URL = 'payment/taly/talypay_tc_url';
    const XML_DEFAULT_COUNTRY_CODE = 'general/country/default';
    const XML_DEFAULT_CURRENCY = 'currency/options/default';
    const XML_GATEWAY_TITLE = 'payment/taly/gateway_title';
    const API_CREATETRANSACTION = '/transactions';
    const API_REFUNDTRANSACTION = '/refunds';
    const API_GETCONFIGURATIONS = '/configuration';
    const POST = "POST";

    /**
     * @var ApiUrl
     */
    public $_ApiUrl;

    /**
     * @var CacheTypeList
     */
    protected $_cacheTypeList;

    /**
     * @var _cacheFrontendPool
     */

    protected $_cacheFrontendPool;
    /**
     * @var _configWriter
     */

    protected $_configWriter;
    /**
     * @var _scopeConfig
     */

    protected $_scopeConfig;
    /**
     * @var $_encrypted
     */
    protected $_encrypted;
    
    /**
     * @var _TokenValue
     */
    private $_TokenValue;

    /**
     * @var _localeResolver
     */
    private $_localeResolver;

    /**
     * @var filesystem
     */
    protected $filesystem;

    /**
     * @var _storeManager
     */
    protected $_storeManager;

    /**
     * @var _currency
     */
    protected $_currency;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var Product
     */
    public $product;

    /**
     * Data constructor.
     * @param Context $context
     * @param TypeListInterface $cacheTypeList
     * @param Pool $cacheFrontendPool
     * @param ScopeConfigInterface $scopeConfig
     * @param filesystem $filesystem
     * @param WriterInterface $configWriter
     * @param Resolver $localeResolver
     * @param curl $curl
     * @param EncryptorInterface $encrypted
     * @param StoreManagerInterface $storeManager
     * @param Currency $currency
     */
    public function __construct(
        Context $context,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool,
        ScopeConfigInterface $scopeConfig,
        Filesystem $filesystem,
        WriterInterface $configWriter,
        Resolver $localeResolver,
        Registry $registry,
        Curl $curl,
        EncryptorInterface $encrypted,
        StoreManagerInterface $storeManager,
        Currency $currency
    ) {
        parent::__construct($context);
        $this->_cacheTypeList = $cacheTypeList;
        $this->_cacheFrontendPool = $cacheFrontendPool;
        $this->_scopeConfig = $scopeConfig;
        $this->_configWriter = $configWriter;
        $this->curl = $curl;
        $this->registry = $registry;
        $this->_filesystem = $filesystem;
        $this->_storeManager = $storeManager;
        $this->_currency = $currency;
        $this->_localeResolver = $localeResolver;
        $this->_encrypted = $encrypted;
    }

    /**
     * @param Version $subject
     */

    public function flushCache(Version $subject)
    {
        $types = ['config', 'layout', 'block_html', 'collections', 'reflection', 'db_ddl', 'eav', 'config_integration', 'config_integration_api', 'full_page', 'translate', 'config_webservice'];
        foreach ($types as $type) {
            $this->_cacheTypeList->cleanType($type);
        }
        foreach ($this->_cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }

    /**
     * Clear Config, config_webservice, Full_Page,
     */
    public function flushConfig()
    {
        $_types = [

            'config',
            'config_webservice',
            'full_page'
        ];

        foreach ($_types as $type) {
            $this->_cacheTypeList->cleanType($type);
        }
        foreach ($this->_cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }

    /**
     * Clear Config, config_webservice, Full_Page,
     */
    public function flushPage()
    {
        $_types = [
            'layout',
            'full_page',
            'block_html'
        ];

        foreach ($_types as $type) {
            $this->_cacheTypeList->cleanType($type);
        }
        foreach ($this->_cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }

    /**
     * @param $path = 'extension_name/general/data'
     * @param $value = '1'
     */
    public function setConfigData($path, $value)
    {
        $this->_configWriter->save($path, $value, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);
    }

    /**
     * @return mixed
     */
    public function getTokenValue()
    {
        return $this->_TokenValue;
    }

    /**
     * @param mixed $TokenValue
     */
    public function setTokenValue($TokenValue)
    {
        $this->_TokenValue = $TokenValue;
    }

    /**
     * @return mixed
     */
    public function getApiUrl()
    {
        return $this->_ApiUrl;
    }

    /**
     * @param mixed $ApiUrl
     */
    public function setApiUrl($ApiUrl)
    {
        $this->_ApiUrl = $ApiUrl;
    }

    /**
     * @return mixed -- Array with the Configuration that stored
     */
    public function getTalyPayConfigurationArrayFormat()
    {
        $fetchConfigResponse = $this->getConfigData(self::XML_SERVICE_CONFIGURATION);
        // $fetchConfigResponse = json_decode($fetchConfigResponse, true);
        return true;
    }

    /**
     * @param $Xml_Path *the XML Path reference to the desired configuration
     * @return *Value stored in the Config
     */
    public function getConfigData($Xml_Path)
    {
        $storeScope = ScopeInterface::SCOPE_STORE;
        return $this->_scopeConfig->getValue($Xml_Path, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $storeScope);
    }

    /**
     * @return * Current Language Local|string
     */
    public function getCurrentLocale()
    {
        $currentLocaleCode = $this->_localeResolver->getLocale(); // fr_CA
        return strstr($currentLocaleCode, '_', true);
    }

    public function encrypt($value)
    {
        return $this->_encrypted->encrypt($value);
    }

    public function decrypt($value)
    {
        return $this->_encrypted->decrypt($value);
    }

    /**
     * @param $data -- array of data or data that need to be sent
     * @param $api_end -- ending of url choose from the constant Values
     * @return array -- return array ($status_code,$curl_response )
     */

    public function curlPaymentPost($data, $api_end, $token)
    {
        $data_string = json_encode($data, true);
        $apiUrl = $api_end;
        //set curl options
        // $this->curl->setOption(CURLOPT_HEADER, 0);
        // $this->curl->setOption(CURLOPT_TIMEOUT, 60);
        // //set curl header
        // $headers = ["Content-Type" => "application/json", 'Authorization' => 'Bearer '.$token];
        // $this->curl->setHeaders($headers);
        // //Post request with url and parameter of an array type
        // $this->curl->post($apiUrl, $data_string);
        // //read response
        // $response = $this->curl->getBody();

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $apiUrl,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => $data_string,
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer '.$token
          ),
        ));

        $response = curl_exec($curl);
        $data = json_decode($response);
        file_put_contents(BP.'/var/log/talysuccess.log', ' ::curlPaymentPost:: '.print_r($data,true).PHP_EOL,FILE_APPEND);

        return ["statusCode" => 201,
            "response" => $data];
    }

    public function curlPaymentFinalStatus($order_token, $token)
    {
        $paymentmode =  $this->getConfigData(self::XML_PAYMENT_MODE);
        $api_end = $this->getLiveUrlValue().'accounts/payment/info/'.$order_token;
        if ($paymentmode == 0) {
            $api_end = $this->getTestUrlValue().'accounts/payment/info/'.$order_token;
        }

        $request = [];
        $data_string = json_encode($request, true);
        $apiUrl = $api_end;
        //set curl options
        // $this->curl->setOption(CURLOPT_HEADER, 0);
        // $this->curl->setOption(CURLOPT_TIMEOUT, 60);
        // //set curl header
        // $headers = ["Content-Type" => "application/x-www-form-urlencoded"];
        // $this->curl->setHeaders($headers);
        // //Post request with url and parameter of an array type
        // $this->curl->post($apiUrl, $request);
        // //read response
        // $response = $this->curl->getBody();
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $apiUrl,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => $data_string,
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded'
          ),
        ));

        $response = curl_exec($curl);
        $datas = json_decode($response);
        file_put_contents(BP.'/var/log/talysuccess.log', ' ::curlPaymentFinalStatus:: '.print_r($datas,true).PHP_EOL,FILE_APPEND);

        return ["response" => $datas];
    }

    public function curlPaymentProduct($data)
    {
        $currencycode = $this->getDefaultCurrencyCode();
        $paymentmode =  $this->getConfigData(self::XML_PAYMENT_MODE);
        $api_end = $this->getLiveUrlValue().'accounts/payment/calcPromotedInstallments';
        if ($paymentmode == 0) {
            $api_end = $this->getTestUrlValue().'accounts/payment/calcPromotedInstallments';
        }
        $token = $this->accessToken();
        $request = [
            'name' => '',
            'quantity' => 1,
            'unitPrice' => round($data, 2),
            'currency' => $currencycode,
        ];
        $data_string = json_encode($request, true);
        
        $apiUrl = $api_end;
        //set curl options

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $apiUrl,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => $data_string,
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer '.$token->access_token
          ),
        ));
        // print_r($data_string);
        // exit;
        $response = curl_exec($curl);
        
        $datas = json_decode($response);
        file_put_contents(BP.'/var/log/talysuccess.log', ' ::curlPaymentProduct:: '.print_r($response,true).PHP_EOL,FILE_APPEND);
        return ["response" => $datas];
    }

    public function accessToken()
    {
        $paymentmode =  $this->getConfigData(self::XML_PAYMENT_MODE);
        $URL = $this->getLiveUrlValue().'uaa/oauth/token';
        if ($paymentmode == 0) {
            $URL = $this->getTestUrlValue().'uaa/oauth/token';
        }
        $data_string = 'grant_type=password&username='.$this->getConfigData(self::XML_MERCHANT_ID).'&password='.$this->getConfigData(self::XML_MERCHANT_KEY).'&scope=ui';

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
          CURLOPT_POSTFIELDS => $data_string,
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent:'.$this->_storeManager->getStore()->getBaseUrl(),
            'Authorization: Basic bWVyY2hhbnQ6c2VjcmV0'
          ),
        ));

        $response = curl_exec($curl);
        // $response = '{"access_token":"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE2Nzc2NzMxODIsInVzZXJfbmFtZSI6IjEiLCJhdXRob3JpdGllcyI6WyJST0xFX01FUkNIQU5UIl0sImp0aSI6Ijk0MzYwODg1LWQ2NjUtNGMyMi04MGYwLTI5YjUzZmUxMzFjNSIsImNsaWVudF9pZCI6Im1lcmNoYW50Iiwic2NvcGUiOlsidWkiXX0.LeDXSjzY9EPSRXAnWXvt4-V0dijL22tDTtS575COpMM","token_type":"bearer","refresh_token":"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX25hbWUiOiIxIiwic2NvcGUiOlsidWkiXSwiYXRpIjoiOTQzNjA4ODUtZDY2NS00YzIyLTgwZjAtMjliNTNmZTEzMWM1IiwiZXhwIjoxNjc3Njc0MDgyLCJhdXRob3JpdGllcyI6WyJST0xFX01FUkNIQU5UIl0sImp0aSI6ImQzNGFhYzYzLWI2MjctNGNiZi1hMWMxLWRlZTA3MWQ1ZDZlYiIsImNsaWVudF9pZCI6Im1lcmNoYW50In0.RGTuRlOKkAiUpENQMbqeItylE4haG5nRCLihui49jrU","expires_in":8,"scope":"ui","jti":"94360885-d665-4c22-80f0-29b53fe131c5"}';

        file_put_contents(BP.'/var/log/talysuccess.log', ' ::::: accessToken :::: '.print_r(array($response),true).PHP_EOL,FILE_APPEND);
        // curl_close($curl);
        return json_decode($response);
    }

    public function getCalculatedInstallmentForPaymentPlans($planId, $token, $quoteTotal)
    {
        $paymentmode =  $this->getConfigData(self::XML_PAYMENT_MODE);
        $URL = $this->getLiveUrlValue().'accounts/payment/expectedInstallments';

file_put_contents(BP.'/var/log/getdata.log', ' ::DATA:: '.print_r($paymentmode,true).PHP_EOL,FILE_APPEND);
        if ($paymentmode == 0) {
            $URL = $this->getTestUrlValue().'accounts/payment/expectedInstallments';
        }
        $currencycode = $this->getDefaultCurrencyCode();
        $request = [
            'paymentPlanId' => $planId,
            'amount' => $quoteTotal,
            'currency' => $currencycode,
        ];
        $request = json_encode($request, true);
        // $this->curl->addHeader('Content-Type', 'application/json');
        // $this->curl->addHeader('Authorization', 'Bearer '.$token);
        // //Post request with url and parameter of an array type
        // $this->curl->post($URL, $request);
        // //read response
        // $response = $this->curl->getBody();

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
            'Authorization: Bearer '.$token
          ),
        ));

        $response = curl_exec($curl);
        file_put_contents(BP.'/var/log/talysuccess.log', ' ::::: getCalculatedInstallmentForPaymentPlans :::: '.print_r(array($response),true).PHP_EOL,FILE_APPEND);

        return json_decode($response);
    }
       
    public function getPlan($authtoken)
    {
        $paymentmode =  $this->getConfigData(self::XML_PAYMENT_MODE);
        $planurl = $this->getLiveUrlValue().'accounts/payment/plans';
        if ($paymentmode == 0) {
            $planurl = $this->getTestUrlValue().'accounts/payment/plans';
        }
        // $this->curl->addHeader('Content-Type', 'application/x-www-form-urlencoded');
        // $this->curl->addHeader('Authorization', 'Bearer '.$authtoken);
        // //set curl options
        // $this->curl->setOption(CURLOPT_HEADER, 0);
        // $this->curl->setOption(CURLOPT_TIMEOUT, 60);
        // //set curl header
        // //get request with url
        // $this->curl->get($planurl);
        // //read response
        // $response = $this->curl->getBody();
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
                'Authorization: Bearer '.$authtoken
            ),
        ));

        
        $response = curl_exec($curl);
        file_put_contents(BP.'/var/log/talysuccess.log', ' ::DATA:: '.print_r($planurl,true).PHP_EOL,FILE_APPEND);



        $datas = json_decode($response);
        return $datas;
    }

    /**
     * Get Image full path from view directory
     */

    public function getImageFullPath()
    {
        $mediapath = $this->_filesystem->getDirectoryRead(DirectoryList::APP)->getAbsolutePath();
        $modulePath =  $mediapath.'/Talypay/Taly/view/frontend/web/images';
        return $modulePath;
    }

    /**
     * Get store base currency code
     *
     * @return string
     */

    public function getBaseCurrencyCode()
    {
        return $this->_storeManager->getStore()->getBaseCurrencyCode();
    }
    
    /**
     * Get current store currency code
     *
     * @return string
     */

    public function getCurrentCurrencyCode()
    {
        return $this->_storeManager->getStore()->getCurrentCurrencyCode();
    }
    
    /**
     * Get default store currency code
     *
     * @return string
     */

    public function getDefaultCurrencyCode()
    {
        return $this->_storeManager->getStore()->getDefaultCurrencyCode();
    }
    
    /**
     * Get allowed store currency codes
     *
     * If base currency is not allowed in current website config scope,
     * then it can be disabled with $skipBaseNotAllowed
     *
     * @param bool $skipBaseNotAllowed
     * @return array
     */

    public function getAvailableCurrencyCodes($skipBaseNotAllowed = false)
    {
        return $this->_storeManager->getStore()->getAvailableCurrencyCodes($skipBaseNotAllowed);
    }
    
    /**
     * Get array of installed currencies for the scope
     *
     * @return array
     */

    public function getAllowedCurrencies()
    {
        return $this->_storeManager->getStore()->getAllowedCurrencies();
    }
    
    /**
     * Get current currency rate
     *
     * @return float
     */

    public function getCurrentCurrencyRate()
    {
        return $this->_storeManager->getStore()->getCurrentCurrencyRate();
    }
    
    /**
     * Get currency symbol for current locale and currency code
     *
     * @return string
     */

    public function getCurrentCurrencySymbol()
    {
        return $this->_currency->getCurrencySymbol();
    }

    /**
     * @return Product
     */

    public function getProduct()
    {
        if (is_null($this->product)) {
            $this->product = $this->registry->registry('product');
        }
        return $this->product;
    }

    public function getLiveUrlValue() {
        return $this->scopeConfig->getValue(self::XML_LIVE_URL,ScopeInterface::SCOPE_STORE);
    }

    public function getTestUrlValue() {
        return $this->scopeConfig->getValue(self::XML_TEST_URL,ScopeInterface::SCOPE_STORE);
    }
}
