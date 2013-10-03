<?php
class Itweb_Installments_Model_Mysql4_Installmentspaymentsinvoices_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{

    public function _construct()
    {
        $this->_init("installments/installmentspaymentsinvoices");
    }

    public function _beforeLoad()
    {
        $this->getSelect()->order('installment_payment_invoice_id asc');
    }
}
