<?php
class Itweb_Installments_Helper_Data extends Mage_Core_Helper_Abstract
{

    const PAY_ALL = 1;

    const PAY_INSTALLMENT = 2;

    const PAY_ALL_TEXT = 'Pay Entire Order Now';

    const PAY_INSTALLMENT_TEXT = 'Pay In Installments';

    const XML_PATH_MAX_INSTALLMENTS = 'installments/defaults/max_installments';
    const XML_PATH_ALLOW_ADMIN_INSTALLMENT_AMOUNT = 'installments/defaults/allow_admin_installment_amount';
    const XML_PATH_ALLOW_CUSTOMER_INSTALLMENT_AMOUNT = 'installments/defaults/allow_customer_installment_amount';

    const PAYMENT_STATE_NOT_PAID = 0;
    const PAYMENT_STATE_PAID = 1;
    const PAYMENT_STATE_REFUNDED = 2;
    const PAYMENT_STATE_PAYMENT_PENDING = 3;
    const PAYMENT_STATE_PAYMENT_CANCELED = 4;
    const PAYMENT_STATE_PARTIALLY_REFUNDED = 5;
    const PAYMENT_STATE_PARTIALLY_CANCELED = 6;

    const ORDER_STATUS_PAID = 'installments_paid';
    const ORDER_STATUS_PENDING_INSTALLMENT_PAID = 'pending_installment';
    const ORDER_STATE_PENDING_INSTALLMENT_PAID = 'pending_installment';
    const ORDER_STATE_PAID = 'installments_paid';

    private static $_orderInstallments = null;
    private static $_moneyCache = 0;
    private static $_applyCacheStep = null;

    protected $_isRentalInstalled;

