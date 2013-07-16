<?php

class Itweb_Installments_Model_Sales_Order extends Mage_Sales_Model_Order {
	
	public function canInvoice()
    {
		if ($this->getGrandTotal() == $this->getTotalPaid()) {
			return false;
		}
        return parent::canInvoice();
    }
}