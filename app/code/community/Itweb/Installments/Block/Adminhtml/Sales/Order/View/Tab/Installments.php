<?php
class Itweb_Installments_Block_Adminhtml_Sales_Order_View_Tab_Installments extends Mage_Adminhtml_Block_Template implements Mage_Adminhtml_Block_Widget_Tab_Interface
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('installments/sales/order/view/tab/installments.phtml');
    }

    public function getMakePaymentUrl()
    {
        return $this->getUrl('installments/adminhtml_installmentsbackend/makePayment', array('_current' => true));
    }

    /**
     * Retrieve order model instance
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return Mage::registry('current_order');
    }

    public function getInstallment()
    {
        return Itweb_Installments_Helper_Data::getOrderInstallment($this->getOrder());
    }

    /**
     * Retrieve tab label
     *
     * @return string
     */
    public function getTabLabel()
    {
        return Mage::helper('sales')->__('Installments');
    }

    /**
     * Retrieve tab title
     *
     * @return string
     */
    public function getTabTitle()
    {
        return Mage::helper('sales')->__('Installments');
    }

    /**
     * Check whether can show tab
     *
     * @return bool
     */
    public function canShowTab()
    {
        return $this->getOrder()->getUseInstallments() > 0;
    }

    /**
     * Check whether tab is hidden
     *
     * @return bool
     */
    public function isHidden()
    {
        return false;
    }
}
