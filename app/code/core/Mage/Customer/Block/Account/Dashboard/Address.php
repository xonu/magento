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
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2004-2007 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer dashboard addresses section
 *
 * @category   Mage
 * @package    Mage_Customer
 */

class Mage_Customer_Block_Account_Dashboard_Address extends Mage_Core_Block_Template
{
	public function getCustomer()
	{
		return Mage::getSingleton('customer/session')->getCustomer();
	}

    public function getPrimaryShippingAddressHtml()
    {
        $address = $this->getCustomer()->getPrimaryShippingAddress();

        if( $address instanceof Varien_Object ) {
            return $address->format('html');
        } else {
            return Mage::helper('customer')->__('You have not set a primary shipping address.');
        }
    }

    public function getPrimaryBillingAddressHtml()
    {
        $address = $this->getCustomer()->getPrimaryBillingAddress();

        if( $address instanceof Varien_Object ) {
        	return $address->format('html');
        } else {
            return Mage::helper('customer')->__('You have not set a primary billing address.');
        }
    }

    public function getPrimaryShippingAddressEditUrl()
    {
    	return Mage::getUrl('customer/address/edit', array('id'=>$this->getCustomer()->getDefaultShipping()));
    }

    public function getPrimaryBillingAddressEditUrl()
    {
    	return Mage::getUrl('customer/address/edit', array('id'=>$this->getCustomer()->getDefaultBilling()));
    }

    public function getAddressBookUrl()
    {
        return $this->getUrl('customer/address/');
    }
}
