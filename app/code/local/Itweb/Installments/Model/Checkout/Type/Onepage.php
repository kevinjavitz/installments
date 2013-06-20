<?php
class Itweb_Installments_Model_Checkout_Type_Onepage
	extends Mage_Checkout_Model_Type_Onepage
{

	public function savePayment($data)
	{
		parent::savePayment($data);

		$this->getCheckout()
			->setStepData('installment', 'allow', true)
			->setStepData('review', 'allow', false);
	}

	public function saveInstallment(array $data)
	{
		if (empty($data)) {
			return array('error' => -1, 'message' => Mage::helper('checkout')->__('Invalid data.'));
		}

		$quote = $this->getQuote();
		$quote->setUseInstallments($data['method']);
		$quote->save();

		$this->getCheckout()
			->setStepData('installment', 'complete', true)
			->setStepData('review', 'allow', true);

		return array();
	}
}
