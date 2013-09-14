<?php
class Itweb_Installments_Model_Sales_Order_Payment
	extends Mage_Sales_Model_Order_Payment
{
	protected function _addTransaction($type, $salesDocument = null, $failsafe = false)
	{
		if ($this->getSkipTransactionCreation()) {
			$this->unsTransactionId();
			return null;
		}

		// look for set transaction ids
		$transactionId = $this->getTransactionId();
		if (null !== $transactionId) {
			// set transaction parameters
			$transaction = false;
			if ($this->getOrder()->getId()) {
				$transaction = $this->_lookupTransaction($transactionId);
			}
			if (!$transaction) {
				$transaction = Mage::getModel('sales/order_payment_transaction')->setTxnId($transactionId);
			}
			$transaction
				->setOrderPaymentObject($this)
				->setTxnType($type)
				->isFailsafe($failsafe);

			if ($this->hasIsTransactionClosed()) {
				$transaction->setIsClosed((int)$this->getIsTransactionClosed());
			}

			//set transaction addition information
			if ($this->_transactionAdditionalInfo) {
				foreach ($this->_transactionAdditionalInfo as $key => $value) {
					$transaction->setAdditionalInformation($key, $value);
				}
			}

			// link with sales entities
			/**
			 * Itweb: Dont overwrite the original transaction id,
			 * otherwise any further refunds cannot go through because
			 * it will not know the original transaction id
			 */
			if ($type != Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND){
				$this->setLastTransId($transactionId);
			}

			$this->setCreatedTransaction($transaction);
			$this->getOrder()->addRelatedObject($transaction);
			if ($salesDocument && $salesDocument instanceof Mage_Sales_Model_Abstract) {
				/**
				 * Itweb: Investigate later because linking a single transaction id
				 * to an order limits the ability to have multiple transactions per order
				 */
				$salesDocument->setTransactionId($transactionId);
				// TODO: linking transaction with the sales document
			}

			// link with parent transaction
			$parentTransactionId = $this->getParentTransactionId();

			if ($parentTransactionId) {
				$transaction->setParentTxnId($parentTransactionId);
				if ($this->getShouldCloseParentTransaction()) {
					$parentTransaction = $this->_lookupTransaction($parentTransactionId);
					if ($parentTransaction) {
						if (!$parentTransaction->getIsClosed()) {
							$parentTransaction->isFailsafe($failsafe)->close(false);
						}
						$this->getOrder()->addRelatedObject($parentTransaction);
					}
				}
			}
			return $transaction;
		}
        return null;
	}
}