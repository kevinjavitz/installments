<?php
class Itweb_Installments_Block_Checkout_Onepage_Installment extends Mage_Checkout_Block_Onepage_Abstract
{

    protected function _construct()
    {
        $this->getCheckout()->setStepData('installment', array(
            'label' => Mage::helper('checkout')->__('Payment Processing Preference'),
            'is_show' => $this->isShow()
        ));

        $this->getCheckout()->setStepData('installment', 'allow', false);

        parent::_construct();
    }
}