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

namespace Bambora\Apaccheckout\Model;


class Payment extends \Magento\Payment\Model\Method\Cc
{
    const CODE = 'bambora_apaccheckout';

    protected $_code = self::CODE;

    protected $_isGateway                   = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canVoid                     = false;
    protected $_canDelete                   = true;
            
    protected $_connectionType;
    protected $_isBackendOrder;

    protected $_api_username = '';
    protected $_api_password = '';    

    
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        array $data = array()
    ) {
        
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            null,
            null,
            $data
        );

    }

    public function validate()
    {
        return $this;
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {        
        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $configdata = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');

        if($configdata->getValue('payment/bambora_apaccheckout/payment_action', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == "authorize_capture"){
            return true;        
        }
        
        $orderId = $payment->getOrder()->getId(); 
        
        $receiptNumber = $payment->getLastTransId();
        
        $response = $this->_SingleCaptureRequest($receiptNumber, $amount);
        
        $payment->setTransactionId((string) $response->Receipt)->setIsTransactionClosed(0);  
        
        if ($this->getConfigData('debug') == "1"){
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $logger = $objectManager->get('\Psr\Log\LoggerInterface');

            $timestamp = (isset($response->Timestamp)) ? $response->Timestamp : '';      
            $declinedCode = (isset($response->DeclinedCode)) ? $response->DeclinedCode : '';
            $declinedMsg = (isset($response->DeclinedMessage)) ? $response->DeclinedMessage : '';
            $currencyCode = $payment->getOrder()->getOrderCurrencyCode();
            $orderId = $payment->getOrder()->getIncrementId(); 
            $receiptNo = (isset($response->Receipt)) ? $response->Receipt : '';
            $paymentApiMode = $this->getConfigData('mode');
            $AccountNumber = $this->getConfigData('account_number');            
            $paymentaction = $this->getConfigData('payment_action');
            
            $message  = "Timestamp: " . $timestamp . "\n";                        
            $message .= " Declined Code: " . $declinedCode  . "\n";  
            $message .= " Declined Message: " . $declinedMsg  . "\n";
            $message .= " Currency: " . $currencyCode  . "\n";
            $message .= " Payment Action: " . $paymentaction  . "\n"; 
            $message .= " Amount: " . $amount  . "\n";
            $message .= " Receipt #: " . $receiptNo  . "\n";
            $message .= " Magento Order #: " . $orderId  . "\n";
            $message .= " Account Number: " . $AccountNumber  . "\n";            
            $message .= " Payment API Mode: " . $paymentApiMode  . "\n";

            $logger->debug($message);
        }
        
        return $this;
    }
    

    protected function _doAPI($request)
    {        
        
        if ($this->getConfigData('mode') == "sandbox"){
            $url = \Bambora\Apacapi\Model\Constant::SANDBOX_ENDPOINT;
        } else {
            $url = \Bambora\Apacapi\Model\Constant::LIVE_ENDPOINT;
        }
                
        $soaprequest  = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dts="http://www.ippayments.com.au/interface/api/dts">';
        $soaprequest .= '<soapenv:Header/>';
        $soaprequest .= '<soapenv:Body>';
        $soaprequest .= $request;
        $soaprequest .= '</soapenv:Body>';
        $soaprequest .= '</soapenv:Envelope>';
        
        $headers = array(
            "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "Content-length: ".strlen($soaprequest),
        );

        
     
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $soaprequest);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,1);    


        $responsecurl =  curl_exec($ch);
        $rt = str_replace("<soap:Body>", "", $responsecurl);
        $rx = str_replace("</soap:Body>", "", $rt); 
        $xml = simplexml_load_string($rx);
        curl_close($ch);        
        return $xml;
    }

    /**
     * Submit single capture request (i.e. complete a preauthorisation) to gateway 
     * and return response
     */     
    protected function _SingleCaptureRequest($receiptNumber, $amount)
    {
        $this->_getAPICred();
        
        $amountcents = $amount * 100;
        $username = $this->_api_username;
        $password = $this->_api_password;
        
        $soaprequest  = '<dts:SubmitSingleCapture>';
        $soaprequest .= '<dts:trnXML>';
        $soaprequest .= '<![CDATA[';
        $soaprequest .= '<Capture>';
        $soaprequest .= '<Receipt>' . $receiptNumber . '</Receipt>';
        $soaprequest .= '<Amount>' . $amountcents . '</Amount>';
        $soaprequest .= '<Security>';
        $soaprequest .= '<UserName>' . $username . '</UserName>';
        $soaprequest .= '<Password>' . $password . '</Password>';
        $soaprequest .= '</Security>';
        $soaprequest .= '</Capture>';
        $soaprequest .= ']]>';    
        $soaprequest .= '</dts:trnXML>';
        $soaprequest .= '</dts:SubmitSingleCapture>';
        
        $xml = $this->_doAPI($soaprequest);
        
        $xmlarray = (array) $xml->SubmitSingleCaptureResponse->SubmitSingleCaptureResult;
        $response = isset($xmlarray[0]) ? simplexml_load_string($xmlarray[0]) : null; 

        return $response; 
    }
    

    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        
        $this->_getAPICred();
        
        $amountcents = $amount * 100;
        $username = $this->_api_username;
        $password = $this->_api_password;
        
        $receiptNumber = $payment->getLastTransId();

        $soaprequest  = ' <dts:SubmitSingleRefund>';
        $soaprequest .= '     <!--Optional:-->';
        $soaprequest .= '     <dts:trnXML>';
        $soaprequest .= '     <![CDATA[';
        $soaprequest .= '     <Refund>';
        $soaprequest .= '         <Receipt>'.$receiptNumber .'</Receipt>';
        $soaprequest .= '         <Amount>' . $amountcents . '</Amount>';
        $soaprequest .= '         <Security>';
        $soaprequest .= '             <UserName>'. $username . '</UserName>';
        $soaprequest .= '             <Password>'. $password . '</Password>';
        $soaprequest .= '          </Security> ';
        $soaprequest .= '     </Refund>';
        $soaprequest .= '     ]]>';
        $soaprequest .= '     </dts:trnXML>';
        $soaprequest .= ' </dts:SubmitSingleRefund>';
                   
        $xml = $this->_doAPI($soaprequest);
        
        $xmlarray = (array) $xml->SubmitSingleRefundResponse->SubmitSingleRefundResult;
        $response = isset($xmlarray[0]) ? simplexml_load_string($xmlarray[0]) : null; 
        
        $payment->setTransactionId((string) $response->Receipt)->setIsTransactionClosed(0); 
        
        if ($this->getConfigData('debug') == "1"){
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $logger = $objectManager->get('\Psr\Log\LoggerInterface');
 
            $message  = "ResponseCode: " . $response->ResponseCode . "\n";
            $message .= "Timestamp: " . $response->Timestamp  . "\n";
            $message .= "Receipt: " . $response->Receipt  . "\n";  
            $message .= "SettlementDate: " . $response->SettlementDate  . "\n";
            $message .= "DeclinedCode" . $response->DeclinedCode  . "\n";
            $message .= "DeclinedMessage: " . $response->DeclinedMessage  . "\n"; 
 
            $logger->debug($message);
        }
        
        return $this;
        
    }


    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        
        $this->_getAPICred();

        $username = $this->_api_username;
        $password = $this->_api_password;
        
        $receiptNumber = $payment->getLastTransId();
        
        
        $amountcents = $payment->getOrder()->getGrandTotal();

        $response = $this->_SingleCaptureRequest($receiptNumber,$amountcents);
        $payment->setTransactionId((string) $response->Receipt)->setIsTransactionClosed(0); 

        $soaprequest  = ' <dts:SubmitSingleVoid>';
        $soaprequest .= '     <!--Optional:-->';
        $soaprequest .= '     <dts:trnXML>';
        $soaprequest .= '     <![CDATA[';
        $soaprequest .= '     <Void>';
        $soaprequest .= '         <Receipt>'.$response->Receipt  .'</Receipt>';
        $soaprequest .= '         <Amount>' . $amountcents . '</Amount>';
        $soaprequest .= '         <Security>';
        $soaprequest .= '             <UserName>'. $username . '</UserName>';
        $soaprequest .= '             <Password>'. $password . '</Password>';
        $soaprequest .= '          </Security> ';
        $soaprequest .= '     </Void>';
        $soaprequest .= '     ]]>';
        $soaprequest .= '     </dts:trnXML>';
        $soaprequest .= ' </dts:SubmitSingleVoid>';

                   
        $xml = $this->_doAPI($soaprequest);
        
        $xmlarray = (array) $xml->SubmitSingleVoidResponse->SubmitSingleVoidResult;
        $response = isset($xmlarray[0]) ? simplexml_load_string($xmlarray[0]) : null; 

        if ($this->getConfigData('debug') == "1"){
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $logger = $objectManager->get('\Psr\Log\LoggerInterface');
 
            $message  = "ResponseCode: " . $response->ResponseCode . "\n";
            $message .= "Timestamp: " . $response->Timestamp . "\n";
            $message .= "Receipt: " . $response->Receipt . "\n";  
            $message .= "SettlementDate: " . $response->SettlementDate . "\n";
            $message .= "DeclinedCode" . $response->DeclinedCode . "\n";
            $message .= "DeclinedMessage: " . $response->DeclinedMessage . "\n"; 
 
            $logger->debug($message);
        }
        
        return $this;
 
    }    

    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        return $this->void($payment);
    }

    protected function _getAPICred()
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $encryp = $objectManager->get('\Magento\Framework\Encryption\EncryptorInterface');

        if ($this->getConfigData('mode') == "sandbox"){
            $this->_api_username = $encryp->decrypt($this->getConfigData('sandbox_api_username'));
            $this->_api_password = $encryp->decrypt($this->getConfigData('sandbox_api_password'));
        } else {
            $this->_api_username = $encryp->decrypt($this->getConfigData('live_api_username'));
            $this->_api_password = $encryp->decrypt($this->getConfigData('live_api_password'));
        }
    }
    
}