<?php
class Itweb_Installments_Block_Adminhtml_Widget_Grid_Column_Filter_State extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Select
{

    protected static $_statuses;

    public function __construct()
    {
        self::$_statuses = array(
            null => null,
            Itweb_Installments_Model_Installments::STATUS_OPEN => 'Open',
            Itweb_Installments_Model_Installments::STATUS_CLOSED => 'Closed'
        );
        parent::__construct();
    }

    protected function _getOptions()
    {
        $result = array();
        foreach (self::$_statuses as $code => $label) {
            $result[] = array('value' => $code, 'label' => Mage::helper('installments')->__($label));
        }

        return $result;
    }

    public function getCondition()
    {
        if (is_null($this->getValue())) {
            return null;
        }

        return array('eq' => $this->getValue());
    }
}
