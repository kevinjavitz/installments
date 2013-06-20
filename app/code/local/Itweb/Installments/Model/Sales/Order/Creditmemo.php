<?php
class Itweb_Installments_Model_Sales_Order_Creditmemo
	extends Mage_Sales_Model_Order_Creditmemo
{

	public function refund()
	{
		if ($this->getOrder()->getUseInstallments() > 0){
			$this->setState(self::STATE_REFUNDED);
			$orderRefund = Mage::app()->getStore()->roundPrice(
				$this->getOrder()->getTotalRefunded() + $this->getGrandTotal()
			);
			$baseOrderRefund = Mage::app()->getStore()->roundPrice(
				$this->getOrder()->getBaseTotalRefunded() + $this->getBaseGrandTotal()
			);

			if ($baseOrderRefund > Mage::app()->getStore()->roundPrice($this->getOrder()->getBaseTotalPaid())){

				$baseAvailableRefund = $this->getOrder()->getBaseTotalPaid() - $this->getOrder()->getBaseTotalRefunded();

				Mage::throwException(
					Mage::helper('sales')->__('Maximum amount available to refund is %s', $this->getOrder()->formatBasePrice($baseAvailableRefund))
				);
			}
			$order = $this->getOrder();
			$order->setBaseTotalRefunded($baseOrderRefund);
			$order->setTotalRefunded($orderRefund);

			$order->setBaseSubtotalRefunded($order->getBaseSubtotalRefunded() + $this->getBaseSubtotal());
			$order->setSubtotalRefunded($order->getSubtotalRefunded() + $this->getSubtotal());

			$order->setBaseTaxRefunded($order->getBaseTaxRefunded() + $this->getBaseTaxAmount());
			$order->setTaxRefunded($order->getTaxRefunded() + $this->getTaxAmount());
			$order->setBaseHiddenTaxRefunded($order->getBaseHiddenTaxRefunded() + $this->getBaseHiddenTaxAmount());
			$order->setHiddenTaxRefunded($order->getHiddenTaxRefunded() + $this->getHiddenTaxAmount());

			$order->setBaseShippingRefunded($order->getBaseShippingRefunded() + $this->getBaseShippingAmount());
			$order->setShippingRefunded($order->getShippingRefunded() + $this->getShippingAmount());

			$order->setBaseShippingTaxRefunded($order->getBaseShippingTaxRefunded() + $this->getBaseShippingTaxAmount());
			$order->setShippingTaxRefunded($order->getShippingTaxRefunded() + $this->getShippingTaxAmount());

			$order->setAdjustmentPositive($order->getAdjustmentPositive() + $this->getAdjustmentPositive());
			$order->setBaseAdjustmentPositive($order->getBaseAdjustmentPositive() + $this->getBaseAdjustmentPositive());

			$order->setAdjustmentNegative($order->getAdjustmentNegative() + $this->getAdjustmentNegative());
			$order->setBaseAdjustmentNegative($order->getBaseAdjustmentNegative() + $this->getBaseAdjustmentNegative());

			$order->setDiscountRefunded($order->getDiscountRefunded() + $this->getDiscountAmount());
			$order->setBaseDiscountRefunded($order->getBaseDiscountRefunded() + $this->getBaseDiscountAmount());

			if ($this->getInvoice()){
				$this->getInvoice()->setIsUsedForRefund(true);
				$this->getInvoice()->setBaseTotalRefunded(
					$this->getInvoice()->getBaseTotalRefunded() + $this->getBaseGrandTotal()
				);
				$this->setInvoiceId($this->getInvoice()->getId());
				if (!$this->getPaymentRefundDisallowed()){
					/** @var $Invoice Mage_Sales_Model_Order_Invoice */
					$Invoice = $this->getInvoice();
					if ($this->getDoTransaction()){
						$txnId = $Invoice->getTransactionId();
						$Payment = null;
						foreach($order->getPaymentsCollection() as $_payment){
							/** @var $_payment Mage_Sales_Model_Order_Payment */
							if ($txnId == $_payment->getLastTransId()){
								$Payment = $_payment;
								$Payment->load($Payment->getId());
								$Payment->setOrder($order);
								break;
							}
						}
						if (null !== $Payment){
							$Payment->refund($this);
						}else{
							Mage::throwException('Unable to find the payment with last_trans_id: ' . $txnId);
						}
					}
				}
			}else{
				if (!$this->getPaymentRefundDisallowed()) {
					$order->getPayment()->refund($this);
				}
			}

			Mage::dispatchEvent('sales_order_creditmemo_refund', array($this->_eventObject => $this));
			return $this;
		}else{
			return parent::refund();
		}
	}
}