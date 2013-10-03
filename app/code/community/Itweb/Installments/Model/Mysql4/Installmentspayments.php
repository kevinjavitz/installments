<?php
class Itweb_Installments_Model_Mysql4_Installmentspayments extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init("installments/installmentspayments", "installment_payment_id");
    }
}