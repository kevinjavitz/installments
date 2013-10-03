<?php
class Itweb_Installments_Model_Mysql4_Installmentspaymentsinvoices extends Mage_Core_Model_Mysql4_Abstract
{

    protected function _construct()
    {
        $this->_init("installments/installmentspaymentsinvoices", "installment_payment_invoice_id");
    }
}