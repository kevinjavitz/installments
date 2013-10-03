<?php

class Itweb_Installments_Model_Mysql4_Calculation_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {
        $this->_init("installments/calculation");
    }
}