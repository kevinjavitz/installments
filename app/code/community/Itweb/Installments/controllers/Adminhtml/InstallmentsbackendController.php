<?php
class Itweb_Installments_Adminhtml_InstallmentsbackendController extends Mage_Adminhtml_Controller_Action
{

    public function indexAction()
    {
        $this->loadLayout();
        $this->_title($this->__("Installment Agreements"));
        $this->renderLayout();
    }

    public function makePaymentAction()
    {
        if (!$this->getRequest()->isAjax()) {
            Mage::throwException('Payment Requests Must Be Submitted Via Ajax!');
        }

        $ToPay = $this->getRequest()->getParam('installment_payment');
        if (is_array($ToPay) === false) {
            $ToPay = null;
        }

        try {
            Itweb_Installments_Helper_Data::makePayment(
                $this->getRequest()->getParam('order_id'),
                $this->getRequest()->getParam('payment'),
                $ToPay
            );
            $this->_getSession()->addSuccess($this->__('The installment payment(s) has been paid.'));
            Mage::getSingleton('adminhtml/session')->getCommentText(true);

            $success = true;
            $message = '';
        } catch (Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        $this->getResponse()
            ->setHeader('Content-type', 'application/json')
            ->setBody(Mage::helper('core')->jsonEncode(array(
                'success' => $success,
                'response_message' => $message
            )));
    }

    public function exportCsvAction()
    {
        $fileName = 'installments.csv';
        $grid = $this->getLayout()->createBlock('installments/adminhtml_installmentsbackend_grid');
        $this->_prepareDownloadResponse($fileName, $grid->getCsv());
    }
}