    public static function getAgreementStateName($id)
    {
        $return = 'Unknown State';
        switch ($id) {
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
        switch ($id) {
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
        foreach ($TaxInfo as $tInfo) {
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
        if (self::$_orderInstallments) {
            return self::$_orderInstallments;
        }
        return (int)Mage::getStoreConfig(self::XML_PATH_MAX_INSTALLMENTS);
    }

    public static function getPaymentsBreakdown($Item, $_customInstallmentAr = null, $_totalQty = null, $_cartGrandTotal = null)
    {
        $ItemQuantity = self::getQtyByItem($Item);
        $ItemPrice = $Item->getPrice();

        $Calc = Mage::getModel('core/calculator', $Item->getStore());
        $numOfInstallments = self::getMaxInstallments();
        $Breakdown = array();
        $Increment = array();

        if (!is_null($_customInstallmentAr) && !is_null($_totalQty) && !is_null($_cartGrandTotal) && $numOfInstallments != 1) {
            $_notDivideInstallment = false;
            $numOfInstallments = 0;

            $_totalForPaid = 0;
            $_totalItemPaid = 0;
            foreach ($_customInstallmentAr as $_customInstallment) {
                $_totalForPaid += $_customInstallment;
                if ($_totalForPaid >= $_cartGrandTotal) $_customInstallment -= $_totalForPaid - $_cartGrandTotal;
                if (self::$_moneyCache && $numOfInstallments == self::$_applyCacheStep) {
                    $_amountForItem = $_customInstallment / $_totalQty + self::$_moneyCache;
                    self::$_moneyCache = 0;
                    $_notDivideInstallment = true;
                } else {
                    $_amountForItem = ($_notDivideInstallment) ? $_customInstallment : $_customInstallment / $_totalQty;
                }
                if ($_amountForItem + $_totalItemPaid >= $ItemPrice) {
                    self::$_moneyCache = $_amountForItem + $_totalItemPaid - $ItemPrice;
                    $_amountForItem = $ItemPrice - $_totalItemPaid;
                    $Increment[] = $_amountForItem / $ItemPrice / $ItemQuantity;
                    self::$_applyCacheStep = $numOfInstallments;
                    $numOfInstallments++;
                    $_totalItemPaid += $_amountForItem;
                    break;
                } else {
                    $Increment[] = $_amountForItem / $ItemPrice / $ItemQuantity;
                }
                $_totalItemPaid += $_amountForItem;
                $numOfInstallments++;
            }
            if ($_totalForPaid < $_cartGrandTotal && $numOfInstallments != 1) {
                while ($numOfInstallments < (int)Mage::getStoreConfig(self::XML_PATH_MAX_INSTALLMENTS) && $_totalItemPaid != $ItemPrice) {
                    $_amountForItem = $_cartGrandTotal - $_totalForPaid;
                    $Increment[] = $_amountForItem / $ItemPrice / $ItemQuantity;
                    $numOfInstallments++;
                }
            }

        }
        /** If using round, then price will be < than actual product price*/
        for ($i = 0; $i < $numOfInstallments; $i++) {
            if (is_null($_customInstallmentAr) || is_null($_totalQty) || is_null($_cartGrandTotal)) {
                $Increment[$i] = $ItemQuantity / $numOfInstallments;
            }
            $Amount = $Calc->deltaRound($ItemPrice * $Increment[$i]);
            $Breakdown[$i] = $Amount;
        }

        return array(
            'increments' => $Increment,
            'payments' => $Breakdown,
            'item_num_of_installments' => $numOfInstallments
        );
    }

    /**
     * Compare 2 items arrays for changes. Return true if items ids or prices changed
     * @param $_dbBrekdownItemsAr
     * @param $_objectItemsAr
     *
     * @return bool
     */
    public static function compareItems($_dbBrekdownItemsAr, $_objectItemsAr)
    {
        if (count($_dbBrekdownItemsAr) != count($_objectItemsAr)) return true;
        foreach ($_objectItemsAr as $_productItem) {
            $_productId = $_productItem->getId();
            if (!array_key_exists($_productId, $_dbBrekdownItemsAr) || $_dbBrekdownItemsAr[$_productId]['price'] != $_productItem->getPrice() || $_dbBrekdownItemsAr[$_productId]['qtyOrdered'] != self::getQtyByItem($_productItem)) return true;
        }
        return false;
    }

    /**
     * Get Qty by item class type
     * @param mixed $_item
     *
     * @return int
     * */
    public static function getQtyByItem($_item)
    {
        if ($_item instanceof Mage_Sales_Model_Quote_Item) {
            /** @var $_item Mage_Sales_Model_Quote_Item */
            /*$ItemDiscount = (float)$Item->getBaseDiscountAmount();*/
            return $_item->getQty();
        } elseif ($_item instanceof Mage_Sales_Model_Order_Item) {
            /** @var $_item Mage_Sales_Model_Order_Item */
            /*$ItemDiscount = (float)$Item->getBaseDiscountAmount();*/
            return $_item->getQtyOrdered();
        } elseif ($_item instanceof Mage_Sales_Model_Order_Invoice_Item) {
            /** @var $_item Mage_Sales_Model_Order_Invoice_Item */
            /*$ItemDiscount = (float)$Item->getOrderItem()->getBaseDiscountAmount();*/
            return $_item->getQty();
        } else {
            Mage::throwException('Unknown Item Type (' . get_class($_item) . ')!');
            return 0;
        }
    }

    public static function convertQuoteToOrderSerialize($_quoteCalculationItem, $_orderObject)
    {
        $_serializeData = unserialize($_quoteCalculationItem->getInstallmentsSerialize());
        $_newOrderSerialize = $_serializeData;
        $_compareArray = array();
        foreach ($_orderObject->getAllItems() as $_orderItem) {
            $_compareArray[$_orderItem->getQuoteItemId()] = $_orderItem->getId();
        }

        foreach ($_compareArray as $_quoteItemId => $_orderItemId) {
            if ($_quoteItemId == $_orderItemId) continue;
            $_newOrderSerialize[$_orderItemId] = $_newOrderSerialize[$_quoteItemId];
            unset($_newOrderSerialize[$_quoteItemId]);
            $_newOrderSerialize['items'][$_orderItemId] = $_newOrderSerialize['items'][$_quoteItemId];
            unset($_newOrderSerialize['items'][$_quoteItemId]);
        }

        $_calculation = Mage::getModel('installments/calculation')->setInstallmentsSerialize(serialize($_newOrderSerialize))->setOrderId($_orderObject->getId());
        try {
            $_calculation->save();
        } catch (Exception $_e) {
            Mage::throwException('Wrong convert quote to order');
        }
        return $_calculation;
    }

    public static function getCalculationByObject($_itemObject)
    {
        if ($_itemObject instanceof Mage_Sales_Model_Quote) {
            /** @var $_item Mage_Sales_Model_Quote_Item */
            $_loadKey = 'quote_id';
            $_loadValue = $_itemObject->getId();
        } elseif ($_itemObject instanceof Mage_Sales_Model_Order) {
            $_loadKey = 'order_id';
            $_loadValue = $_itemObject->getId();
        } elseif ($_itemObject instanceof Mage_Sales_Model_Order_Invoice) {
            $_loadKey = 'invoice_id';
            $_loadValue = $_itemObject->getId();
        } else {
            Mage::throwException('Unknown Item Type (' . get_class($_itemObject) . ')!');
            return false;
        }

        $_calculation = Mage::getSingleton('installments/calculation')->load($_loadValue, $_loadKey);
        if ($_itemObject instanceof Mage_Sales_Model_Order && !$_calculation->getId()) {
            $_calculation = Mage::getSingleton('installments/calculation')->load($_itemObject->getQuoteId(), 'quote_id');
            $_calculation = self::convertQuoteToOrderSerialize($_calculation, $_itemObject);
        }
        if (!$_calculation->getId()) return false;
        return $_calculation;
    }

    /**
     * Custom function sorting items by price
     *
     * @param Mage_Sales_Model_Quote_Item|Mage_Sales_Model_Order_Item|Mage_Sales_Model_Order_Invoice_Item $_firstItem
     * @param Mage_Sales_Model_Quote_Item|Mage_Sales_Model_Order_Item|Mage_Sales_Model_Order_Invoice_Item $secondItem
     *
     * @return int
     * */
    public static function sortItemsByPrice($_firstItem, $secondItem)
    {
        if ($_firstItem->getPrice() == $secondItem->getPrice()) return 0;
        return ($_firstItem->getPrice() > $secondItem->getPrice()) ? 1 : -1;
    }

    public static function getInstallmentBreakdown($Total)
    {
        $ShippingCost = 0;
        $DiscountTotal = 0;
        $TaxInfo = array();
        if ($Total instanceof Mage_Sales_Model_Quote) {
            $_searchKey = 'quote_id';
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
            if ($Total->getUseInstallments() == Itweb_Installments_Helper_Data::PAY_ALL) {
                self::setMaxInstallments(1);
            }
        } elseif ($Total instanceof Mage_Sales_Model_Order) {
            $_searchKey = 'order_id';
            $ShippingCost = $Total->getBaseShippingAmount();
            $TaxInfo = $Total->getFullTaxInfo();
            $Agreement = self::getOrderInstallment($Total);
            $DiscountTotal = $Total->getBaseDiscountAmount();
            if ($Total->getUseInstallments() == Itweb_Installments_Helper_Data::PAY_ALL) {
                self::setMaxInstallments(1);
            }
        } elseif ($Total instanceof Mage_Sales_Model_Order_Invoice) {
            $_searchKey = 'invoice_id';
            $ShippingCost = $Total->getBaseShippingAmount();
            $TaxInfo = $Total->getOrder()->getFullTaxInfo();
            $Agreement = self::getOrderInstallment($Total->getOrder());
            $DiscountTotal = $Total->getBaseDiscountAmount();
            if ($Total->getOrder()->getUseInstallments() == Itweb_Installments_Helper_Data::PAY_ALL) {
                self::setMaxInstallments(1);
            }
        }

        if ($Agreement && $Agreement->getId() > 0) {
            self::setMaxInstallments($Agreement->getNumOfInstallments());
        }

        if ($Total->getId()) {
            $_calculation = self::getCalculationByObject($Total);
        }
        if (isset($_calculation) && $_calculation !== false) {
            $Breakdown = unserialize($_calculation->getInstallmentsSerialize());
            $_isItemsChanged = self::compareItems($Breakdown['items'], $Total->getAllItems());
            $_isNumOfInstallmentsChanged = ($Breakdown['num_of_installments'] != self::getMaxInstallments()) ? true : false;
            $_isShippingChanged = ($Breakdown['shipping_cost'] != $ShippingCost) ? true : false;
            if (!($Total instanceof Mage_Sales_Model_Quote) || $Breakdown['is_custom'] || (!$_isItemsChanged && !$_isNumOfInstallmentsChanged && !$_isShippingChanged)) return $Breakdown;
        }

        $Breakdown = array(
            'shipping_cost' => $ShippingCost,
            'is_custom' => false,
            'num_of_installments' => self::getMaxInstallments(),
            'discounts' => self::figureDiscounts($Total),
            'taxes' => array(),
            'installments' => array()
        );
        foreach ($Total->getAllItems() as $Item) {
            if ($Item->getParentItemId() > 0) {
                continue;
            }
            $Breakdown['items'][$Item->getId()] = array(
                'price' => $Item->getPrice(),
                'qtyOrdered' => self::getQtyByItem($Item)
            );

            $PaymentBreakdown = self::getPaymentsBreakdown($Item);
            $Breakdown[$Item->getId()] = array(
                'increments' => $PaymentBreakdown['increments'],
                'payments' => $PaymentBreakdown['payments'],
                'item_num_of_installments' => $PaymentBreakdown['item_num_of_installments'],
                'total' => 0
            );
            foreach ($Breakdown[$Item->getId()]['payments'] as $k => $pInfo) {
                $Breakdown[$Item->getId()]['total'] += $pInfo;
                if (!isset($Breakdown['installments'][$k])) {
                    $Breakdown['installments'][$k] = 0;
                }
                $Breakdown['installments'][$k] += $pInfo;
            }
        }

        foreach ($Breakdown as $k => $v) {
            if ($k == 'installments') {
                foreach ($v as $InstallmentNumber => $InstallmentPayment) {
                    $Breakdown['taxes'][$InstallmentNumber] = Itweb_Installments_Helper_Data::estimateTax($InstallmentPayment, $TaxInfo);
                }
                break;
            }
        }

        if ($Total->getId() && count($Breakdown['installments'])) {
            $_newCalculation = Mage::getSingleton('installments/calculation');
            $_newCalculation->setData($_searchKey, $Total->getId());
            $_newCalculation->setInstallmentsSerialize(serialize($Breakdown));
            try {
                $_newCalculation->save();
            } catch (Exception $_e) {
                Mage::log($_e->getMessage(), null, 'installment.log');
            }
        }
        return $Breakdown;
    }

    /**
     * Recalculate installments by user input at the time amount
     * @param array $_params
     *
     * @return array|bool|mixed
     */
    public static function recalculateInstallment($_params = array())
    {
        /** Check quote for customer session (it can be different) */
        $_isDefault = (bool)$_params['is_default'];
        if (!isset($_params['quote_id'])) return false;
        $_item = Mage::getModel('sales/quote')->loadByIdWithoutStore($_params['quote_id']);
        if (!$_item->getId()) return false;
        $_shippingCost = $_item->getShippingAddress()->getShippingAmount() + $_item->getBillingAddress()->getShippingAmount();

        /** @var $Calc Mage_Tax_Model_Calculation */
        $_calc = Mage::getSingleton('tax/calculation');
        $_taxRequest = $_calc->getRateRequest(
            $_item->getShippingAddress(),
            $_item->getBillingAddress(),
            $_item->getCustomerTaxClassId(),
            $_item->getStore()
        );
        $_taxInfo = $_calc->getAppliedRates($_taxRequest);

        if ($_item->getUseInstallments() == Itweb_Installments_Helper_Data::PAY_ALL) {
            self::setMaxInstallments(1);
        }

        $_calculation = self::getCalculationByObject($_item);
        $_breakdown = array(
            'shipping_cost' => $_shippingCost,
            'is_custom' => true,
            'num_of_installments' => 'custom',
            'discounts' => self::figureDiscounts($_item),
            'taxes' => array(),
            'installments' => array()
        );
        $_cartItems = $_item->getAllItems();
        usort($_cartItems, 'self::sortItemsByPrice');
        foreach ($_cartItems as $_itemEl) {
            if ($_itemEl->getParentItemId() > 0) {
                continue;
            }
            $_breakdown['items'][$_itemEl->getId()] = array(
                'price' => $_itemEl->getPrice(),
                'qtyOrdered' => self::getQtyByItem($_itemEl)
            );

            if ($_isDefault) {
                $_paymentBreakdown = self::getPaymentsBreakdown($_itemEl);
            } else {
                $_paymentBreakdown = self::getPaymentsBreakdown($_itemEl, $_params['installment'], (int)$_item->getItemsQty(), $_item->getSubtotal());
            }
            $_breakdown[$_itemEl->getId()] = array(
                'increments' => $_paymentBreakdown['increments'],
                'payments' => $_paymentBreakdown['payments'],
                'item_num_of_installments' => $_paymentBreakdown['item_num_of_installments'],
                'total' => 0
            );
            foreach ($_breakdown[$_itemEl->getId()]['payments'] as $_k => $_pInfo) {
                $_breakdown[$_itemEl->getId()]['total'] += $_pInfo;
                if (!isset($_breakdown['installments'][$_k])) {
                    $_breakdown['installments'][$_k] = 0;
                }
                $_breakdown['installments'][$_k] += $_pInfo;
            }
        }

        foreach ($_breakdown as $_k => $_v) {
            if ($_k == 'installments') {
                foreach ($_v as $_installmentNumber => $_installmentPayment) {
                    $_breakdown['taxes'][$_installmentNumber] = Itweb_Installments_Helper_Data::estimateTax($_installmentPayment, $_taxInfo);
                }
                break;
            }
        }
        if ($_calculation->getId()) {
            $_newCalculation = $_calculation;
        } else {
            $_newCalculation = Mage::getSingleton('installments/calculation');
            $_newCalculation->load($_item->getId(), 'quote_id');
        }
        $_newCalculation->setInstallmentsSerialize(serialize($_breakdown));
        try {
            $_newCalculation->save();
        } catch (Exception $_e) {
            Mage::log($_e->getMessage(), null, 'installment.log');
        }
        return $_breakdown;
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
        /** @var $Installment Itweb_Installments_Model_Installments */
        $Installment = Mage::getModel('installments/installments');
        /*->getResourceCollection();*/
        if (is_object($Order)) {
            $Return = $Installment->load($Order->getId(), 'order_id');
            $Return->setOrder($Order);
        } else {
            $Return = $Installment->load($Order, 'order_id');
            $Return->setOrder(Mage::getModel('sales/order')->load($Order));
        }

        /*$Return = $Installment->getFirstItem();
        if ($Return) {
            $Return->load($Return->getId());
            if (is_object($Order)) {
                $Return->setOrder($Order);
            } else {
                $Return->setOrder(Mage::getModel('sales/order')->load($Order));
            }
        }*/
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
        if (is_object($Invoice)) {
            $PaymentsInvoices->addFilter('invoice_id', $Invoice->getId());
        } else {
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
     * @param int $orderId
     * @param array $PaymentInfo
     * @param null|array $ToPay
     */
    public static function makePayment($orderId, $PaymentInfo, $ToPay = null)
    {
        $InstallmentAgreement = self::getOrderInstallment($orderId);
        $InstallmentAgreement->loadOrder();

        $Order = $InstallmentAgreement->getOrder();

        if (!$ToPay) {
            $ToPay = array();
            foreach ($InstallmentAgreement->getPayments() as $_payment) {
                if ($_payment->isPaid() === false) {
                    $ToPay[] = $_payment->getId();
                    break;
                }
            }
        } else {
            foreach ($InstallmentAgreement->getPayments() as $_payment) {
                if ($_payment->isPaid() === false) {
                    if (in_array($_payment->getId(), $ToPay)) {
                        /*TODO REMOVE!!!! This code is wrong because it don't do never new*/
                        $key = array_search($_payment->getId(), $ToPay);

                        $ToPay[$key] = $_payment->getId();
                    }
                }
            }
        }

        $toPayKey = 0;
        foreach ($InstallmentAgreement->getPayments() as $k => $_payment) {
            if ($_payment->isPaid() === true) {
                continue;
            }
            if (isset($ToPay[$toPayKey]) && $ToPay[$toPayKey] != $_payment->getId()) {
                if (in_array($_payment->getId(), $ToPay)) {
                    Mage::throwException('Payments must be made sequentially' . "\n\n" . 'ex. Cannot pay only installment #3 without also paying #2');
                }
            }
            $toPayKey++;
        }

        foreach ($ToPay as $k => $v) {
            /** @var $PaymentMethod Mage_Sales_Model_Order_Payment */
            $PaymentMethod = Mage::getModel('sales/order_payment');
            $PaymentMethod->setMethod($PaymentInfo['method']);
            $PaymentMethod->getMethodInstance()->assignData($PaymentInfo);
            $Order->addPayment($PaymentMethod);

            try {
                if ($InstallmentAgreement->getState() == Itweb_Installments_Model_Installments::STATUS_OPEN_MANUAL || $InstallmentAgreement->getState() == Itweb_Installments_Model_Installments::STATUS_OPEN) {
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
                            $invoiceData['items'][$item->getOrderItemId()] = $item->getQty() * 1;
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
                        if (!empty($invoiceData['do_shipment']) || (int)$invoice->getOrder()->getForcedDoShipmentWithInvoice()) {
                            $shipment = self::_prepareShipment($invoice, $invoiceData['items']);
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

                    $transaction = Mage::getModel('sales/order_payment_transaction')
                        ->setTxnId($PaymentMethod->getTransactionId())
                        ->setOrderPaymentObject($PaymentMethod)
                        ->setTxnType('capture')
                        ->isFailsafe(false);

                    //$PaymentMethod->setLastTransId($transactionId);
                    $PaymentMethod->setCreatedTransaction($transaction);
                    $Order->addRelatedObject($transaction);
                    $Order->addRelatedObject($InstallmentAgreement);
                } else {
                    $PaymentMethod->place();
                }
                $PaymentMethod->getMethodInstance()->prepareSave();
            } catch (Exception $e) {
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
        foreach ($Collection as $Order) {
            /** @var $Order Mage_Sales_Model_Order */
            $Agreement = self::getOrderInstallment($Order);
            if ($Agreement->getId() > 0) {
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
	 