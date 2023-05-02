<?php
namespace Taly\Taly\Observer;

use Magento\Checkout\Exception;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Taly\Taly\Helper\Data as zDataHelper;
use Taly\Taly\Logger\Zlogger as LoggerInterface;
use Taly\Taly\Model\PaymentMethod;
use Magento\Sales\Model\Order;

class OrderPlaceAfter implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var _zLogger
     */
    protected $_zLogger;
    /**
     * @var _url
     */
    protected $_url;

    /**
     * @var _orderRepository
     */
    private $_orderRepository;
    /**
     * @var _cookieManager
     */
    private $_cookieManager;
    /**
     * @var zDataHelper
     */
    private $_zDataHelper;
    /**
     * @var \Magento\Customer\Model\Session
     */
    private $_customerSession;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $_checkoutSession;
    /**
     * @var Order\Payment\Transaction\Builder
     */
    private $_transactionBuilder;
    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    private $messageManager;

    public function __construct(
        LoggerInterface $logger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\Response\RedirectInterface $redirect,
        \Magento\Framework\UrlInterface $url,
        \Magento\Framework\Controller\ResultFactory $resultFactory,
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        zDataHelper $zDataHelper,
        \Magento\Sales\Model\Order\Payment\Transaction\Builder $transactionBuilder,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Store\Model\StoreManagerInterface $StoreManagerInterface,
        \Magento\Framework\Registry $Registry
    ) {
        $this->_zLogger = $logger;
        $this->_checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->_url = $url;
        $this->_orderRepository = $orderRepository;
        $this->_cookieManager = $cookieManager;
        $this->_zDataHelper = $zDataHelper;
        $this->_transactionBuilder = $transactionBuilder;
        $this->messageManager = $messageManager;
        $this->storeManagerInterface =  $StoreManagerInterface;
        $this->registry = $Registry;
    }

    /**
     * @inheritDoc
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     * @throws \Magento\Framework\Exception\PaymentException
     */

    public function execute(Observer $observer)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Sales\Model\Order $order -- Get the Order From Observer */
        $order = $observer->getEvent()->getOrder();
        $payment = $order->getPayment();
        if ($payment->getMethodInstance()->getCode() == PaymentMethod::CODE) {
            try {
                /** @var Quote $quoteObj */
                $quoteObj = $this->_checkoutSession->getQuote();
                /** @var  $selectedService -- Get Selected Service from Cache */
                $selectedServiceJson = $this->_cookieManager->getCookie('selected_service');
                $selectedServiceArray = json_decode($selectedServiceJson, true);
                $selectedService = $selectedServiceArray['service_code'];
                $selectedServiceType = $selectedServiceArray['service_type'];
                $additionalData = json_encode(['selected_service' => ['service_code' => $selectedService, 'service_type' => $selectedServiceType]]);
                /** @var Cart $cartItems -- Get All Item in Cart */
                $cartItems = $this->_checkoutSession->getQuote()->getAllVisibleItems();
                $storeManager = $this->storeManagerInterface;
                $currencyCode = $storeManager->getStore()->getCurrentCurrencyCode();
                $this->_zLogger->info("API URL:" .$selectedService);
                $merchant_key = $this->_zDataHelper->getConfigData(zDataHelper::XML_MERCHANT_ID);
                $merchant_reference_no = $order->getIncrementId();
                $amount = $quoteObj->getGrandTotal();
                $market_code = $this->_zDataHelper->getConfigData(zDataHelper::XML_DEFAULT_COUNTRY_CODE);
                $salt = $this->_zDataHelper->decrypt($this->_zDataHelper->getConfigData(zDataHelper::XML_MERCHANT_SALT));
                $orderString = implode("|", [$merchant_key, $merchant_reference_no, $amount, $currencyCode, $market_code, $salt]);
                $this->_zLogger->info("API URL:" .$order->getId());
                $orderItemData = [];
                foreach ($cartItems as $item) {
                    $orderItemData = [
                        'name'=> $item->getProductType(),
                        'quantity'=> $item->getQty(),
                        'unitPrice'=> $item->getPrice(),
                        'currency'=> $currencyCode
                    ];
                }
                $precode = 'M';
                $paymentmode = $this->_zDataHelper->getConfigData(zDataHelper::XML_PAYMENT_MODE);
                if ($paymentmode == 0) {
                    $curlPaymentUrl = $this->_zDataHelper->getTestUrlValue().'accounts/payment/initiate';
                } else {
                    $curlPaymentUrl = $this->_zDataHelper->getLiveUrlValue().'accounts/payment/initiate';
                }

                $request = [
                    'merchantOrderId' => $precode.'-'.$order->getIncrementId(),
                    'amount' => $quoteObj->getGrandTotal(),
                    'shippingCharges' => $order->getShippingAmount(),
                    'currency' => $currencyCode,
                    'redirectUrl'=> $this->_url->getBaseUrl() . 'talypay/Payment/success/',
                    'languageCode'=> 'en',
                    'paymentPlanId'=> $selectedServiceType
                ];
                $ccesstoekn =$this->_zDataHelper->accessToken();
                $token= $ccesstoekn->access_token;
                $curlResponse = $this->_zDataHelper->curlPaymentPost($request, $curlPaymentUrl, $token);
                file_put_contents(BP.'/var/log/getStoreStock.log', ' ::::: After Order Place :::: '.print_r(array($curlResponse),true).PHP_EOL,FILE_APPEND);
                if (isset($curlResponse)) {
                    if (201 == 201) {
                        $this->_customerSession->setCurlResponseJson($curlResponse);
                        if (1 == 1) {
                            if ($paymentmode == 0) {
                                $callBackUrl = "https://dev-taly.io/checkout/securecheckout/".$curlResponse['response']->orderToken;
                            } else {
                                $callBackUrl = "https://taly.io/checkout/securecheckout/".$curlResponse['response']->orderToken;
                            }
                            $this->_customerSession->setCallBackURL($callBackUrl);
                            $orderState = Order::STATE_PENDING_PAYMENT;
                            $order->setState($orderState);
                            $order->setStatus(Order::STATE_PENDING_PAYMENT);
                            $order->save();
                            $this->_orderRepository->save($order);
                            $payment = $order->getPayment();
                            $payment->setLastTransId($curlResponse['response']->orderToken);
                            $additionalData = json_encode(['selected_service' => ['service_code' => $selectedService, 'service_type' => $selectedServiceType,'order_token' =>  $ccesstoekn ]]);
                            $payment->setAdditionalData($additionalData);
                            $trans = $this->_transactionBuilder;
                            $transaction = $trans->setPayment($payment)
                                ->setOrder($order)
                                ->setTransactionId($curlResponse['response']->orderToken)
                                ->setFailSafe(true)
                                ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
                            $payment->addTransactionCommentsToOrder($transaction, "Created ");
                            $payment->setParentTransactionId(null);
                            $payment->save();
                            $order->save();
                        } else {
                            $message = "There is problem with Signature of your order";
                            throw new \Magento\Framework\Exception\PaymentException(__($message));
                        }
                    } else {
                        $message = "Something Went Wrong Contact Administrator";
                        if ($curlResponse['statusCode'] == 400) {
                            $errorResponse = json_decode($curlResponse['response'], true);
                            $message = $errorResponse['message'] . "<br>";
                            if (isset($errorResponse['details'])) {
                                for ($i = 0, $iMax = count($errorResponse['details']); $i < $iMax; $i++) {
                                    $message .= $errorResponse['details'][$i]['field'] . ": " . $errorResponse['details'][$i]['error'] . "<br>";
                                }
                            }
                        }
                        $order->cancel();
                        $order->save();
                        $this->_checkoutSession->restoreQuote();
                        $registry = $this->registry;
                        $registry->register('isSecureArea', 'true');
                        $order->delete();
                        $registry->unregister('isSecureArea');
                        throw new \Magento\Framework\Exception\PaymentException(__($message));
                        // throw new Exception(__('Not Valid Information Provided'));
                    }
                } else {
                    $order->cancel();
                    $order->save();
                    $this->_checkoutSession->restoreQuote();
                    $registry = $this->registry;
                    $registry->register('isSecureArea', 'true');
                    $order->delete();
                    $registry->unregister('isSecureArea');
                    $message = "Something Went Wrong Contact Administrator";
                    throw new \Magento\Framework\Exception\PaymentException(__($message));
                }
            } catch (Exception $e) {
                //  $this->_zLogger->error($e->getMessage());
                $this->messageManager->addExceptionMessage($e, $e->getMessage());
            } catch (\Exception $e) {
                throw new \Magento\Framework\Exception\PaymentException(__($e->getMessage()));
            }
        }
    }
}
