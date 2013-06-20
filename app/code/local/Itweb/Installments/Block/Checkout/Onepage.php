<?php
class Itweb_Installments_Block_Checkout_Onepage
	extends Mage_Checkout_Block_Onepage
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
}