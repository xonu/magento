<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   Mage
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2004-2007 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Express Checkout Controller
 *
 */
class Mage_Paypal_ExpressController extends Mage_Core_Controller_Front_Action
{
    protected function _expireAjax()
    {
        if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
            exit;
        }
    }

    /**
     * Get singleton with paypal express order transaction information
     *
     * @return Mage_Paypal_Model_Express
     */
    public function getExpress()
    {
        return Mage::getSingleton('paypal/express');
    }

    /**
     * When there's an API error
     */
    public function errorAction()
    {
        $this->_redirect('checkout/cart');
    }

    public function cancelAction()
    {
        $this->_redirect('checkout/cart');
    }

    /**
     * When a customer clicks Paypal button on shopping cart
     */
    public function shortcutAction()
    {
        $this->getExpress()->shortcutSetExpressCheckout();
        $this->getResponse()->setRedirect($this->getExpress()->getRedirectUrl());
    }

    /**
     * When a customer chooses Paypal on Checkout/Payment page
     *
     */
    public function markAction()
    {
        $this->getExpress()->markSetExpressCheckout();
        $this->getResponse()->setRedirect($this->getExpress()->getRedirectUrl());
    }

    public function editAction()
    {
        $this->getResponse()->setRedirect($this->getExpress()->getApi()->getPaypalUrl());
    }

    /**
     * Return here from Paypal before final payment (continue)
     *
     */
    public function returnAction()
    {
        $this->getExpress()->returnFromPaypal();
        $this->getResponse()->setRedirect($this->getExpress()->getRedirectUrl());
    }

    /**
     * Return here from Paypal after final payment (commit) or after on-site order review
     *
     */
    public function reviewAction()
    {
        $payment = Mage::getSingleton('checkout/session')->getQuote()->getPayment();
        if ($payment && $payment->getPaypalPayerId()) {
            $this->loadLayout();
            $this->_initLayoutMessages('paypal/session');
            $this->renderLayout();
        } else {
            $this->_redirect('checkout/cart');
        }
    }

    /**
     * Get PayPal Onepage checkout model
     *
     * @return Mage_Paypal_Model_Express_Onepage
     */
    public function getReview()
    {
        return Mage::getSingleton('paypal/express_review');
    }

    public function saveShippingMethodAction()
    {
        if ($this->getRequest()->getParam('ajax')) {
            $this->_expireAjax();
        }

        if (!$this->getRequest()->isPost()) {
            return;
        }

        $data = $this->getRequest()->getParam('shipping_method', '');
        $result = $this->getReview()->saveShippingMethod($data);

        if ($this->getRequest()->getParam('ajax')) {
            $this->loadLayout('paypal_express_review_details');
            $this->getResponse()->setBody($this->getLayout()->getBlock('root')->toHtml());
        } else {
            $this->_redirect('paypal/express/review');
        }
    }

    public function saveOrderAction()
    {
        /**
         * 1- create order
         * 2- place order (call doexpress checkout)
         * 3- save order
         */
        $error_message = '';

        try {
            $address = $this->getReview()->getQuote()->getShippingAddress();
            if (!$address->getShippingMethod()) {
                if ($shippingMethod = $this->getRequest()->getParam('shipping_method')) {
                    $this->getReview()->saveShippingMethod($shippingMethod);
                } else {
                    Mage::getSingleton('paypal/session')->addError(Mage::helper('paypal')->__('Please select a valid shipping method'));
                    $this->_redirect('paypal/express/review');
                    return;
                }
            }
            $billing = $this->getReview()->getQuote()->getBillingAddress();
            $shipping = $this->getReview()->getQuote()->getShippingAddress();

            $convertQuote = Mage::getModel('sales/convert_quote');
            /* @var $convertQuote Mage_Sales_Model_Convert_Quote */
            $order = Mage::getModel('sales/order');
            /* @var $order Mage_Sales_Model_Order */

            $order = $convertQuote->addressToOrder($shipping);
            $order->setBillingAddress($convertQuote->addressToOrderAddress($billing));
            $order->setShippingAddress($convertQuote->addressToOrderAddress($shipping));
            $order->setPayment($convertQuote->paymentToOrderPayment($this->getReview()->getQuote()->getPayment()));

            foreach ($this->getReview()->getQuote()->getAllItems() as $item) {
                $order->addItem($convertQuote->itemToOrderItem($item));
            }

            /**
             * We can use configuration data for declare new order status
             */
            Mage::dispatchEvent('checkout_type_onepage_save_order', array('order'=>$order, 'quote'=>$this->getReview()->getQuote()));

            //customer checkout from shopping cart page
            if (!$order->getCustomerEmail()) {
                $order->setCustomerEmail($shipping->getEmail());
            }

            $order->place();

        } catch (Mage_Core_Exception $e){
            $error_message = $e->getMessage();
        } catch (Exception $e){
            if (isset($order)) {
                $error_message = $order->getErrors();
            } else {
                $error_message = $e->getMessage();
            }
        }

        if ($error_message) {
            Mage::getSingleton('paypal/session')->addError($e->getMessage());
            $this->_redirect('paypal/express/review');
            return;
        }

        try {
            $this->getExpress()->placeOrder($order->getPayment());
        } catch (Exception $e) {
            Mage::getSingleton('paypal/session')->addError($e->getMessage());
            $this->_redirect('paypal/express/review');
            return;
        }


        $order->save();

        $this->getReview()->getQuote()->setIsActive(false);
        $this->getReview()->getQuote()->save();

        $orderId = $order->getIncrementId();
        $this->getReview()->getCheckout()->setLastQuoteId($this->getReview()->getQuote()->getId());
        $this->getReview()->getCheckout()->setLastOrderId($order->getId());
        $this->getReview()->getCheckout()->setLastRealOrderId($order->getIncrementId());

        $order->sendNewOrderEmail();

        $this->_redirect('checkout/onepage/success');
    }
}