<?php
namespace Taly\Taly\Controller\Payment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DB\Transaction;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Service\InvoiceService;
use Taly\Taly\Helper\Data as zDataHelper;
use Taly\Taly\Helper\Order\OrderTransactionHelperInterface;
use Taly\Taly\Logger\Zlogger;
use Taly\Taly\Model\CheckoutConfigProvider;
use Taly\Taly\Model\PaymentMethod;
use \Magento\Checkout\Model\Session as Checkoutsession;
use \Magento\Customer\Model\Session;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\App\Request\Http;
use \Magento\Framework\Session\SessionManagerInterface;
use \Magento\Framework\Webapi\Rest\Request;
use \Magento\Sales\Model\OrderRepository;
use \Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Framework\Registry;

class Success extends \Magento\Framework\App\Action\Action

{

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;
    protected $resultJsonFactory;
    protected $request;
    protected $_customerSession;
    protected $_zLogger = null;
    private $params = ['amount', 'created_at', 'status', 'transaction_id', 'merchant_order_reference', 'signature'];
    /**
     * @var OrderTransactionHelperInterface
     */
    private $_orderTransactionHelper;
    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    private $_orderRepository;
    /**
     * @var $_zDataHelper
     */
    private $_zDataHelper;
    /**
     * @var Order\Payment\Transaction\Builder
     */
    private $_transactionBuilder;
    /**
     * @var InvoiceService
     */
    private $_invoiceService;
    /**
     * @var InvoiceSender
     */
    private $_invoiceSender;
    /**
     * @var Transaction
     */
    private $_transactionDB;
    /**
     * @var Http
     */
    private $httpRequest;
    /**
     * @var Transaction
     */
    private $transactionDB;
    /**
     * @var InvoiceSender
     */
    private $invoiceSender;
    /**
     * @var InvoiceService
     */
    private $invoiceService;

