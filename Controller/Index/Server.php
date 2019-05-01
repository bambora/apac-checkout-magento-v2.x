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
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

if (!interface_exists('Magento\Framework\App\CsrfAwareActionInterface')) {
    require_once(__DIR__.'/../../Helper/CsrfAwareActionInterface.php');
}

use Magento\Framework\App\CsrfAwareActionInterface as CsrfAwareActionInterface;

class Server extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_pageFactory;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $_cartRepository;

    /**
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    protected $_cartManagement;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var \Magento\Sales\Model\Order\Payment\Transaction\Builder
     */
    protected $_builder;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $_invoiceSender;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * Server constructor.
     * @param Context $context
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param \Magento\Quote\Api\CartManagementInterface $cartManagement
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\Order\Payment\Transaction\Builder $builder
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\Builder $builder,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_pageFactory = $pageFactory;
        $this->_cartRepository = $cartRepository;
        $this->_cartManagement = $cartManagement;
        $this->_orderRepository = $orderRepository;
        $this->_builder = $builder;
        $this->_invoiceSender = $invoiceSender;
        $this->_logger = $logger;
        parent::__construct($context);
    }

    /**
     * @return bool|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $post = $this->getRequest()->getPostValue();

        $declineCode = $post['DeclinedCode'] ?? '';

        if ($declineCode && !empty($declineCode)) {
            $this->_logger->debug(__('Declined. Code # %1', $declineCode));

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

        $quote = $this->_cartRepository->get($post["SessionId"]);

        $transaction_type = "";

        if ($this->_scopeConfig->getValue('payment/bambora_apaccheckout/payment_action',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == "authorize_capture") {
            $transaction_type = \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE;
        }

        if ($this->_scopeConfig->getValue('payment/bambora_apaccheckout/payment_action',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == "authorize") {
            $transaction_type = \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH;
        }

        $billingAddressInfo = $quote->getBillingAddress();
        $billingAddressData = $billingAddressInfo->getData();

        $quote->setPaymentMethod('bambora_apaccheckout');
        $quote->save(); //Now Save quote and your quote is ready

        $quote->getPayment()->importData(['method' => 'bambora_apaccheckout']);
        $quote->collectTotals()->save(); //Now Save quote and your quote is ready

        // Create Order From Quote
        $orderId = $this->_cartManagement->placeOrder($quote->getId());
        $order = $this->_orderRepository->get($orderId);

        $receipt = $post["Receipt"];

        $payment = $order->getPayment();
        $payment->setTransactionId($receipt);
        $payment->setIsTransactionClosed(0);

        $formattedPrice = $order->getBaseCurrency()->formatTxt($order->getGrandTotal());

        $message = __('The authorized amount is %1.', $formattedPrice);

        $transaction = $this->_builder->setPayment($payment)
            ->setOrder($order)
            ->setTransactionId($receipt)
            ->setFailSafe(true)
            ->build($transaction_type);
        $transaction->save();

        $payment->addTransactionCommentsToOrder($transaction, $message);
        $payment->setParentTransactionId(null);
        $payment->save();
        $order->save();

        if ($this->_scopeConfig->getValue('payment/bambora_apaccheckout/payment_action',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == "authorize_capture") {

            //$invoiceService = $objectManager->get('\Magento\Sales\Model\Service\InvoiceService');

            foreach ($order->getInvoiceCollection() as $invoice) {
                $invoice->setTransactionId($post['Receipt']);
                $invoice->save();
                $this->_invoiceSender->send($invoice);
            }
        }

        if ($this->_scopeConfig->getValue('payment/bambora_apaccheckout/debug',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE) == "1") {
            $timestamp = (isset($post['TxDateTime'])) ? $post['TxDateTime'] : '';
            $declinedCode = (isset($post['DeclinedCode'])) ? $post['DeclinedCode'] : '';
            $declinedMsg = (isset($post['DeclinedMessage'])) ? $post['DeclinedMessage'] : '';
            $currencyCode = $order->getOrderCurrencyCode();
            $orderId = $order->getIncrementId();
            $receiptNo = (isset($post['Receipt'])) ? $post['Receipt'] : '';
            $cardNo = $post['MaskedCard']; // last 4 digits only
            $cardExp = $post['ExpiryDate'];
            $cardHolderName = $post['CardHolderName'];
            $paymentApiMode = $this->_scopeConfig->getValue('payment/bambora_apaccheckout/mode',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $accountNumber = $this->_scopeConfig->getValue('payment/bambora_apaccheckout/account_number',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $amount = $post['Receipt'];
            $paymentAction = $this->_scopeConfig->getValue('payment/bambora_apaccheckout/payment_action',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

            $message = "Timestamp: " . $timestamp . "\n";
            $message .= " Declined Code: " . $declinedCode . "\n";
            $message .= " Declined Message: " . $declinedMsg . "\n";
            $message .= " Currency: " . $currencyCode . "\n";
            $message .= " Payment Action: " . $paymentAction . "\n";
            $message .= " Amount: " . $amount . "\n";
            $message .= " Receipt #: " . $receiptNo . "\n";
            $message .= " Card Number: " . $cardNo . "\n";
            $message .= " Expiry: " . $cardExp . "\n";
            $message .= " Card Holder Name: " . $cardHolderName . "\n";
            $message .= " Magento Order #: " . $orderId . "\n";
            $message .= " Account Number: " . $accountNumber . "\n";
            $message .= " Payment API Mode: " . $paymentApiMode . "\n";

            $this->_logger->debug($message);
        }

        return false;
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}