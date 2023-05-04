<?php
namespace Talypay\Taly\Block\Adminhtml\OrderView;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Talypay\Taly\Helper\Data as zDataHelper;
use Talypay\Taly\Model\PaymentMethod;
use \Magento\Backend\Block\Template\Context;
use \Magento\Sales\Model\OrderRepository;

/**
 * Order custom tab
 *
 */
class View extends \Magento\Backend\Block\Template

{
    protected $_template = 'view/view_order_info.phtml';

    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    private $_orderRepository;

    /**
     * @var zDataHelper
     */
    private $_zDataHelper;

    /**
     * View constructor.
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */

    public function __construct(
        Context $context,
        OrderRepository $orderRepository,
        zDataHelper $zDataHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_orderRepository = $orderRepository;
        $this->_zDataHelper = $zDataHelper;
    }

    public function getContent()
    {
        $order = null;
        $orderId = $this->getRequest()->getParam('order_id');
        try {
            $order = $this->_orderRepository->get($orderId);
            $payment = $order->getPayment();
            if ($payment->getMethodInstance()->getCode() == PaymentMethod::CODE) {
                $additionalData = json_decode($payment->getAdditionalData(), true);
                $serviceSelected = 'Payment Not Capture';
                if (isset($additionalData['selected_service']['service_code']) && $additionalData['selected_service']['service_code'] != null) {
                    $serviceSelected = $additionalData['selected_service']['service_code'];
                    $serviceType = $additionalData['selected_service']['service_type'];
                }
                $fetchConfigResponse = $this->_zDataHelper->getTalyPayConfigurationArrayFormat();
                for ($i = 0, $iMax = $fetchConfigResponse; $i < $iMax; $i++) {
                    if (isset($serviceSelected)) {
                        return [
                            "service_code" => $serviceType,
                            "service_monthly_text" => $serviceSelected . " " . __('MONTHLY'),
                        ];
                    } else {
                        return [
                            "service_code" => '',
                            "service_monthly_text" => '',
                        ];
                    }
                }
            }
        } catch (InputException $e) {
        } catch (NoSuchEntityException $e) {
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function isHidden()
    {
        return false;
    }
}
