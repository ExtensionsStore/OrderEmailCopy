<?php

/**
 * Observer
 *
 * @category   Aydus
 * @package    Aydus_OrderEmailCopy
 * @author     Aydus <davidt@aydus.com>
 */

class Aydus_OrderEmailCopy_Model_Observer 
{    
    /**
     * @see checkout_type_onepage_save_order_after
     * @param Varien_Event_Observer $observer
     */
    public function copyNewOrderEmail($observer)
    {
        $order = $observer->getOrder();
        $store = Mage::app()->getStore();
        $storeId = $store->getId();
        
        $copyToEmails = Mage::getStoreConfig($order::XML_PATH_EMAIL_COPY_TO, $storeId);
        $copyTo = explode(',', $copyToEmails);
        $copyMethod = Mage::getStoreConfig($order::XML_PATH_EMAIL_COPY_METHOD, $storeId);
        
        if ($copyTo && $copyMethod == 'copy') {
            
            //disable copy to
            $store->setConfig($order::XML_PATH_EMAIL_COPY_TO, null);
            
            // Start store emulation process
            $appEmulation = Mage::getSingleton('core/app_emulation');
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
            
            try {
                // Retrieve specified view block from appropriate design package (depends on emulated store)
                $paymentBlock = Mage::helper('payment')->getInfoBlock($order->getPayment())
                ->setIsSecureMode(true);
                $paymentBlock->getMethod()->setStore($storeId);
                $paymentBlockHtml = $paymentBlock->toHtml();
            } catch (Exception $exception) {
                // Stop store emulation process
                $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
                throw $exception;
            }
            
            // Stop store emulation process
            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
            
            // Retrieve corresponding email template id and customer name
            $templateId = Mage::getStoreConfig('sales_email/order/copy_template', $storeId);
            
            $mailer = Mage::getModel('core/email_template_mailer');
            
            // Email copies are sent as separated emails if their copy method is 'copy'
            foreach ($copyTo as $email) {
                $emailInfo = Mage::getModel('core/email_info');
                $emailInfo->addTo($email);
                $mailer->addEmailInfo($emailInfo);
            }
            
            // Set all required params and send emails
            $mailer->setSender(Mage::getStoreConfig($order::XML_PATH_EMAIL_IDENTITY, $storeId));
            $mailer->setStoreId($storeId);
            $mailer->setTemplateId($templateId);
            
            $templateParamsObj = new Varien_Object();
            $templateParamsObj->setData(array(
                    'order'        => $order,
                    'billing'      => $order->getBillingAddress(),
                    'payment_html' => $paymentBlockHtml
            ));
            
            //additional template params
            Mage::dispatchEvent('aydus_orderemailcopy_copyneworderemail', array('template_params' => $templateParamsObj));
            
            $templateParams = $templateParamsObj->getData();
            $mailer->setTemplateParams($templateParams);
            
            /** @var $emailQueue Mage_Core_Model_Email_Queue */
            $emailQueue = Mage::getModel('core/email_queue');
            $emailQueue->setEntityId($order->getId())
            ->setEntityType($order::ENTITY)
            ->setEventType($order::EMAIL_EVENT_NAME_NEW_ORDER);
            
            $mailer->setQueue($emailQueue)->send();

        }
        
        return $observer;
        
    }
    
    /**
     * Observer for this listener
     * 
     * @see aydus_orderemailcopy_copyneworderemail
     * @param Varien_Event_Observer $observer
     */
    public function copyNewOrderEmailObserver($observer)
    {
        $templateParamsObj = $observer->getTemplateParams();
        $templateParamsObj->setTest('Hello');
        
        return $observer;
    }

}