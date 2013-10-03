<?php
class Itweb_Installments_Block_Adminhtml_Sales_Order_Create_Installments_Form extends Mage_Core_Block_Template
{

    public function getCurrentInstallment()
    {
        return $this->getQuote()->getUseInstallments();
    }

    public function getQuote()
    {
        return Mage::getSingleton('adminhtml/session_quote')->getQuote();
    }

}