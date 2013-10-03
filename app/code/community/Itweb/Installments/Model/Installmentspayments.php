<?php

/**
 *
 */
class Itweb_Installments_Model_Installmentspayments extends Mage_Core_Model_Abstract
{

    /**
     * @var Mage_Sales_Model_Order_Payment
     */
    protected $_relatedPayment;

    /**
     * @var Mage_Sales_Model_Order_Invoice
     */
    protected $_relatedInvoices;

    /**
     * Varien Lib doesn't delare this, so i declare it
     *
     * @var array
     */
    protected $_dirty = array();

    /**
     * @var array
     */
    protected $_errors = array();

    /**
     *
     */
    protected function _construct()
    {
        $this->_init("installments/installmentspayments");
    }

    /**
     * @return false|Mage_Sales_Model_Order_Payment_Transaction
     */
    public function hasTransaction()
    {
        return ($this->getPayment()->getTransaction($this->getPayment()->getTansactionId()));
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     *
     * @return Itweb_Installments_Model_Installmentspayments
     */
    public function setPayment(Mage_Sales_Model_Order_Payment $payment)
    {
        $this->_relatedPayment = $payment;
        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $invoice
     *
     * @return Itweb_Installments_Model_Installmentspayments
     */
    public function addInvoice(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $this->_relatedInvoices[] = $invoice;
        return $this;
    }

    /**
     * @return Mage_Sales_Model_Order_Payment
     */
    public function getPayment()
    {
        if (!$this->_relatedPayment) {
            $Payment = Mage::getModel('sales/order_payment')
                ->load($this->getOrderPaymentId());

            $this->setPayment($Payment);
        }
        return $this->_relatedPayment;
    }

    /**
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function getInvoices()
    {
        if (!$this->_relatedInvoices) {
            $Related = Mage::getModel('installments/installmentspaymentsinvoices')
                ->findByInstallmentPaymentId($this->getId());
            foreach ($Related as $_related) {
                $Invoice = Mage::getModel('sales/order_invoice')
                    ->load($_related->getInvoiceId());

                $this->addInvoice($Invoice);
            }
        }
        return $this->_relatedInvoices;
    }

    /**
     * Validate data
     *
     * @return bool
     */
    public function isValid()
    {
        if (!$this->_relatedPayment) {
            //$this->_errors[] = Mage::helper('payment')->__('No payment has been associated with this installment payment.');
        }
        return empty($this->_errors);
    }

    /**
     * @return Mage_Core_Model_Abstract
     * @throws Mage_Core_Exception
     */
    protected function _beforeSave()
    {
        if ($this->isValid()) {
            /** @var $date Mage_Core_Model_Date */
            $date = Mage::getModel('core/date');
            if ($this->isObjectNew() && !$this->getCreatedAt()) {
                $this->setCreatedAt($date->gmtDate('Y-m-d h:i:s'));
            } else {
                $this->setUpdatedAt($date->gmtDate('Y-m-d h:i:s'));
            }

            if ($this->_relatedPayment) {
                $this->setOrderPaymentId($this->_relatedPayment->getId());
            }

            return parent::_beforeSave();
        }
        array_unshift($this->_errors, Mage::helper('payment')->__('Unable to save Installment Payment:'));
        throw new Mage_Core_Exception(implode(' ', $this->_errors));
    }

    protected function _afterSave()
    {
        if ($this->_relatedInvoices) {
            foreach ($this->_relatedInvoices as $Invoice) {
                $Check = Mage::getModel('installments/installmentspaymentsinvoices')
                    ->checkExists($this->getId(), $Invoice->getId());
                if ($Check === false) {
                    $NewPaymentInvoice = Mage::getModel('installments/installmentspaymentsinvoices');
                    $NewPaymentInvoice->setInstallmentPaymentId($this->getId());
                    $NewPaymentInvoice->setInvoiceId($Invoice->getId());
                    $NewPaymentInvoice->save();
                }
            }
        }
        return parent::_afterSave();
    }

    /**
     * @return string
     */
    public function getStateHtml()
    {
        return Itweb_Installments_Helper_Data::getStateName($this->getState());
    }

    /**
     * @return bool
     */
    public function isPaid()
    {
        return $this->getState() == Itweb_Installments_Helper_Data::PAYMENT_STATE_PAID;
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice_Item $Item
     *
     * @return bool
     */
    public function itemIsPaid(Mage_Sales_Model_Order_Invoice_Item $Item)
    {
        $result = false;

        $OrderItemId = $Item->getOrderItemId();
        $Invoices = $this->getInvoices();
        if (is_array($Invoices)) {
            foreach ($Invoices as $Invoice) {
                /** @var $Invoice Mage_Sales_Model_Order_Invoice */
                foreach ($Invoice->getItemsCollection() as $InvoiceProduct) {
                    /** @var $InvoiceProduct Mage_Sales_Model_Order_Invoice_Item */
                    if ($OrderItemId == $InvoiceProduct->getOrderItemId()) {
                        $result = true;
                        break 2;
                    }
                }
            }
        }
        return $result;
    }
}
	 