<?php
class Itweb_Installments_Model_Observer
{

	public function salesOrderSaveAfter($observer)
	{
		/** @var $Order Mage_Sales_Model_Order */
		$Order = $observer->getOrder();
		if ($Order->getUseInstallments() > 0){
			$Agreement = Itweb_Installments_Helper_Data::getOrderInstallment($Order);
			if ($Agreement->getId() <= 0){
				$Breakdown = Itweb_Installments_Helper_Data::getInstallmentBreakdown($Order);
				$Agreement = $this->_createInstallments($Order, $Breakdown['installments']);
				if (null !== $Agreement){
					$Comment = $Order->addStatusHistoryComment('Created Installment Agreement.');

					$Order->setIsMultiPayment(1);
					$Order->addRelatedObject($Comment);
					$Order->addRelatedObject($Agreement);

					$Order->save();
				}
				else {
					Mage::throwException('Unable to create an installment agreement for this invoices order!');
				}
			}
		}
	}

	public function salesOrderInvoiceSaveAfter($observer)
	{
		/** @var $Invoice Mage_Sales_Model_Order_Invoice */
		$Invoice = $observer->getInvoice();
		if ($Invoice->wasPayCalled()){
			/** @var $Order Mage_Sales_Model_Order */
			$Order = $Invoice->getOrder();
			if ($Order->getUseInstallments() > 0){
				$Breakdown = Itweb_Installments_Helper_Data::getInstallmentBreakdown($Order);
				$Agreement = Itweb_Installments_Helper_Data::getOrderInstallment($Order);

				if ($Agreement->getId() <= 0){
					Mage::throwException('No installment agreement exists for this invoices order!');
				}

				$this->applyInstallmentPayment($Agreement, $Invoice, $Breakdown);

				$Agreement->save();

				if ($Agreement->getState() == Itweb_Installments_Model_Installments::STATUS_CLOSED){
					$Comment = $Order->addStatusHistoryComment('Installment Agreement Fully Paid.');
					$Comment->save();
				}
			}
		}
	}

	public function salesOrderCreditMemoRefund($observer)
	{
		/** @var $CreditMemo Mage_Sales_Model_Order_Creditmemo */
		$CreditMemo = $observer->getCreditmemo();

		$Order = $CreditMemo->getOrder();
		if ($Order->getUseInstallments() > 0){
			$baseAmountToRefund = $CreditMemo->getSubtotal();
			$Installment = Itweb_Installments_Helper_Data::getOrderInstallment($Order);
			$Installment->setOrder($Order);
			if ($CreditMemo->getInvoice()){
				/**
				 * This means we need to find the installment that's tied to this invoice
				 * so we can do a refund against that installment and log the data to it
				 */
				$Invoice = $CreditMemo->getInvoice();
				$InvoicePayment = Itweb_Installments_Helper_Data::getInvoiceInstallmentPayment($Invoice);
				$InvoicePayment->setAmountRefunded($InvoicePayment->getAmountRefunded() + $baseAmountToRefund);

				if ($CreditMemo->getTaxAmount() > 0){
					$Installment->setTaxRefunded($Installment->getTaxRefunded() + $CreditMemo->getTaxAmount());
				}

				if ($CreditMemo->getShippingAmount() > 0){
					$Installment->setShippingRefunded($Installment->getShippingRefunded() + $CreditMemo->getShippingAmount());
				}

				$Installment->setTotalRefunded($Installment->getTotalRefunded() + $baseAmountToRefund);

				$Installment->save();
				$InvoicePayment->save();
			}
			else {
				/**
				 * This means we need to remove X amount from each installment to prevent
				 * the client from paying for items that have been canceled
				 */
			}
		}
	}

	public function payOnPayment(Itweb_Installments_Model_Installments $Agreement, Itweb_Installments_Model_Installmentspayments $Payment, $amount)
	{
		/**
		 * Have to do this because of floating point problems
		 */
		$amount = round($amount, 2);

		$Payment->flagDirty('state');
		$Payment->flagDirty('amount_due');
		$Payment->flagDirty('amount_paid');

		$Payment->setAmountDue(round($Payment->getAmountDue() - $amount, 2));
		$Payment->setAmountPaid(round($Payment->getAmountPaid() + $amount, 2));

		if ($Payment->getAmountDue() == 0){
			$Payment->setState(Itweb_Installments_Helper_Data::PAYMENT_STATE_PAID);
		}
		else {
			$Payment->setState(Itweb_Installments_Helper_Data::PAYMENT_STATE_NOT_PAID);
		}

		$Agreement->setTotalPaid(round($Agreement->getTotalPaid() + $amount, 2));

		$TotalPaid = $Agreement->getTotalPaid() + $Agreement->getTaxPaid() + $Agreement->getShippingPaid();
		if (round($TotalPaid, 2) >= round($Agreement->getTotalDue(), 2)){
			$Agreement->close();
			$Agreement->getOrder()->setStatus(Itweb_Installments_Helper_Data::ORDER_STATUS_PAID);
		}
	}

