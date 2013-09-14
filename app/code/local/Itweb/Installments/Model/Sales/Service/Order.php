<?php
class Itweb_Installments_Model_Sales_Service_Order extends Mage_Sales_Model_Service_Order
{

    /**
     * Prepare order invoice based on order data and requested items qtys. If $qtys is not empty - the function will
     * prepare only specified items, otherwise all containing in the order.
     *
     * @param array $qtys
     * @param bool $simulate
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function prepareInvoice($qtys = array(), $simulate = false)
    {
        if ($this->_order->getUseInstallments() > 0) {
            $invoice = $this->_convertor->toInvoice($this->_order);
            $totalQty = 0;
            foreach ($this->_order->getAllItems() as $orderItem) {
                /** @var $orderItem Mage_Sales_Model_Order_Item */
                if (!$this->_canInvoiceItem($orderItem, array())) {
                    continue;
                }
                $item = $this->_convertor->itemToInvoiceItem($orderItem);
                if ($orderItem->isDummy()) {
                    $qty = $orderItem->getQtyOrdered() ? $orderItem->getQtyOrdered() : 1;
                } elseif (!empty($qtys)) {
                    if (isset($qtys[$orderItem->getId()])) {
                        $qty = (float)$qtys[$orderItem->getId()];
                    }
                } else {
                    $_calculation = Itweb_Installments_Helper_Data::getCalculationByObject($this->_order);
                    if ($_calculation) {
                        $_serializeData = unserialize($_calculation->getInstallmentsSerialize());
                        if (!isset($_serializeData[$orderItem->getId()]['count_of_payment'])) $_serializeData[$orderItem->getId()]['count_of_payment'] = 0;
                        $qty = $_serializeData[$orderItem->getId()]['increments'][$_serializeData[$orderItem->getId()]['count_of_payment']];
                        $_serializeData[$orderItem->getId()]['count_of_payment']++;
                        try {
                            $_calculation->setInstallmentsSerialize(serialize($_serializeData));
                            $_calculation->save();
                        } catch (Exception $_e) {
                            Mage::throwException($_e->getMessage());
                        }

                    } else {
                        $Breakdown = Itweb_Installments_Helper_Data::getInstallmentBreakdown($this->_order);
                        $qty = $Breakdown[$orderItem->getId()]['increments'];
                    }
                }
                $totalQty += $qty;
                $item->setQty($qty);
                $invoice->addItem($item);
            }
            $invoice->setTotalQty($totalQty);
            $invoice->collectTotals();
            if (!$simulate) {
                $this->_order->getInvoiceCollection()->addItem($invoice);
            }
            return $invoice;
        } else {
            return parent::prepareInvoice($qtys);
        }
    }
}
