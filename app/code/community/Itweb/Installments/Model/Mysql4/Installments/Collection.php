<?php
class Itweb_Installments_Model_Mysql4_Installments_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{

    private $addOrderCustomer = false;

    private $addOrderIncrement = false;

    public function _construct()
    {
        $this->_init("installments/installments");
    }

    public function addCustomerName()
    {
        $this->addOrderCustomer = true;
        return $this;
    }

    public function addOrderIncrementId()
    {
        $this->addOrderIncrement = true;
        return $this;
    }

    public function _beforeLoad()
    {
        $addOrderFields = array();
        if ($this->addOrderCustomer) {
            $addOrderFields[] = 'customer_firstname';
            $addOrderFields[] = 'customer_lastname';
        }

        if ($this->addOrderIncrement) {
            $addOrderFields[] = 'increment_id';
        }

        if (!empty($addOrderFields)) {
            $this->getSelect()->joinLeft(
                array('order_table' => $this->getTable('sales/order')),
                'main_table.order_id = order_table.entity_id',
                $addOrderFields
            );
        }
        return parent::_beforeLoad();
    }
}
	 