	public function applyInstallmentPayment(Itweb_Installments_Model_Installments $Agreement, Mage_Sales_Model_Order_Invoice $Invoice, $Breakdown)
	{
		$NumOfInstallments = $Agreement->getNumOfInstallments();

		$Payments = $Agreement->getPayments();
		/**
		 * Taxes are not included in the breakdown because they depend on the final invoice amount
		 * they are also not added to the payment because it cannot be broken up over payments if
		 * it ends up being an odd number, so to prevent a problem we just log it with the agreement
		 * and refunds will subtract their tax amount from this
		 */
		if ($Invoice->getTaxAmount() > 0){
			$Agreement->setTaxPaid($Agreement->getTaxPaid() + $Invoice->getTaxAmount());
		}

		/**
		 * Shipping is only paid once, and it's just easier to store it with the agreement
		 * and if shipping ends up being refunded we can just subtract it from there
		 */
		if ($Invoice->getShippingAmount() > 0){
			$Agreement->setShippingPaid($Agreement->getShippingPaid() + $Invoice->getShippingAmount());
		}

		$ItemsArr = array();
		foreach($Invoice->getAllItems() as $Item){
			if ($Item->getOrderItem()->getParentItemId() > 0){
				continue;
			}
			/** @var $Item Itweb_Installments_Model_Sales_Order_Invoice_Item */
			if (isset($Breakdown[$Item->getOrderItem()->getId()])){
				$Increment = $Breakdown[$Item->getOrderItem()->getId()]['increments'];
				$NumberOfPayments = $Item->getQty() / $Increment;
				$ItemsArr[$Item->getId()] = $NumberOfPayments;
			}
			else {
				Mage::throwException('Unknown product being invoiced, does not exists in the original order!');
			}
		}

		foreach($Agreement->getPayments() as $k => $Payment){
			if ($Payment->isPaid() === false){
				$HadPayment = false;
				/** @var $Payment Itweb_Installments_Model_Installmentspayments */
				foreach($ItemsArr as $ItemId => $NumberOfPayments){
					if ($NumberOfPayments == 0){
						continue;
					}

					/** @var $Item Itweb_Installments_Model_Sales_Order_Invoice_Item */
					$Item = $Invoice->getItemById($ItemId);
					if ($Payment->itemIsPaid($Item) === false){
						$HadPayment = true;

						$ItemBreakdown = $Breakdown[$Item->getOrderItem()->getId()];

						$this->payOnPayment($Agreement, $Payment, $ItemBreakdown['payments'][$k]);
						if ($Payment->isPaid() === true){
							$Comment = $Agreement->getOrder()->addStatusHistoryComment('Installment Payment #' . ($k + 1) . ' Completed.');
							$Comment->save();
						}

						$ItemsArr[$ItemId]--;
					}
				}

				if ($HadPayment === true){
					$Payment->addInvoice($Invoice);
				}
			}
		}
	}

	public function salesOrderInvoiceRegister($observer)
	{
	}

	public function _createInstallments(Mage_Sales_Model_Order $Order, array $installments)
	{
		if ($installments){
			$agreement = Mage::getModel('installments/installments')
				->setOrder($Order)
				->setTotalDue($Order->getGrandTotal())
				->setNumOfInstallments(Itweb_Installments_Helper_Data::getMaxInstallments())
				->setState(Itweb_Installments_Model_Installments::STATUS_OPEN);

			if ($agreement->isValid()){
				foreach($installments as $k => $due){
					/** @var $installmentPayment Itweb_Installments_Model_Installmentspayments */
					$installmentPayment = Mage::getModel('installments/installmentspayments');
					$installmentPayment->setAmountDue($due);
					$installmentPayment->setState(Itweb_Installments_Helper_Data::PAYMENT_STATE_NOT_PAID);

					$agreement->addPayment($installmentPayment);
				}
			}
			else {
				Mage::throwException('Agreement Is Not Valid' . implode("\n<br>", $agreement->getErrors()));
			}

			return $agreement;
		}
		return null;
	}
}