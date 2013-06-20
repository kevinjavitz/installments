<?php
class Itweb_Installments_Adminhtml_Sales_Order_CreateController extends Mage_Adminhtml_Controller_Action {
	
	protected function _getQuote()
	{
		return Mage::getSingleton('adminhtml/session_quote')->getQuote();
	}
	
	public function saveAction()
	{
		$result = array();
		try {
			$quote = $this->_getQuote();
			$method = $this->getRequest()->getPost('method', null);
			$quote->setUseInstallments($method);
			$quote->save();
			$result = array('success' => true);
		} catch (Mage_Core_Exception $e) {
			$result['error'] = $e->getMessage();
		} catch (Exception $e) {
			$result['error'] = $e->getMessage();
		}
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
	}
	
}