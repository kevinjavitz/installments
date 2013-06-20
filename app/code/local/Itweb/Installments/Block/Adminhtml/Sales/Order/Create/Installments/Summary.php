<?php
class Itweb_Installments_Block_Adminhtml_Sales_Order_Create_Installments_Summary extends Mage_Core_Block_Template {
	
	public function getQuote()
    {
        return Mage::getSingleton('adminhtml/session_quote')->getQuote();
    }
	
}