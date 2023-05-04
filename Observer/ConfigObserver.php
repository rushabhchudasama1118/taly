<?php
namespace Talypay\Taly\Observer;

/**
* @category    Taly
* @package     Talypay_Talypay
* @copyright Copyright © 2020 Taly TalyPay. All rights reserved.
* @author
*/

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Talypay\Taly\Helper\Data as zDataHelper;

class ConfigObserver implements \Magento\Framework\Event\ObserverInterface
{

    /**
     * @var DataHelper
     */
    protected $_zDataHelper;

    /**
     *  constructor.
     * @param zDataHelper $zDataHelper
     */

    public function __construct(
        zDataHelper $zDataHelper
    ) {
        $this->_zDataHelper = $zDataHelper;
    }

    public function execute(EventObserver $observer)
    {
        $curlResponseHealthy = $this->_zDataHelper->accessToken();
        $activeval = $this->_zDataHelper->getConfigData('payment/taly/active');
        if (!empty($curlResponseHealthy->access_token)) {
            if ($activeval==1) {
                $this->_zDataHelper->setConfigData(zDataHelper::XML_MERCHANT_ACTIVE, 1);
            }
        } else {
            $this->_zDataHelper->setConfigData(zDataHelper::XML_MERCHANT_ACTIVE, 0);
        }
        $this->_zDataHelper->flushConfig();
    }
}
