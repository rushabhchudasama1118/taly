<?php
namespace Talypay\Taly\Controller\Checkout;

use \Magento\Framework\App\ResponseInterface;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\View\Result\PageFactory;
use \Magento\Customer\Model\Session;
use \Magento\Framework\App\Action\Action;

class RedirectPage extends Action
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    private $_resultPageFactory;

    /**
     * @var \Magento\Customer\Model\Session
     */
    private $_customerSession;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Session $customerSession
    ) {
        parent::__construct($context);
        $this->_resultPageFactory = $resultPageFactory;
        $this->_customerSession = $customerSession;
    }

    /**
     * Blog Index, shows a list of recent blog posts.
     *
     * @return \Magento\Framework\View\Result\PageFactory   
     */
    public function execute()
    {
        $resultPage = $this->_resultPageFactory->create();
        $callBackUrl = $this->_customerSession->getCallBackURL();
        $this->_customerSession->setCallBackURL($this->_url->getBaseUrl());
        return $this->_redirect->redirect($this->getResponse(), $callBackUrl);
    }
}
