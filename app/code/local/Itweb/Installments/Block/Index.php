<?php
class Itweb_Installments_Block_Index
	extends Mage_Core_Block_Template
{

	/**
	 * @return Mage_Customer_Model_Customer
	 */
	public function getCustomer()
	{
		return Mage::getSingleton('customer/session')->getCustomer();
	}

	public function getMakePaymentUrl()
	{
		return $this->getUrl('installments/index/makePayment', array('_secure' => true, '_current' => true));
	}

	public function getInstallments()
	{
		return Itweb_Installments_Helper_Data::getCustomerInstallments($this->getCustomer()->getId());
	}
}