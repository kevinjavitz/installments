<?php
class Itweb_Installments_IndexController
	extends Mage_Core_Controller_Front_Action
{

	/**
	 * Retrieve customer session model object
	 *
	 * @return Mage_Customer_Model_Session
	 */
	protected function _getSession()
	{
		return Mage::getSingleton('customer/session');
	}

	public function IndexAction()
	{
		if ($this->_getSession()->isLoggedIn() === false){
			$this->_redirect('customer/account/login');
			return;
		}

		$this->loadLayout();
		$this->_initLayoutMessages('customer/session');
		$this
			->getLayout()
			->getBlock("head")
			->setTitle($this->__("My Installment Agreements"));

		$this->renderLayout();
	}

	public function makePaymentAction()
	{
		if (!$this->getRequest()->isAjax()){
			return;
		}

		$ToPay = $this->getRequest()->getParam('installment_payment');
		if (is_array($ToPay) === false){
			$ToPay = null;
		}

		try {
			foreach($ToPay as $orderId => $_topay){
				Itweb_Installments_Helper_Data::makePayment(
					$orderId,
					$this->getRequest()->getParam('payment'),
					$_topay
				);
			}
			$this->_getSession()->addSuccess($this->__('The installment payment(s) has been paid.'));
			Mage::getSingleton('customer/session')->getCommentText(true);

			$success = true;
			$message = '';
		}catch (Exception $e){
			$success = false;
			$message = $e->getMessage();
		}


		$this->getResponse()
			->setHeader('Content-type', 'application/json')
			->setBody(Mage::helper('core')->jsonEncode(array(
			'success'          => $success,
			'response_message' => $message
		)));
	}
}