<?php
class Itweb_Installments_Block_Checkout_Onepage_Progress
	extends Mage_Checkout_Block_Onepage_Progress
{

	/**
	 * Get checkout steps codes
	 *
	 * @return array
	 */
	protected function _getStepCodes()
	{
		return array('login', 'billing', 'shipping', 'shipping_method', 'payment', 'installment', 'review');
	}

	public function getInstallmentHtml()
	{
		switch($this->getQuote()->getUseInstallments()){
			case Itweb_Installments_Helper_Data::PAY_INSTALLMENT:
				$return = Itweb_Installments_Helper_Data::PAY_INSTALLMENT_TEXT;
				break;
			case Itweb_Installments_Helper_Data::PAY_ALL:
				$return = Itweb_Installments_Helper_Data::PAY_ALL_TEXT;
				break;
			default:
				$return = 'Unknown Action';
				break;
		}
		return $return;
	}
}
