<?php
/**
 * @author    Reign <hello@reign.com.au>
 * @version   1.1.0
 * @copyright Copyright (c) 2018 Reign. All rights reserved.
 * @copyright Copyright (c) 2018 Bambora. All rights reserved.
 * @license   Proprietary/Closed Source
 * By viewing, using, or actively developing this application in any way, you are
 * henceforth bound the license agreement, and all of its changes, set forth by
 * Reign and Bambora. The license can be found, in its entirety, at this address:
 * http://www.reign.com.au/magento-licence
 */

namespace Bambora\Apaccheckout\Controller\Index;

use Magento\Framework\App\Action\Context;

class Index extends \Magento\Framework\App\Action\Action
{
    protected $_pageFactory;
    protected $_api_username;
    protected $_api_password;
    protected $_submitUrl;
    protected $_transactionType;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_session;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Framework\Data\Form\FormKey
     */
    protected $_formKey;

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $_cartRepository;

    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    protected $_quoteFactory;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $_registry;

    /**
     * @var
     */
    private $encryptor;

    /**
     * Index constructor.
     * @param Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Customer\Model\Session $session
     * @param \Magento\Framework\Data\Form\FormKey $formKey
     */
    public function __construct(
        Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\Session $session,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    ) {
        $this->_resultPageFactory = $resultPageFactory;
        $this->_scopeConfig = $scopeConfig;
        $this->_storeManager = $storeManager;
        $this->_session = $session;
        $this->_checkoutSession = $checkoutSession;
        $this->_formKey = $formKey;
        $this->_cartRepository = $cartRepository;
        $this->_quoteFactory = $quoteFactory;
        $this->_registry = $registry;
        $this->encryptor = $encryptor;

        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|\Magento\Framework\View\Result\Page
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $baseUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

        $this->_getAPICred();

        switch ($this->_scopeConfig->getValue('payment/bambora_apaccheckout/payment_action')) {
            case 'authorize_capture':
                $this->_transactionType = \Bambora\Apacapi\Model\Constant::CHECKOUT_V1_PURCHASE;
                break;
            case 'authorize':
                $this->_transactionType = \Bambora\Apacapi\Model\Constant::CHECKOUT_V1_PREAUTH;
                break;
        }

        $accountNumber = $this->_scopeConfig->getValue('payment/bambora_apaccheckout/account_number',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $sessionKey = $this->_formKey->getFormKey();
        $grandTotal = $this->_checkoutSession->getQuote()->getGrandTotal();

        $headers = array(
            "Content-type: application/x-www-form-urlencoded",
            "Cache-Control: no-cache",
        );

        $amount = $grandTotal * 100;
        $serverUrl = base64_encode($baseUrl . 'bamboraintegrated/index/server?form_key=' . $sessionKey);
        $userUrl = base64_encode($baseUrl . 'bamboraintegrated/index/user?form_key=' . $sessionKey);

        $cartId = $this->_checkoutSession->getQuote()->getId();
        $quote = $this->_cartRepository->get($cartId);

        if ($this->_session->isLoggedIn()) {
            $customerNumber = $this->_session->getCustomer()->getId();
        } else {
            $customerNumber = 'guest';
            $customerEmail = str_replace(" ", "+", $this->getRequest()->getParam("email"));
            $quote->setCustomerEmail($customerEmail);
            $quote->setCustomerIsGuest(1);
            $quote->save();
        }

        $reservedOrderId = $quote->getReservedOrderId();

        if (!$reservedOrderId) {
            $quote->reserveOrderId()->save();
            $reservedOrderId = $quote->getReservedOrderId();
        }

        $postField = 'UserName=' . $this->_api_username . '&';
        $postField .= 'password=' . $this->_api_password . '&';
        $postField .= 'CustRef=' . $reservedOrderId . '&';
        $postField .= 'CustNumber=' . $customerNumber . '&';
        $postField .= 'Amount=' . $amount . '&';
        $postField .= 'SessionId=' . $quote->getId() . '&';
        $postField .= 'SessionKey=' . $sessionKey . '&';
        $postField .= 'DL=' . $this->_transactionType . '&';
        $postField .= 'ServerURL=' . $serverUrl . '&';
        $postField .= 'UserURL=' . $userUrl . '&';

        if ($accountNumber != "") {
            $postField .= 'AccountNumber=' . $accountNumber . '&';
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->_submitUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postField);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $responsecurl = curl_exec($ch);
        curl_close($ch);

        $this->_registry->register('sessionid', $quote->getId());
        $this->_registry->register('submiturl', $this->_submitUrl);
        $this->_registry->register('sst', $responsecurl);

        //$assetRepo = $objectManager->get('\Magento\Framework\View\Asset\Repository');
        //echo $assetRepo->getUrl("Bambora_Apaccheckout::images/spinner.jpg");

        /*$html  = '<html>';
        $html .= '<body style="width: 400px; min-width: 320px; height: 704px; margin: 35px auto 0px; display: table-row; background-size: 25%; text-align: center; box-shadow: rgb(0, 0, 0) 0px 0px 70px 0px;">';
        $html .= '<form id="integratedcheckoufrm" action="'.$this->_submitUrl.'" >';
        $html .= '<input type="hidden" name="SST" value="" />';
        $html .= '<input type="hidden" name="SessionId" value="'.$cart->getQuote()->getId().'" />';
        $html .= '</form>';
        $html .= '<script>';
        $html .= 'window.onload = function() {';
        $html .= 'document.getElementsByName("SST")[1].value = document.getElementsByName("SST")[0].value;';
        $html .= 'document.getElementById("integratedcheckoufrm").submit();';
        $html .= '}';
        $html .= '</script>';
        $html .= '</body>';
        $html .= '</html>';
       
        echo $html;

        exit;*/
        $resultPage = $this->_resultPageFactory->create();

        return $resultPage;
    }

    protected function _getAPICred()
    {
        if ($this->_scopeConfig->getValue('payment/bambora_apaccheckout/mode',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == "sandbox") {

            $this->_api_username = $this->encryptor->decrypt($this->_scopeConfig->getValue('payment/bambora_apaccheckout/sandbox_api_username',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
            $this->_api_password = $this->encryptor->decrypt($this->_scopeConfig->getValue('payment/bambora_apaccheckout/sandbox_api_password',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
            $this->_submitUrl = \Bambora\Apacapi\Model\Constant::SANDBOX_INTEGRATED_CHECKOUT_URL;

        } else {
            $this->_api_username = $this->encryptor->decrypt($this->_scopeConfig->getValue('payment/bambora_apaccheckout/live_api_username',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
            $this->_api_password = $this->encryptor->decrypt($this->_scopeConfig->getValue('payment/bambora_apaccheckout/live_api_password',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
            $this->_submitUrl = \Bambora\Apacapi\Model\Constant::LIVE_INTEGRATED_CHECKOUT_URL;
        }
    }
}