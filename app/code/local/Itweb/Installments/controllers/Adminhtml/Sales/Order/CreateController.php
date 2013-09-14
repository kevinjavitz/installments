<?php
class Itweb_Installments_Adminhtml_Sales_Order_CreateController extends Mage_Adminhtml_Controller_Action
{

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
            $_calculation = Mage::getSingleton('installments/calculation')->load($quote->getId(), 'quote_id');
            if ($_calculation->getId() && $_calculation->getInstallmentsSerialize() != '') {
                $_serializeData = unserialize($_calculation->getInstallmentsSerialize());
                if ($_serializeData['is_custom']) {
                    $_serializeData['is_custom'] = false;
                    $_calculation->setInstallmentsSerialize(serialize($_serializeData));
                    $_calculation->save();
                }
            }
        } catch (Mage_Core_Exception $e) {
            $result['error'] = $e->getMessage();
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    public function recalculateAction()
    {
        $_params = $this->getRequest()->getParams();
        $_result = Itweb_Installments_Helper_Data::recalculateInstallment($_params);
        $_response = array();
        if (!$_result) {
            $_response['error'] = 'Can\'t load quote. Used default price calculation';
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($_response));
    }

}