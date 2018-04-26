<?php

namespace Bambora\Apaccheckout\Observer;

class Changstatus implements \Magento\Framework\Event\ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $configdata = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
        
        $orderPayment = $observer->getEvent()->getPayment();
        $method = $orderPayment->getMethodInstance();
        $methodCode = $method->getCode();
        
        if($methodCode == "bambora_apaccheckout"){
            $order_status = $configdata->getValue('payment/bambora_apaccheckout/order_status');
            $order = $orderPayment->getOrder();
            $order->setState("processing")
            ->setStatus($order_status);
            $order->save();
        }
        
        return $this;
    }
}