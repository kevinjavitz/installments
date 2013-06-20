<?php
class Itweb_Installments_Model_Mysql4_Installments extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init("installments/installments", "installment_id");
    }
}