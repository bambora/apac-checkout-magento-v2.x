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
 
class Server extends \Magento\Framework\App\Action\Action
{
    
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory
    )
    {
        $this->_pageFactory = $pageFactory;
        return parent::__construct($context);
    }

    public function execute()
    {

        $post = $this->getRequest()->getPostValue();
        $declinecode = $post['DeclinedCode'];
        
        if ($declinecode != "") {
            return false;
        } 
        
        // Sample Parameters 
        /*SessionId:::44
        SST:::4b69af7a-485f-4e0c-82ee-52b97fcab44d
        SessionKey:::123
        CustRef:::44
        CustNumber:::123
        Amount:::20200
        Surcharge:::
        AmountIncludingSurcharge:::20200
        Result:::1
        DeclinedCode:::
        DeclinedMessage:::
        Receipt:::92512084
        TxDateTime:::2018-02-01 01:59:35
        SettlementDate:::2018-02-01
        MaskedCard:::411111******1111
        CardHolderName:::sad ads
        ExpiryDate:::11/21
        CardType:::Visa*/
        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $quoteFactory = $objectManager->get('\Magento\Quote\Model\QuoteFactory');
        $quote = $quoteFactory->create()->load($post["SessionId"]);
         
        $quoteManagement = $objectManager->get('\Magento\Quote\Model\QuoteManagement');
         
        $configdata = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
        $transaction_type = "";
        
        if($configdata->getValue('payment/bambora_apaccheckout/payment_action', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == "authorize_capture"){
            $transaction_type = \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE;
        }
        
        if($configdata->getValue('payment/bambora_apaccheckout/payment_action', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == "authorize"){
            $transaction_type = \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH;
        }
                   
        $billingAddressInfo = $quote->getBillingAddress();
        $billingAddressData = $billingAddressInfo->getData();
        
        $quote->setPaymentMethod('bambora_apaccheckout');
        $quote->save(); //Now Save quote and your quote is ready
        
        $quote->getPayment()->importData(['method' => 'bambora_apaccheckout']);
        $quote->collectTotals()->save(); //Now Save quote and your quote is ready
        
        
        $cartRepositoryInterface = $objectManager->get('\Magento\Quote\Api\CartRepositoryInterface');
        $cartManagementInterface = $objectManager->get('\Magento\Quote\Api\CartManagementInterface');
        $order = $objectManager->get('\Magento\Sales\Model\Order');
        
        // Create Order From Quote
        $quote = $cartRepositoryInterface->get($quote->getId());
        $orderId = $cartManagementInterface->placeOrder($quote->getId());
        $order = $order->load($orderId);

        $transactionbuilder = $objectManager->get('\Magento\Sales\Model\Order\Payment\Transaction\Builder');
        
        $receipt = $post["Receipt"];
        
        $payment = $order->getPayment();
        $payment->setTransactionId($receipt);
        $payment->setIsTransactionClosed(0);

        
        $formatedPrice = $order->getBaseCurrency()->formatTxt($order->getGrandTotal());

        $message = __('The authorized amount is %1.', $formatedPrice);
        
        $transaction = $transactionbuilder->setPayment($payment)
        ->setOrder($order)
        ->setTransactionId($receipt)
        ->setFailSafe(true)
        ->build($transaction_type);
        $transaction->save();
        
        $payment->addTransactionCommentsToOrder($transaction,$message);
        $payment->setParentTransactionId(null);
        $payment->save();
        $order->save();

        if($configdata->getValue('payment/bambora_apaccheckout/payment_action', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == "authorize_capture") {
        
            $invoiceService = $objectManager->get('\Magento\Sales\Model\Service\InvoiceService');
            $invoiceSender = $objectManager->get('\Magento\Sales\Model\Order\Email\Sender\InvoiceSender');
            
            foreach ($order->getInvoiceCollection() as $invoice)
            {
                $invoice->setTransactionId($post['Receipt']);
                $invoice->save();
                $invoiceSender->send($invoice);
                
            }
        
        }
        
        if($configdata->getValue('payment/bambora_apaccheckout/debug', \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == "1") {
            $logger = $objectManager->get('\Psr\Log\LoggerInterface');

        
            $timestamp = (isset($post['TxDateTime'])) ? $post['TxDateTime'] : '';      
            $declinedCode = (isset($post['DeclinedCode'])) ? $post['DeclinedCode'] : '';
            $declinedMsg = (isset($post['DeclinedMessage'])) ? $post['DeclinedMessage'] : '';
            $currencyCode = $order->getOrderCurrencyCode();
            $orderId = $order->getIncrementId(); 
            $receiptNo = (isset($post['Receipt'])) ? $post['Receipt'] : '';
            $cardNo = $post['MaskedCard']; // last 4 digits only
            $cardExp = $post['ExpiryDate'];
            $cardholdername = $post['CardHolderName'];
            $paymentApiMode = $configdata->getValue('payment/bambora_apaccheckout/mode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $AccountNumber = $configdata->getValue('payment/bambora_apaccheckout/account_number', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);            
            $amount = $post['Receipt'];
            $paymentaction = $configdata->getValue('payment/bambora_apaccheckout/payment_action', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            
            $message  = "Timestamp: " . $timestamp . "\n";                        
            $message .= " Declined Code: " . $declinedCode  . "\n";  
            $message .= " Declined Message: " . $declinedMsg  . "\n";
            $message .= " Currency: " . $currencyCode  . "\n";
            $message .= " Payment Action: " . $paymentaction  . "\n";  
            $message .= " Amount: " . $amount  . "\n";
            $message .= " Receipt #: " . $receiptNo  . "\n";
            $message .= " Card Number: " . $cardNo  . "\n";
            $message .= " Expiry: " . $cardExp  . "\n";
            $message .= " Card Holder Name: " . $cardholdername  . "\n";
            $message .= " Magento Order #: " . $orderId  . "\n";
            $message .= " Account Number: " . $AccountNumber  . "\n";
            $message .= " Payment API Mode: " . $paymentApiMode  . "\n";          
 
            $logger->debug($message);            
        }
        
        return false;
    }
}