<?php
/**
 * @author    Reign <hello@reign.com.au>
 * @version   1.0.0
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
 
class User extends \Magento\Framework\App\Action\Action
{
    
    protected $_api_username;
    protected $_api_password;
    protected $_submiturl;
    protected $_resultPageFactory;

    
    public function __construct(Context $context, \Magento\Framework\View\Result\PageFactory $resultPageFactory)
    {
        $this->_resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }


    public function execute()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $post = $this->getRequest()->getPostValue();
        $registry = $objectManager->get('\Magento\Framework\Registry'); 
         
        // Sample Parameters
        /*Array
        (
            [SessionId] => 190
            [SST] => 613e949a-9da6-4d9f-8d3e-4081a8e565b5
        )*/       
        
        
        $sst = $post['SST'];
        $sessionid =$post['SessionId'];
        $errormessage = $this->queryTransaction($sst, $sessionid);
        $registry->register('errormessage', $errormessage);

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
        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $checkoutSession = $objectManager->get('\Magento\Checkout\Model\Session');
        
        $orderCollectionFactory = $objectManager->get('\Magento\Sales\Model\ResourceModel\Order\CollectionFactory');
        $orders =  $orderCollectionFactory->create()->addFieldToSelect(
                '*'
            )->setOrder(
                'created_at',
                'desc'
            )->setPageSize(1)
             ->setCurPage(1);
            
        $orderId = $orders->getFirstItem()->getIncrementId();        
        $checkoutSession->setLastRealOrderId($orderId)->setLastSuccessQuoteId($post['SessionId'])->setLastQuoteId($post['SessionId'])->setLastOrderId($post['SessionId']);
                    
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $base_url = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        $registry->register('base_url', $base_url);

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
    
    
    protected function queryTransaction($sst, $sessionid)
    {
        
        $this->_getAPICred();
        
        $headers = array(
            "Content-type: application/x-www-form-urlencoded",
            "Cache-Control: no-cache",
        );

        $postfield = 'UserName='.$this->_api_username.'&';
        $postfield .= 'Password='.$this->_api_password.'&';
        $postfield .= 'SST='.$sst.'&';
        $postfield .= 'SessionId='.$sessionid.'&';
        $postfield .= 'Query=true';

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->_submiturl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfield);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $responsecurl =  curl_exec($ch);
        curl_close($ch);
 
 
        /* Return the follwing
        <html><body><form><input type="hidden" name="SessionId" value="190" /><input type="hidden" name="SST" value="613e949a-9da6-4d9f-8d3e-4081a8e565b5" /><input type="hidden" name="SessionKey" value="123" /><input type="hidden" name="CustRef" value="190" /><input type="hidden" name="CustNumber" value="2" /><input type="hidden" name="Amount" value="10900" /><input type="hidden" name="Surcharge" value="" /><input type="hidden" name="AmountIncludingSurcharge" value="10900" /><input type="hidden" name="Result" value="1" /><input type="hidden" name="DeclinedCode" value="" /><input type="hidden" name="DeclinedMessage" value="" /><input type="hidden" name="Receipt" value="92699209" /><input type="hidden" name="TxDateTime" value="2018-02-07+21%3a59%3a56" /><input type="hidden" name="SettlementDate" value="2018-02-08" /><input type="hidden" name="MaskedCard" value="411111******1111" /><input type="hidden" name="CardHolderName" value="dsaf dsafasdf" /><input type="hidden" name="ExpiryDate" value="11/21" /><input type="hidden" name="CardType" value="Visa" /></form></body></html>test<pre>
        */ 
 
        $string = $responsecurl;
        
        $result = preg_match('/<input type="hidden" name="DeclinedMessage" value="(.*?)"/', $string, $matches);
        $resultsecond = preg_match('/<input type="hidden" name="DeclinedCode" value="(.*?)"/', $string, $matchesarray);
        $resultresponse = preg_match('/<input type="hidden" name="Result" value="(.*?)"/', $string, $resultresponsearray);
        if ($resultresponsearray[1] == "0") {
            return $matchesarray[1] . " " .str_replace("+", " ", $matches[1]);
        } else {
            return "";  
        }
    }
    
    protected function _getAPICred()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $configdata = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
        $encryp = $objectManager->get('\Magento\Framework\Encryption\EncryptorInterface');

        if ($configdata->getValue('payment/bambora_apaccheckout/mode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == "sandbox") {
            $this->_api_username = $encryp->decrypt($configdata->getValue('payment/bambora_apaccheckout/sandbox_api_username', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)); 
            $this->_api_password = $encryp->decrypt($configdata->getValue('payment/bambora_apaccheckout/sandbox_api_password', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)); 
            $this->_submiturl = \Bambora\Apacapi\Model\Constant::SANDBOX_INTEGRATED_CHECKOUT_URL;
        } else {
            $this->_api_username = $encryp->decrypt($configdata->getValue('payment/bambora_apaccheckout/live_api_username', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
            $this->_api_password = $encryp->decrypt($configdata->getValue('payment/bambora_apaccheckout/live_api_password', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
            $this->_submiturl = \Bambora\Apacapi\Model\Constant::LIVE_INTEGRATED_CHECKOUT_URL;
        }
    }    
   
}