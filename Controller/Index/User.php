<?php
/**
 * @author    Reign <hello@reign.com.au>
 * @version   1.1.0
 * @copyright Copyright (c) 2019 Reign. All rights reserved.
 * @copyright Copyright (c) 2019 Bambora. All rights reserved.
 * @license   Proprietary/Closed Source
 * By viewing, using, or actively developing this application in any way, you are
 * henceforth bound the license agreement, and all of its changes, set forth by
 * Reign and Bambora. The license can be found, in its entirety, at this address:
 * http://www.reign.com.au/magento-licence
 */

namespace Bambora\Apaccheckout\Controller\Index;

use Magento\Framework\App\Action\Context;

class User extends \Magento\Framework\App\Action\Action
{
    protected $_api_username;
    protected $_api_password;
    protected $_submitUrl;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $_registry;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_session;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $_collectionFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    private $encryptor;

    /**
     * User constructor.
     * @param Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collectionFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     */
    public function __construct(
        Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Registry $registry,
        \Magento\Checkout\Model\Session $session,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    ) {
        $this->_resultPageFactory = $resultPageFactory;
        $this->_registry = $registry;
        $this->_session = $session;
        $this->_collectionFactory = $collectionFactory;
        $this->_storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|\Magento\Framework\View\Result\Page
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $post = $this->getRequest()->getPostValue();

        // Sample Parameters
        /*Array
        (
            [SessionId] => 190
            [SST] => 613e949a-9da6-4d9f-8d3e-4081a8e565b5
        )*/

        $sst = $post['SST'];
        $sessionId = $post['SessionId'];
        $errormessage = $this->queryTransaction($sst, $sessionId);
        $this->_registry->register('errormessage', $errormessage);

        /*if ($errormessage != "") {            
            $htm  ='    <script>';
            $htm .='        window.onload = function()'; 
            $htm .='        {';
            $htm .='            alert("Payment capturing error"); ';
            $htm .='            parent.removeOverlay();';
            $htm .='        }';
            $htm .='    </script>';

            echo $htm;
            exit;
        }*/

        $orders = $this->_collectionFactory->create()->addFieldToSelect(
            '*'
        )->setOrder(
            'created_at',
            'desc'
        )->setPageSize(1)
            ->setCurPage(1);

        $orderIncrementId = $orders->getFirstItem()->getIncrementId();
        $orderId = $orders->getFirstItem()->getId();
        $this->_session->setLastRealOrderId($orderIncrementId)
            ->setLastSuccessQuoteId($post['SessionId'])
            ->setLastQuoteId($post['SessionId'])
            ->setLastOrderId($orderId);

        $base_url = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        $this->_registry->register('base_url', $base_url);

        /*
        $html  = "<script>";
        $html .= "window.onload = function()";
        $html .= "{";
        $html .= "window.top.location.href = '" .  $base_url . "checkout/onepage/success/';";
        $html .= "}";
        $html .= "</script>";
        */

        //echo $html;
        //exit;
        $resultPage = $this->_resultPageFactory->create();
        return $resultPage;
    }

    /**
     * @param $sst
     * @param $sessionId
     * @return string
     */
    protected function queryTransaction($sst, $sessionId)
    {
        $this->_getAPICred();

        $headers = [
            "Content-type: application/x-www-form-urlencoded",
            "Cache-Control: no-cache"
        ];

        $postField = 'UserName=' . $this->_api_username . '&';
        $postField .= 'Password=' . $this->_api_password . '&';
        $postField .= 'SST=' . $sst . '&';
        $postField .= 'SessionId=' . $sessionId . '&';
        $postField .= 'Query=true';

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->_submitUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postField);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $responseCurl = curl_exec($ch);
        curl_close($ch);

        /* Return the follwing
        <html><body><form><input type="hidden" name="SessionId" value="190" /><input type="hidden" name="SST" value="613e949a-9da6-4d9f-8d3e-4081a8e565b5" /><input type="hidden" name="SessionKey" value="123" /><input type="hidden" name="CustRef" value="190" /><input type="hidden" name="CustNumber" value="2" /><input type="hidden" name="Amount" value="10900" /><input type="hidden" name="Surcharge" value="" /><input type="hidden" name="AmountIncludingSurcharge" value="10900" /><input type="hidden" name="Result" value="1" /><input type="hidden" name="DeclinedCode" value="" /><input type="hidden" name="DeclinedMessage" value="" /><input type="hidden" name="Receipt" value="92699209" /><input type="hidden" name="TxDateTime" value="2018-02-07+21%3a59%3a56" /><input type="hidden" name="SettlementDate" value="2018-02-08" /><input type="hidden" name="MaskedCard" value="411111******1111" /><input type="hidden" name="CardHolderName" value="dsaf dsafasdf" /><input type="hidden" name="ExpiryDate" value="11/21" /><input type="hidden" name="CardType" value="Visa" /></form></body></html>test<pre>
        */

        $string = $responseCurl;

        $result = preg_match('/<input type="hidden" name="DeclinedMessage" value="(.*?)"/', $string, $matches);
        $resultsecond = preg_match('/<input type="hidden" name="DeclinedCode" value="(.*?)"/', $string, $matchesarray);
        $resultresponse = preg_match('/<input type="hidden" name="Result" value="(.*?)"/', $string,
            $resultresponsearray);
        if ($resultresponsearray[1] == "0") {
            return $matchesarray[1] . " " . str_replace("+", " ", $matches[1]);
        } else {
            return "";
        }
    }

    /**
     * Set API Credentials
     */
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