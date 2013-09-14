<?php

class Itweb_Installments_Model_Installments
	extends Mage_Core_Model_Abstract
{

	const STATUS_OPEN = 0;

	const STATUS_CLOSED = 1;

	const STATUS_OPEN_MANUAL = 2;

	/**
	 * @var Mage_Sales_Model_Order
	 */
	protected $_order;

	/**
	 * @var Mage_Sales_Model_Quote
	 */
	protected $_quote;

	/**
	 * Agreement Errors
	 *
	 * @var array
	 */
	protected $_errors = array();

	/**
	 * Related agreement installment payments
	 *
	 * @var array
	 */
	protected $_relatedInstallmentPayments = array();

	protected function _construct()
	{
		$this->_init("installments/installments");
	}

	/**
	 * @return Itweb_Installments_Model_Installments
	 */
	public function loadOrder()
	{
		/** @var $Order Mage_Sales_Model_Order */
		$Order = Mage::getModel('sales/order')
			->load($this->getOrderId());

		$this->setOrder($Order);
		$this->loadQuote();
		return $this;
	}

	/**
	 * @param Mage_Sales_Model_Order $Order
	 *
	 * @return Itweb_Installments_Model_Installments
	 */
	public function setOrder(Mage_Sales_Model_Order $Order)
	{
		$this->_order = $Order;
		return $this;
	}

	/**
	 * @return Itweb_Installments_Model_Installments
	 */
	public function loadQuote()
	{
		$Quote = Mage::getModel('sales/quote')
			->load($this->_order->getQuoteId());

		$this->setQuote($Quote);
		return $this;
	}

	/**
	 * @param Mage_Sales_Model_Quote $Quote
	 *
	 * @return Itweb_Installments_Model_Installments
	 */
	public function setQuote(Mage_Sales_Model_Quote $Quote)
	{
		$this->_quote = $Quote;
		return $this;
	}

	/**
	 * @return Mage_Sales_Model_Order
	 */
	public function getOrder()
	{
		if (!$this->_order){
			$order = Mage::getModel('sales/order')
				->load($this->getOrderId());
			$this->setOrder($order);
		}
		return $this->_order;
	}

	public function getQuote()
	{
		if (!$this->_quote){
			$quote = Mage::getModel('sales/quote')
				->load($this->getOrder()->getQuoteId());
			$this->setQuote($quote);
		}
		return $this->_quote;
	}

	/**
	 * @param Itweb_Installments_Model_Installmentspayments $payment
	 * @param bool                                          $flagDirty
	 *
	 * @return Itweb_Installments_Model_Installments
	 */
	public function addPayment(Itweb_Installments_Model_Installmentspayments $payment, $flagDirty = true)
	{
		/**
		 * Set the payment as "dirty" meaning it has data that needs to be sent to the db
		 */
		if ($flagDirty === true){
			$payment->flagDirty('state');
		}
		$this->_relatedInstallmentPayments[] = $payment;
		return $this;
	}

	/**
	 * @return Mage_Core_Model_Abstract
	 */
	public function _afterLoad()
	{
		/**
		 * Might not need???
		 */
		$this->_relatedInstallmentPayments = array();

		/** @var $Payments Itweb_Installments_Model_Mysql4_Installmentspayments_Collection */
		$Payments = Mage::getModel('installments/installmentspayments')
			->getCollection();
		$Payments->addFilter('installment_id', $this->getInstallmentId());
		foreach($Payments as $Payment){
			$this->addPayment($Payment, false);
		}
		return parent::_afterLoad();
	}

	/**
	 * @return Mage_Core_Model_Abstract
	 * @throws Mage_Core_Exception
	 */
	protected function _beforeSave()
	{
		if ($this->isValid()){
			/** @var $date Mage_Core_Model_Date */
			$date = Mage::getModel('core/date');
			if ($this->isObjectNew() && !$this->getCreatedAt()){
				$this->setCreatedAt($date->gmtDate('Y-m-d h:i:s'));
			}
			else {
				$this->setUpdatedAt($date->gmtDate('Y-m-d h:i:s'));
			}

			$this->setOrderId($this->_order->getId());
			return parent::_beforeSave();
		}
		array_unshift($this->_errors, Mage::helper('payment')->__('Unable to save Installment Agreement:'));
		throw new Mage_Core_Exception(implode(' ', $this->_errors));
	}

	/**
	 * Save agreement order relations
	 *
	 * @return Mage_Core_Model_Abstract
	 */
	protected function _afterSave()
	{
		if (!empty($this->_relatedInstallmentPayments)){
			foreach($this->_relatedInstallmentPayments as $payment){
				/** @var $payment Itweb_Installments_Model_Installmentspayments */
				if ($payment->isDirty()){
					$payment->setInstallmentId($this->getId());
					$payment->save();
				}
			}
		}
		return parent::_afterSave();
	}

	/**
	 * Retrieve billing agreement status label
	 *
	 * @return string
	 */
	public function getStateLabel()
	{
		switch($this->getState()){
            default:
			case self::STATUS_OPEN:
				return Mage::helper('sales')->__('Open');
			case self::STATUS_CLOSED:
				return Mage::helper('sales')->__('Closed');
			case self::STATUS_OPEN_MANUAL:
				return Mage::helper('sales')->__('Open But Requires Manual Processing');
		}
	}

	/**
	 * Cancel billing agreement
	 *
	 * @return Mage_Sales_Model_Billing_Agreement
	 */
	public function close()
	{
		$this->setState(self::STATUS_CLOSED);
		return $this->save();
	}

	/**
	 * Check whether can cancel billing agreement
	 *
	 * @return bool
	 */
	public function canClose()
	{
		return ($this->getState() != self::STATUS_CLOSED);
	}

	/**
	 * Retrieve billing agreement statuses array
	 *
	 * @return array
	 */
	public function getStatesArray()
	{
		return array(
			self::STATUS_OPEN   => Mage::helper('sales')->__('Open'),
			self::STATUS_CLOSED => Mage::helper('sales')->__('Closed'),
			self::STATUS_OPEN_MANUAL => Mage::helper('sales')->__('Open But Requires Manual Processing')
		);
	}

	/**
	 * Validate data
	 *
	 * @return bool
	 */
	public function isValid()
	{
		if (!$this->_order){
			$this->_errors[] = Mage::helper('payment')->__('No order has been set for the installment agreement.');
		}
		$Statuses = $this->getStatesArray();
		if (isset($Statuses[$this->getState()]) === false){
			$this->_errors[] = Mage::helper('payment')->__('Installment Agreement status is not set.');
		}
		return empty($this->_errors);
	}

	/**
	 * @param $id
	 *
	 * @return Itweb_Installments_Model_Installmentspayments|null
	 */
	public function &getPayment($id)
	{
		foreach($this->getPayments() as $k => $Payment){
			if ($Payment->getId() == $id){
				return $this->_relatedInstallmentPayments[$k];
			}
		}
		return null;
	}

	/**
	 * @return array
	 */
	public function getPayments()
	{
		return $this->_relatedInstallmentPayments;
	}
}
	 