<?php
class Itweb_Installments_Model_Sales_Order_Invoice extends Mage_Sales_Model_Order_Invoice
{

    /**
     * Capture invoice
     *
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function capture()
    {
        $Order = $this->getOrder();
        if ($Order->getUseInstallments() > 0) {
            foreach ($this->getOrder()->getPaymentsCollection() as $Payment) {
                /** @var $Payment Mage_Sales_Model_Order_Payment */
                if (!$Payment->isDeleted() && $Payment->getBaseAmountPaidOnline() == 0) {
                    $Payment->capture($this);
                    if ($this->getIsPaid()) {
                        $this->pay();
                    }
                    break;
                }
            }
            return $this;
        } else {
            return parent::capture();
        }
    }
}