    protected $_checkoutSession;
    /**
     * @var string
     */
    private $pdata;
    /**
     * @var \Magento\Framework\Webapi\Rest\Request
     */
    private $webrequest;
    /**
     * @var \Magento\Framework\Session\SessionManagerInterface
     */
    private $_coreSession;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        PageFactory $resultPageFactory,
        RequestInterface $request,
        Session $customerSession,
        Zlogger $zLogger,
        Checkoutsession $checkoutSession,
        OrderTransactionHelperInterface $orderTransactionHelper,
        OrderRepository $orderRepository,
        zDataHelper $zDataHelper,
        Builder $transactionBuilder,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        SessionManagerInterface $coreSession,
        Transaction $transactionDB,
        Http $httpRequest,
        Request $webrequest,
        CheckoutConfigProvider $checkoutconfig,
        Registry $registry
    ) {
        parent::__construct(
            $context
        );
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->request = $request;
        $this->_customerSession = $customerSession;
        $this->_zLogger = $zLogger;
        $this->_orderTransactionHelper = $orderTransactionHelper;
        $this->_orderRepository = $orderRepository;
        $this->_zDataHelper = $zDataHelper;
        $this->_transactionBuilder = $transactionBuilder;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->transactionDB = $transactionDB;
        $this->httpRequest = $httpRequest;
        $this->webrequest = $webrequest;
        $this->_checkoutSession = $checkoutSession;
        $this->_coreSession = $coreSession;
        $this->_checkoutconfig = $checkoutconfig;
        $this->_registry = $registry;
    }

    public function execute()
    {
        $paymnetStatus = $this->getRequest()->getParam('status');
        $order = $this->_checkoutSession->getLastRealOrder();
        $orderId = $order->getEntityId();
        $order = $this->_orderRepository->get($orderId);
        $payment = $this->_checkoutSession->getLastRealOrder()->getPayment();
        $additiondata = json_decode($payment['additional_data'], true);
        $order_token = $payment['last_trans_id'];
        $token = $additiondata['selected_service']['order_token']['access_token'];
        $curlResponse = $this->_zDataHelper->curlPaymentFinalStatus($order_token, $token);
        file_put_contents(BP . '/var/log/talysuccess.log', ' ::curlResponse:: ' . print_r($curlResponse, true) . PHP_EOL, FILE_APPEND);

        $resultJsonFactory = $this->resultJsonFactory->create();
        $this->_coreSession->start();
        $pageMessage = '';
        $url = $this->_url->getBaseUrl();
        try {
            if (!empty($curlResponse)) {
                $transactionAmount = $curlResponse['response']->amount;
                $createdAt = $curlResponse['response']->orderDate;
                $transactionStatus = $curlResponse['response']->status;
                $transactionID = $curlResponse['response']->orderToken;
                $paymentPlanId = $curlResponse['response']->paymentPlanId;
                $paymentData = $this->_orderTransactionHelper->getPaymentData($order_token);
                if (isset($transactionAmount, $createdAt, $transactionStatus, $transactionID, $paymentPlanId)) {
                    /** @var TransactionInterface $transactionData -- Not used  Due to Change of Logic */
                    // $transactionData = $this->_orderTransactionHelper->getTransactionData($transactionID);
                    /** @var TransactionInterface $paymentData */
                    $paymentData = $this->_orderTransactionHelper->getPaymentData($transactionID);
                    if (isset($paymentData)) {
                        $orderId = $paymentData->getParentId();
                        /** @var Order $order -- Get the Order Model From OrderID */
                        //  $order = $this->_orderTransactionHelper->getOrderModel($orderId);
                        $order = $this->_orderRepository->get($orderId);
                        /** @var Order\Payment $payment -- Get The Payment */
                        $payment = $order->getPayment();
                        $orderIncID = $order->getIncrementId();
                        $merchant_reference_no = $order->getIncrementId();
                        $additionalData = json_decode($payment->getAdditionalData(), true);
                        $paymentSelectedService = $additionalData['selected_service']['service_code'];
                        $paymentSelectedServiceType = $additionalData['selected_service']['service_type'];
                        if ($payment->getMethodInstance()->getCode() == PaymentMethod::CODE) {
                            if (1 === 1) {
                                $orderStatus = $order->getStatus();
                                file_put_contents(BP . '/var/log/talysuccess.log', ' ::orderStatus:: ' . print_r($orderStatus, true) . PHP_EOL, FILE_APPEND);
                                file_put_contents(BP . '/var/log/talysuccess.log', ' ::transactionID:: ' . print_r($transactionID, true) . PHP_EOL, FILE_APPEND);
                                if ($paymnetStatus == 'failure') {
                                    $order->setStatus(Order::STATE_HOLDED);
                                    $order->setState(Order::STATE_HOLDED);
                                    $trans = $this->_transactionBuilder;
                                    $transaction = $trans->setPayment($payment)
                                        ->setOrder($order)
                                        ->setTransactionId($transactionID)
                                        ->setFailSafe(true)
                                    //build method creates the transaction and returns the object
                                        ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
                                    $payment->addTransactionCommentsToOrder($transaction, "Failed ");
                                    $payment->setAmountPaid(0);
                                    $_invoices = $order->getInvoiceCollection();
                                    $this->_registry->register('isSecureArea', true);
                                    if ($_invoices) {
                                        foreach ($_invoices as $invoice) {
                                            $invoice->delete();
                                        }
                                    }
                                    $order->save();
                                    $payment->save();
                                    try {
                                        $this->_orderRepository->save($order);
                                    } catch (\Exception$e) {
                                        $this->messageManager->addExceptionMessage($e, $e->getMessage());
                                    }
                                    $pageMessage = __("PAYMENT_FAILED") . " " . $orderId;
                                    $url = ($this->_url->getRedirectUrl($this->_url->getBaseUrl() . 'checkout/onepage/failure/'));
                                } else {
                                    switch ($transactionStatus) {
                                        case "CONFIRMED":
                                            if ((!($order->getStatus() != Order::STATE_CLOSED) || ($order->getStatus() != Order::STATE_CANCELED) || ($order->getStatus() != Order::STATE_HOLDED))) {
                                                $payment->setLastTransId($transactionID);
                                                $payment->setTransactionId($transactionID);
                                                $trans = $this->_transactionBuilder;
                                                $transaction = $trans->setPayment($payment)
                                                    ->setOrder($order)
                                                    ->setTransactionId($transactionID)
                                                    ->setFailSafe(true)
                                                    ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
                                                $payment->addTransactionCommentsToOrder(
                                                    $transaction,
                                                    "Paid "
                                                );
                                                $payment->setBaseAmountPaid($order->getBaseGrandTotal());
                                                $payment->setParentTransactionId(null);
                                                $payment->save();
                                                $payment->setParentTransactionId(null);
                                                file_put_contents(BP . '/var/log/talysuccess.log', ' ::DEBUG:: ' . print_r("ascxczc", true) . PHP_EOL, FILE_APPEND);

                                                if ($order->canInvoice()) {
                                                    $invoice = $order->prepareInvoice();
                                                    $invoice = $this->invoiceService->prepareInvoice($order);
                                                    $invoice->register();
                                                    $invoice->save();
                                                    $transactionSave = $this->transactionDB->addObject(
                                                        $invoice
                                                    )->addObject(
                                                        $invoice->getOrder()
                                                    );
                                                    $transactionSave->save();
                                                    $this->invoiceSender->send($invoice);
                                                    $order->addStatusHistoryComment(
                                                        __('Notified customer about invoice #%1.', $invoice->getId())
                                                    )
                                                        ->setIsCustomerNotified(true)
                                                        ->save();

                                                } else {
                                                    $_invoices = $order->getInvoiceCollection();
                                                    $this->_registry->register('isSecureArea', true);
                                                    if ($_invoices) {
                                                        $inv_count = $_invoices->count();
                                                        if ($inv_count > 1) {
                                                            foreach ($_invoices as $invoice) {
                                                                $invoice->delete();
                                                            }
                                                            $invoice = $this->invoiceService->prepareInvoice($order);
                                                            $invoice->register();
                                                            $invoice->save();
                                                            $transactionSave = $this->transactionDB->addObject(
                                                                $invoice
                                                            )->addObject(
                                                                $invoice->getOrder()
                                                            );
                                                            $transactionSave->save();
                                                            $this->invoiceSender->send($invoice);
                                                            //send notification code
                                                            $order->addStatusHistoryComment(
                                                                __('Notified customer about invoice #%1.', $invoice->getId())
                                                            )
                                                                ->setIsCustomerNotified(true)
                                                                ->save();
                                                        } elseif ($inv_count == 0) {
                                                            $invoice = $this->invoiceService->prepareInvoice($order);
                                                            $invoice->register();
                                                            $invoice->save();
                                                            $transactionSave = $this->transactionDB->addObject(
                                                                $invoice
                                                            )->addObject(
                                                                $invoice->getOrder()
                                                            );
                                                            $transactionSave->save();
                                                            $this->invoiceSender->send($invoice);
                                                            //send notification code
                                                            $order->addStatusHistoryComment(
                                                                __('Notified customer about invoice #%1.', $invoice->getId())
                                                            )
                                                                ->setIsCustomerNotified(true)
                                                                ->save();
                                                        }
                                                    }
                                                }
                                                $order->setStatus(Order::STATE_PROCESSING);
                                                $order->setState(Order::STATE_PROCESSING);
                                                try {
                                                    $this->_orderRepository->save($order);
                                                } catch (\Exception$e) {
                                                    $this->messageManager->addExceptionMessage($e, $e->getMessage());
                                                }
                                            }

                                            $pageMessage = __("PAYMENT_ACCEPTED");
                                            // $url = ($this->_url->getRedirectUrl($this->_url->getBaseUrl() . 'talypay/Checkout/SuccessPage/'));
                                            $url = ($this->_url->getRedirectUrl($this->_url->getBaseUrl() . 'checkout/onepage/success/'));

                                            break;
                                        case "REJECTED":
                                            if (!($order->getStatus() != Order::STATE_PROCESSING)) {
                                                $order->setStatus(Order::STATE_HOLDED);
                                                $order->setState(Order::STATE_HOLDED);
                                                $trans = $this->_transactionBuilder;
                                                $transaction = $trans->setPayment($payment)
                                                    ->setOrder($order)
                                                    ->setTransactionId($transactionID)
                                                    ->setFailSafe(true)
                                                //build method creates the transaction and returns the object
                                                    ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
                                                $payment->addTransactionCommentsToOrder($transaction, "Failed ");
                                                $payment->setAmountPaid(0);
                                                $_invoices = $order->getInvoiceCollection();
                                                $this->_registry->register('isSecureArea', true);
                                                if ($_invoices) {
                                                    foreach ($_invoices as $invoice) {
                                                        $invoice->delete();
                                                    }
                                                }
                                                $order->save();
                                                $payment->save();
                                                try {
                                                    $this->_orderRepository->save($order);
                                                } catch (\Exception$e) {
                                                    $this->messageManager->addExceptionMessage($e, $e->getMessage());
                                                }
                                            }

                                            $pageMessage = __("PAYMENT_FAILED") . " " . $orderId;
                                            $url = ($this->_url->getRedirectUrl($this->_url->getBaseUrl() . 'checkout/onepage/failure/'));
                                            break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception$exception) {
            //  $this->_zLogger->critical($exception->getMessage());
        }
        $this->_coreSession->setPageMessage($pageMessage);
        $this->_redirect->redirect($this->getResponse(), ($url));
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        // TODO: Implement validateForCsrf() method.
        return true;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        // TODO: Implement createCsrfValidationException() method.
        return null;
    }
}
