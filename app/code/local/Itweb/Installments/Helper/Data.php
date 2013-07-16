<?php
class Itweb_Installments_Helper_Data
	extends Mage_Core_Helper_Abstract
{

	const PAY_ALL = 1;

	const PAY_INSTALLMENT = 2;

	const PAY_ALL_TEXT = 'Pay Entire Order Now';

	const PAY_INSTALLMENT_TEXT = 'Pay In Installments';

	const XML_PATH_MAX_INSTALLMENTS = 'installments/defaults/max_installments';

	const PAYMENT_STATE_NOT_PAID = 0;

	const PAYMENT_STATE_PAID = 1;

	const PAYMENT_STATE_REFUNDED = 2;

	const PAYMENT_STATE_PAYMENT_PENDING = 3;

	const PAYMENT_STATE_PAYMENT_CANCELED = 4;

	const PAYMENT_STATE_PARTIALLY_REFUNDED = 5;

	const PAYMENT_STATE_PARTIALLY_CANCELED = 6;

	const ORDER_STATUS_PAID = 'paid';

	private static $_orderInstallments = null;
	
	protected $_isRentalInstalled;

	public static function getAgreementStateName($id)
	{
		$return = 'Unknown State';
		switch($id){
			case Itweb_Installments_Model_Installments::STATUS_OPEN:
				$return = Mage::helper('sales')->__('Open');
				break;
			case Itweb_Installments_Model_Installments::STATUS_CLOSED:
				$return = Mage::helper('sales')->__('Closed');
				break;
			case Itweb_Installments_Model_Installments::STATUS_OPEN_MANUAL:
				$return = Mage::helper('sales')->__('Open But Requires Manual Processing');
				break;
		}
		return $return;
	}

	public static function getStateName($id)
	{
		$return = 'Unknown State';
		switch($id){
			case self::PAYMENT_STATE_NOT_PAID:
				$return = 'Not Paid';
				break;
			case self::PAYMENT_STATE_PAID:
				$return = 'Paid';
				break;
			case self::PAYMENT_STATE_REFUNDED:
				$return = 'Refunded';
				break;
			case self::PAYMENT_STATE_PAYMENT_PENDING:
				$return = 'Payment Pending';
				break;
			case self::PAYMENT_STATE_PAYMENT_CANCELED:
				$return = 'Cancelled';
				break;
		}
		return $return;
	}

	public static function estimateTax($Amount, $TaxInfo)
	{
		/** @var $Calc Mage_Tax_Model_Calculation */
		$Calc = Mage::getSingleton('tax/calculation');

		$EstTax = 0;
		foreach($TaxInfo as $tInfo){
			$Rate = $tInfo['percent'];
			$EstTax += $Calc->calcTaxAmount($Amount, $Rate);
		}
		return $EstTax;
	}

	public static function setMaxInstallments($val)
	{
		self::$_orderInstallments = $val;
	}

	public static function getMaxInstallments()
	{
		if (self::$_orderInstallments){
			return self::$_orderInstallments;
		}
		return (int)Mage::getStoreConfig(self::XML_PATH_MAX_INSTALLMENTS);
	}

	public static function getPaymentsBreakdown($Item)
	{
		if ($Item instanceof Mage_Sales_Model_Quote_Item){
			/** @var $Item Mage_Sales_Model_Quote_Item */
			$ItemQuantity = $Item->getQty();
			$RowTotal = $Item->getBaseRowTotal();
			$ItemDiscount = (float)$Item->getBaseDiscountAmount();
			$ItemPrice = $Item->getPrice();
		}
		elseif ($Item instanceof Mage_Sales_Model_Order_Item) {
			/** @var $Item Mage_Sales_Model_Order_Item */
			$ItemQuantity = $Item->getQtyOrdered();
			$ItemPrice = $Item->getPrice();
			$RowTotal = $Item->getBaseRowTotal();
			$ItemDiscount = (float)$Item->getBaseDiscountAmount();
		}
		elseif ($Item instanceof Mage_Sales_Model_Order_Invoice_Item) {
			/** @var $Item Mage_Sales_Model_Order_Invoice_Item */
			$ItemQuantity = $Item->getQty();
			$ItemPrice = $Item->getPrice();
			$RowTotal = $Item->getBaseRowTotal();
			$ItemDiscount = (float)$Item->getOrderItem()->getBaseDiscountAmount();
		}
		else {
			Mage::throwException('Unknown Item Type (' . get_class($Item) . ')!');
		}

		$RowTotal -= $ItemDiscount;

		$Calc = Mage::getModel('core/calculator', $Item->getStore());
		$numOfInstallments = self::getMaxInstallments();
		$Increment = $ItemQuantity / $numOfInstallments;

		$Breakdown = array();
		for($i = 0; $i < $numOfInstallments; $i++){
			$Amount = $Calc->deltaRound($ItemPrice * $Increment);
			$Breakdown[] = $Amount;
		}

		return array(
			'increment' => $Increment,
			'payments'  => $Breakdown
		);
	}

	public static function getInstallmentBreakdown($Total)
	{
		$ShippingCost = 0;
		$DiscountTotal = 0;
		$TaxInfo = array();
		if ($Total instanceof Mage_Sales_Model_Quote){
			$ShippingCost = $Total->getShippingAddress()->getShippingAmount() + $Total->getBillingAddress()->getShippingAmount();
			$DiscountTotal = $Total->getBaseSubtotal() - $Total->getBaseSubtotalWithDiscount();

			/** @var $Calc Mage_Tax_Model_Calculation */
			$Calc = Mage::getSingleton('tax/calculation');

			$TaxRequest = $Calc->getRateRequest(
				$Total->getShippingAddress(),
				$Total->getBillingAddress(),
				$Total->getCustomerTaxClassId(),
				$Total->getStore()
			);
			$TaxInfo = $Calc->getAppliedRates($TaxRequest);
			$Agreement = null;
			if ($Total->getUseInstallments() == Itweb_Installments_Helper_Data::PAY_ALL){
				self::setMaxInstallments(1);
			}
		}
		elseif ($Total instanceof Mage_Sales_Model_Order) {
			$ShippingCost = $Total->getBaseShippingAmount();
			$TaxInfo = $Total->getFullTaxInfo();
			$Agreement = self::getOrderInstallment($Total);
			$DiscountTotal = $Total->getBaseDiscountAmount();
			if ($Total->getUseInstallments() == Itweb_Installments_Helper_Data::PAY_ALL){
				self::setMaxInstallments(1);
			}
		}
		elseif ($Total instanceof Mage_Sales_Model_Order_Invoice) {
			$ShippingCost = $Total->getBaseShippingAmount();
			$TaxInfo = $Total->getOrder()->getFullTaxInfo();
			$Agreement = self::getOrderInstallment($Total->getOrder());
			$DiscountTotal = $Total->getBaseDiscountAmount();
			if ($Total->getOrder()->getUseInstallments() == Itweb_Installments_Helper_Data::PAY_ALL){
				self::setMaxInstallments(1);
			}
		}

		if ($Agreement && $Agreement->getId() > 0){
			self::setMaxInstallments($Agreement->getNumOfInstallments());
		}

		$Breakdown = array(
			'shipping_cost' => $ShippingCost,
			'discounts'     => self::figureDiscounts($Total),
			'taxes'         => array(),
			'installments'  => array()
		);
		foreach($Total->getAllItems() as $Item){
			if ($Item->getParentItemId() > 0){
				continue;
			}

			$PaymentBreakdown = self::getPaymentsBreakdown($Item);
			$Breakdown[$Item->getId()] = array(
				'increments' => $PaymentBreakdown['increment'],
				'payments'   => $PaymentBreakdown['payments'],
				'total'      => 0
			);
			foreach($Breakdown[$Item->getId()]['payments'] as $k => $pInfo){
				$Breakdown[$Item->getId()]['total'] += $pInfo;
				if (!isset($Breakdown['installments'][$k])){
					$Breakdown['installments'][$k] = 0;
				}
				$Breakdown['installments'][$k] += $pInfo;
			}
		}

		foreach($Breakdown as $k => $v){
			if ($k == 'installments'){
				foreach($v as $InstallmentNumber => $InstallmentPayment){
					$Breakdown['taxes'][$InstallmentNumber] = Itweb_Installments_Helper_Data::estimateTax($InstallmentPayment, $TaxInfo);
				}
				break;
			}
		}
		return $Breakdown;
	}

	public static function figureDiscounts($Total)
	{
		return 0;
	}

	/**
	 * @param int|Mage_Sales_Model_Order $Order
	 *
	 * @return Itweb_Installments_Model_Installments
	 */
	public static function getOrderInstallment($Order)
	{
		/** @var $Installment Itweb_Installments_Model_Mysql4_Installments_Collection */
		$Installment = Mage::getModel('installments/installments')
			->getResourceCollection();
		if (is_object($Order)){
			$Installment->addFilter('order_id', $Order->getId());
		}
		else {
			$Installment->addFilter('order_id', $Order);
		}

		$Return = $Installment->getFirstItem();
		if ($Return){
			$Return->load($Return->getId());
			if (is_object($Order)){
				$Return->setOrder($Order);
			}
			else {
				$Return->setOrder(Mage::getModel('sales/order')->load($Order));
			}
		}
		return $Return;
	}

	/**
	 * @param int|Mage_Sales_Model_Invoice $Invoice
	 *
	 * @return Itweb_Installments_Model_Installmentspayments
	 */
	public static function getInvoiceInstallmentPayment($Invoice)
	{
		/** @var $PaymentsInvoices Itweb_Installments_Model_Mysql4_Installmentspaymentsinvoices_Collection */
		$PaymentsInvoices = Mage::getModel('installments/installmentspaymentsinvoices')
			->getResourceCollection();
		if (is_object($Invoice)){
			$PaymentsInvoices->addFilter('invoice_id', $Invoice->getId());
		}
		else {
			$PaymentsInvoices->addFilter('invoice_id', $Invoice);
		}

		$Payment = Mage::getModel('installments/installmentspayments')
			->load($PaymentsInvoices->getFirstItem()->getInstallmentPaymentId());
		return $Payment;
	}

	public static function payInstallment($id)
	{
	}

	/**
	 * @param int        $orderId
	 * @param array      $PaymentInfo
	 * @param null|array $ToPay
	 */
	public static function makePayment($orderId, $PaymentInfo, $ToPay = null)
	{
		$InstallmentAgreement = self::getOrderInstallment($orderId);
		$InstallmentAgreement->loadOrder();

		$Order = $InstallmentAgreement->getOrder();

		if (!$ToPay){
			$ToPay = array();
			foreach($InstallmentAgreement->getPayments() as $_payment){
				if ($_payment->isPaid() === false){
					$ToPay[] = $_payment->getId();
					break;
				}
			}
		}
		else {
			foreach($InstallmentAgreement->getPayments() as $_payment){
				if ($_payment->isPaid() === false){
					if (in_array($_payment->getId(), $ToPay)){
						$key = array_search($_payment->getId(), $ToPay);

						$ToPay[$key] = $_payment->getId();
					}
				}
			}
		}

		$toPayKey = 0;
		foreach($InstallmentAgreement->getPayments() as $k => $_payment){
			if ($_payment->isPaid() === true){
				continue;
			}
			if (isset($ToPay[$toPayKey]) && $ToPay[$toPayKey] != $_payment->getId()){
				if (in_array($_payment->getId(), $ToPay)){
					Mage::throwException('Payments must be made sequentially' . "\n\n" . 'ex. Cannot pay only installment #3 without also paying #2');
				}
			}
			$toPayKey++;
		}

		foreach($ToPay as $k => $v){
			/** @var $PaymentMethod Mage_Sales_Model_Order_Payment */
			$PaymentMethod = Mage::getModel('sales/order_payment');
			$PaymentMethod->setMethod($PaymentInfo['method']);
			$PaymentMethod->getMethodInstance()->assignData($PaymentInfo);
			$Order->addPayment($PaymentMethod);

			try {
				if ($InstallmentAgreement->getState() == Itweb_Installments_Model_Installments::STATUS_OPEN_MANUAL || $InstallmentAgreement->getState() == Itweb_Installments_Model_Installments::STATUS_OPEN){
					$PaymentAmount = $InstallmentAgreement->getPayment($v)->getAmountDue();
					$PaymentMethod->setAmountAuthorized($PaymentAmount);
					$PaymentMethod->setBaseAmountAuthorized($PaymentAmount);
					$PaymentMethod->setAmountPaid($PaymentAmount);
					$PaymentMethod->setBaseAmountPaid($PaymentAmount);
					$PaymentMethod->setBaseAmountPaidOnline($PaymentAmount);
					
					// Invoice
					$invoice = false;
					if ($Order->canInvoice()) {
						$simulateInvoice = Mage::getModel('sales/service_order', $Order)->prepareInvoice(array(), true);
						// Get items and qty
						$items = $simulateInvoice->getAllItems();
						$invoiceData = array('items' => array());
						foreach ($items AS $item) {
							if ($item->getOrderItem()->getParentItem()) continue;
							$invoiceData['items'][$item->getOrderItemId()] = $item->getQty()*1;
						}
						// Prepare invoice again with qty data
						$invoice = Mage::getModel('sales/service_order', $Order)->prepareInvoice($invoiceData['items']);
						$invoice->register();
						$invoice->setEmailSent(true);
						$invoice->getOrder()->setCustomerNoteNotify(true);
						$invoice->getOrder()->setIsInProcess(true);
						$transactionSave = Mage::getModel('core/resource_transaction')
							->addObject($invoice)
							->addObject($invoice->getOrder());
						if (!empty($invoiceData['do_shipment']) || (int) $invoice->getOrder()->getForcedDoShipmentWithInvoice()) {
							$shipment = $this->_prepareShipment($invoice, $invoiceData['items']);
							if ($shipment) {
								$shipment->setEmailSent($invoice->getEmailSent());
								$transactionSave->addObject($shipment);
							}
						}
						$transactionSave->save();
					}
					
					if (!$invoice) {
						$Order->setTotalPaid($Order->getTotalPaid() + $PaymentAmount);
						$Order->setBaseTotalPaid($Order->getTotalPaid() + $PaymentAmount);
						$Order->setTotalDue($Order->getTotalDue() - $PaymentAmount);
						$Order->setBaseTotalDue($Order->getTotalDue() - $PaymentAmount);

					}
					
					$methodInstance = $PaymentMethod->getMethodInstance()
						->setStore($Order->getStoreId());
					if ($methodInstance->canCapture()) {
						$methodInstance->capture($PaymentMethod, $PaymentAmount);
					}

					$AgreementPayment = $InstallmentAgreement->getPayment($v);
					
					
					

					$Observer = new Itweb_Installments_Model_Observer();
					$Observer->payOnPayment(
						$InstallmentAgreement,
						$AgreementPayment,
						$AgreementPayment->getAmountDue()
					);

					$transaction = Mage::getModel('sales/order_payment_transaction')
						->setTxnId($PaymentMethod->getTransactionId())
						->setOrderPaymentObject($PaymentMethod)
						->setTxnType('capture')
						->isFailsafe(false);

					//$PaymentMethod->setLastTransId($transactionId);
					$PaymentMethod->setCreatedTransaction($transaction);
					$Order->addRelatedObject($transaction);
					$Order->addRelatedObject($InstallmentAgreement);
				}
				else {
					$PaymentMethod->place();
				}
				$PaymentMethod->getMethodInstance()->prepareSave();
			} catch(Exception $e){
				Mage::throwException($e->getMessage());
			}
		}
		$Order->save();
	}
	
	/**
     * Prepare shipment
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * $param array $savedQtys
     * @return Mage_Sales_Model_Order_Shipment
     */
	protected function _prepareShipment($invoice, $savedQtys)
    {
        $shipment = Mage::getModel('sales/service_order', $invoice->getOrder())->prepareShipment($savedQtys);
        if (!$shipment->getTotalQty()) {
            return false;
        }


        $shipment->register();
        $tracks = $this->getRequest()->getPost('tracking');
        if ($tracks) {
            foreach ($tracks as $data) {
                $track = Mage::getModel('sales/order_shipment_track')
                    ->addData($data);
                $shipment->addTrack($track);
            }
        }
        return $shipment;
    }

	/**
	 * @param $customerId
	 *
	 * @return Itweb_Installments_Model_Installments[]
	 */
	public static function getCustomerInstallments($customerId)
	{
		$Installments = array();

		/** @var $Collection Mage_Sales_Model_Resource_Order_Collection */
		$Collection = Mage::getModel('sales/order')
			->getCollection();
		$Collection->addFilter('customer_id', $customerId);
		foreach($Collection as $Order){
			/** @var $Order Mage_Sales_Model_Order */
			$Agreement = self::getOrderInstallment($Order);
			if ($Agreement->getId() > 0){
				$Installments[] = $Agreement;
			}
		}

		return $Installments;
	}
	
	/**
	 *
	 * @return bool
	 */
	public function isRentalInstalled()
	{
		if (is_null($this->_isRentalInstalled)) {
			$modules = (array)Mage::getConfig()->getNode('modules')->children();
			if (isset($modules['ITwebexperts_Payperrentals'])) {
				$this->_isRentalInstalled = true;
			} else {
				$this->_isRentalInstalled = false;
			}
		}
		return $this->_isRentalInstalled;
	}
}
	 