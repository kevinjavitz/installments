<?php
# Controllers are not autoloaded so we will have to do it manually:
require_once 'Mage/Checkout/controllers/OnepageController.php';

class Itweb_Installments_Checkout_OnepageController
	extends Mage_Checkout_OnepageController
{

	protected $_sectionUpdateFunctions = array(
		'payment-method'  => '_getPaymentMethodsHtml',
		'shipping-method' => '_getShippingMethodsHtml',
		'review'          => '_getReviewHtml',
		'installment'     => '_getInstallmentHtml'
	);

	protected function _getInstallmentHtml()
	{
		return $this->getLayout()->getBlock('checkout.onepage.installment')->toHtml();
	}

	public function savePaymentAction()
	{
		parent::savePaymentAction();

		$OldResponse = Mage::helper('core')->jsonDecode($this->getResponse()->getBody());

		$OldResponse['goto_section'] = 'installment';

		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($OldResponse));
	}

	public function saveInstallmentAction()
	{
		if ($this->_expireAjax()) {
			return;
		}
		try {
			if (!$this->getRequest()->isPost()) {
				$this->_ajaxRedirectResponse();
				return;
			}

			// set payment to quote
			$result = array();
			$data = $this->getRequest()->getPost('installment', array());
			$result = $this->getOnepage()->saveInstallment($data);

			// get section and redirect data
			if (empty($result['error'])) {
				$this->loadLayout('checkout_onepage_review');
				if ($this->getOnepage()->getQuote()->getUseInstallments() == Itweb_Installments_Helper_Data::PAY_INSTALLMENT){
					$block = $this->getLayout()->createBlock(
						'Mage_Checkout_Block_Onepage',
						'installment_summary',
						array('template' => 'installments/checkout/onepage/installment_summary.phtml')
					)->setBlockAlias('items_after');

					$this->getLayout()->getBlock('checkout.onepage.review.info.items.after')->append($block);
				}
				$result['goto_section'] = 'review';
				$result['update_section'] = array(
					'name' => 'review',
					'html' => $this->_getReviewHtml()
				);
			}
		} catch (Mage_Payment_Exception $e) {
			if ($e->getFields()) {
				$result['fields'] = $e->getFields();
			}
			$result['error'] = $e->getMessage();
		} catch (Mage_Core_Exception $e) {
			$result['error'] = $e->getMessage();
		} catch (Exception $e) {
			Mage::logException($e);
			$result['error'] = $this->__('Unable to set Payment Preference.');
		}
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
	}

    public function recalculateAction()
    {
        $_params = $this->getRequest()->getParams();
        $_result = Itweb_Installments_Helper_Data::recalculateInstallment($_params);
        if (!$_result) {
            $_response['error'] = 'Can\'t load quote. Used default price calculation';
        } else {
            $_response['updateSummary'] = $this->getLayout()->createBlock('checkout/onepage')->setTemplate('installments/checkout/onepage/installment_summary.phtml')->toHtml();
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($_response));
    }
}