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
 
class Index extends \Magento\Framework\App\Action\Action
{
    protected $_pageFactory;
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
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $configdata = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
        $request= $objectManager->get('\Magento\Framework\App\Request\Http');
        $key_form = $objectManager->get('Magento\Framework\Data\Form\FormKey');

        $base_url = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

        $this->_getAPICred();
        

        if($configdata->getValue('payment/bambora_apaccheckout/payment_action', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == "authorize_capture"){
            $transactiontype = \Bambora\Apacapi\Model\Constant::CHECKOUT_V1_PURCHASE;
        }
        
        if($configdata->getValue('payment/bambora_apaccheckout/payment_action', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == "authorize"){
            $transactiontype = \Bambora\Apacapi\Model\Constant::CHECKOUT_V1_PREAUTH; 
        }
        
            
        $accountnumber = $configdata->getValue('payment/bambora_apaccheckout/account_number', \Magento\Store\Model\ScopeInterface::SCOPE_STORE); 

        $sessionkey = $key_form->getFormKey();
  
        $cart = $objectManager->get('\Magento\Checkout\Model\Cart'); 
        $grandTotal = $cart->getQuote()->getGrandTotal();
 
        $headers = array(
            "Content-type: application/x-www-form-urlencoded",
            "Cache-Control: no-cache",
        );
        
        $amount = $grandTotal * 100;
        $serverurl = base64_encode($base_url . 'bamboraintegrated/index/server');
        $userurl = base64_encode($base_url . 'bamboraintegrated/index/user');
        
        
        $quoteFactory = $objectManager->get('\Magento\Quote\Model\QuoteFactory');
        $quote = $quoteFactory->create()->load($cart->getQuote()->getId());
        
        if($customerSession->isLoggedIn()) {
            $custnumber = $customerSession->getCustomer()->getId();
        }else{

            $custnumber = "";
            $customeremail = str_replace(" ", "+", $request->getParam("email"));
            $quote->setCustomerEmail($customeremail);
            $quote->setCustomerIsGuest(1);
            $quote->save();
        }

        
        $postfield  = 'UserName='.$this->_api_username.'&';
        $postfield .= 'password='.$this->_api_password.'&';
        $postfield .= 'CustRef='.$cart->getQuote()->getId().'&';    //  !!!! Need to get Magento order #
        $postfield .= 'CustNumber='.$custnumber.'&';   //  !!!! Need to get Magento customer ID or 'guest' for guests
        $postfield .= 'Amount='.$amount.'&';
        $postfield .= 'SessionId=' . $cart->getQuote()->getId() . '&';
        $postfield .= 'SessionKey='.$sessionkey.'&';   //  !!!! Need to get Magento session key
        $postfield .= 'DL='.$transactiontype.'&';
        $postfield .= 'ServerURL='.$serverurl.'&';
        $postfield .= 'UserURL='.$userurl.'&';
        
        if ($accountnumber != "") {
            $postfield .= 'AccountNumber=' . $accountnumber . '&'; 
        }
        
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->_submiturl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfield);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $responsecurl =  curl_exec($ch);
        curl_close($ch);

          
        $registry = $objectManager->get('\Magento\Framework\Registry'); 
         
        $assetRepo = $objectManager->get('\Magento\Framework\View\Asset\Repository'); 
        $registry->register('sessionid', $cart->getQuote()->getId());
        $registry->register('submiturl', $this->_submiturl);
        $registry->register('sst',$responsecurl);
        
        //echo $assetRepo->getUrl("Bambora_Apaccheckout::images/spinner.jpg");
        
        

        /*$html  = '<html>';
        $html .= '<body style="width: 400px; min-width: 320px; height: 704px; margin: 35px auto 0px; display: table-row; background-size: 25%; text-align: center; box-shadow: rgb(0, 0, 0) 0px 0px 70px 0px;">';
        $html .= '<form id="integratedcheckoufrm" action="'.$this->_submiturl.'" >';
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

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $configdata = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
        $encryp = $objectManager->get('\Magento\Framework\Encryption\EncryptorInterface');

        if ($configdata->getValue('payment/bambora_apaccheckout/mode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == "sandbox"){
            
            $this->_api_username = $encryp->decrypt($configdata->getValue('payment/bambora_apaccheckout/sandbox_api_username', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)); 
            $this->_api_password = $encryp->decrypt($configdata->getValue('payment/bambora_apaccheckout/sandbox_api_password', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)); 
            $this->_submiturl = \Bambora\Apacapi\Model\Constant::SANDBOX_INTEGRATED_CHECKOUT_URL;

        } else {
            
            $this->_api_username = $encryp->decrypt($configdata->getValue('payment/bambora_apaccheckout/live_api_username', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
            $this->_api_password = $encryp->decrypt($configdata->getValue('payment/bambora_apaccheckout/live_api_password', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
            $this->_submiturl = \Reign\Apacapi\Model\Constant::LIVE_INTEGRATED_CHECKOUT_URL;
        }
    }
    
}