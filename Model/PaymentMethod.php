<?php
namespace Taly\Taly\Model;
use Magento\Sales\Model\Order;

/**
 * MD Custom Payment Method Model
 */
class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod {
    /**
     * Payment Method code
     *
     * @var string
     */
    protected $_code = 'taly';
    const CODE = 'taly';

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = array()
    ) 
    { 
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->_customerSession = $customerSession;

    }

    /**
     * Authorize payment
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface|Payment $payment
     * @param  $amount
     * @return $this
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */

     public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
     {
         $this->_customerSession->setCallBackURL("");
         return $this->_placeOrder($payment);
     }

    /**
     * @param \Magento\Framework\DataObject $payment
     */

    private function _placeOrder(\Magento\Framework\DataObject $payment)
    {
        //  $this->_zLogger->info('inside Place an Order');
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $this->_customerSession->setEntityID($order->getRealOrderId());
        try {
            $orderState = Order::STATE_NEW;
            $order->setState($orderState);
            $order->setStatus(Order::STATE_PENDING_PAYMENT);
            $order->save();
        } catch (\Exception $e) {
            //  $this->_zLogger->error( $e->getMessage());
        }
    }

}