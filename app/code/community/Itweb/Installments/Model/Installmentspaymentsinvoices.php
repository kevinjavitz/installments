<?php
class Itweb_Installments_Model_Installmentspaymentsinvoices extends Mage_Core_Model_Abstract
{

    protected function _construct()
    {
        $this->_init("installments/installmentspaymentsinvoices");
    }

    public function findByInstallmentPaymentId($paymentId)
    {
        $Check = Mage::getModel('installments/installmentspaymentsinvoices')->getCollection();
        $Check->addFilter('installment_payment_id', $paymentId);
        return $Check;
    }

    public function checkExists($paymentId, $invoiceId)
    {
        /** @var $Check Itweb_Installments_Model_Mysql4_Installmentspaymentsinvoices_Collection */
        $Check = Mage::getModel('installments/installmentspaymentsinvoices')->getCollection();
        $Check->addFilter('installment_payment_id', $paymentId)
            ->addFilter('invoice_id', $invoiceId);

        return ($Check->count() > 0);
    }
}
