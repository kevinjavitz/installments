<?php
class Itweb_Installments_Block_Adminhtml_Sales_Order_Create_Installments extends Mage_Adminhtml_Block_Sales_Order_Create_Abstract {
	
	public function __construct()
    {
        parent::__construct();
        $this->setId('sales_order_create_installments');
    }

    public function getHeaderText()
    {
        return Mage::helper('installments')->__('Payment Processing Preference');
    }

    public function getHeaderCssClass()
    {
        return 'head-installments';
    }
	
}