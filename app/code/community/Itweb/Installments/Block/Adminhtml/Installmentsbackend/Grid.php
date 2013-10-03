<?php

class itweb_Installments_Block_Adminhtml_Installmentsbackend_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    public function __construct()
    {
        parent::__construct();
        $this->setId('installments_grid');
        $this->setDefaultSort('id');
        $this->setDefaultDir('desc');
        $this->setSaveParametersInSession(true);
        $this->addExportType('*/*/exportCsv', Mage::helper('installments')->__('CSV'));

        $this->setColumnFilters(array(
            'installment_state' => 'installments/adminhtml_widget_grid_column_filter_state'
        ));

        $this->setColumnRenderers(array(
            'installment_state' => 'installments/adminhtml_widget_grid_column_renderer_state'
        ));
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('installments/installments')
            ->getCollection()
            ->addCustomerName()
            ->addOrderIncrementId();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('id', array(
            'header' => Mage::helper('installments')->__('ID'),
            'align' => 'right',
            'width' => '50px',
            'index' => 'installment_id',
            'type' => 'number'
        ));

        $this->addColumn('order_id', array(
            'header' => Mage::helper('installments')->__('Order ID'),
            'align' => 'left',
            'index' => 'increment_id',
            'type' => 'number'
        ));

        $this->addColumn('customer_first_name', array(
            'header' => Mage::helper('installments')->__('Customer Firstname'),
            'align' => 'left',
            'index' => 'customer_firstname',
        ));

        $this->addColumn('customer_last_name', array(
            'header' => Mage::helper('installments')->__('Customer Lastname'),
            'align' => 'left',
            'index' => 'customer_lastname'
        ));

        $this->addColumn('total_paid', array(
            'header' => Mage::helper('installments')->__('Total Paid'),
            'align' => 'left',
            'index' => 'total_paid',
            'filter_index' => 'main_table.total_paid',
            'type' => 'number'
        ));

        $this->addColumn('total_canceled', array(
            'header' => Mage::helper('installments')->__('Total Cancelled'),
            'align' => 'left',
            'index' => 'total_canceled',
            'filter_index' => 'main_table.total_canceled',
            'type' => 'number'
        ));

        $this->addColumn('total_refunded', array(
            'header' => Mage::helper('installments')->__('Total Refunded'),
            'align' => 'left',
            'index' => 'total_refunded',
            'filter_index' => 'main_table.total_refunded',
            'type' => 'number'
        ));

        $this->addColumn('total_due', array(
            'header' => Mage::helper('installments')->__('Total Due'),
            'align' => 'left',
            'index' => 'total_due',
            'filter_index' => 'main_table.total_due',
            'type' => 'number'
        ));

        $this->addColumn('state', array(
            'header' => Mage::helper('installments')->__('State'),
            'align' => 'left',
            'index' => 'state',
            'filter_index' => 'main_table.state',
            'type' => 'installment_state'
        ));

        $this->addColumn('num_of_installments', array(
            'header' => Mage::helper('installments')->__('Number Of Installments'),
            'align' => 'left',
            'index' => 'num_of_installments',
            'type' => 'number'
        ));

        $this->addColumn('created_at', array(
            'header' => Mage::helper('installments')->__('Created At'),
            'align' => 'left',
            'index' => 'created_at',
            'filter_index' => 'main_table.created_at',
            'type' => 'datetime'
        ));

        $this->addColumn('updated_at', array(
            'header' => Mage::helper('installments')->__('Updated At'),
            'align' => 'left',
            'index' => 'updated_at',
            'filter_index' => 'main_table.created_at',
            'type' => 'datetime'
        ));

        return parent::_prepareColumns();
    }

    public function getRowUrl($row)
    {
        return $this->getUrl('adminhtml/sales_order/view', array('order_id' => $row->getOrderId()));
    }

    private function getBreakdownPaymentMethod()
    {
        return (Mage::getStoreConfigFlag('installments/export/breakdown_payment_method') == 1);
    }

    private function getBreakdownPaymentMethodCards()
    {
        return (Mage::getStoreConfigFlag('installments/export/breakdown_payment_method_card') == 1);
    }

    private function getBreakdownStoreName()
    {
        return (Mage::getStoreConfigFlag('installments/export/breakdown_store_name') == 1);
    }

    private function getCombineCreditCards()
    {
        return (Mage::getStoreConfigFlag('installments/export/breakdown_payment_method_card_combine') == 1);
    }

    private function getExcludedCardTypes()
    {
        $Config = Mage::getStoreConfig('installments/export/breakdown_payment_method_card_exclude');
        return explode(',', $Config);
    }

    public function getCsv()
    {
        $csv = '';
        $this->_isExport = true;
        $this->_prepareGrid();
        $this->getCollection()->getSelect()->limit();
        $this->getCollection()->setPageSize(0);
        $this->getCollection()->load();
        $this->_afterLoadCollection();

        $data = array();
        foreach ($this->_columns as $column) {
            if (!$column->getIsSystem()) {
                $data[] = '"' . $column->getExportHeader() . '"';
            }
        }

        if ($this->getBreakdownPaymentMethod()) {
            $payments = Mage::getSingleton('payment/config')->getActiveMethods();
            $addedCardTypes = array();
            $creditCardHeaderAdded = false;
            foreach ($payments as $Code => $Model) {
                if ($Code == 'paypal_billing_agreement') {
                    continue;
                }
                $Title = Mage::getStoreConfig('payment/' . $Code . '/title');
                $useFallback = true;
                if ($this->getBreakdownPaymentMethodCards()) {
                    if ($Model instanceof Mage_Payment_Model_Method_Cc) {
                        $useFallback = false;
                        foreach (Mage::getSingleton('payment/config')->getCcTypes() as $code => $name) {
                            if (in_array($code, $this->getExcludedCardTypes())) {
                                continue;
                            }
                            if ($this->getCombineCreditCards() === false) {
                                $data[] = '"' . $Title . ' (' . $name . ')"';
                            } else {
                                if (in_array($name, $addedCardTypes) === false) {
                                    $data[] = '"' . $name . '"';
                                    $addedCardTypes[] = $name;
                                }
                            }
                        }
                    }
                } elseif ($this->getCombineCreditCards() === true) {
                    if ($Model instanceof Mage_Payment_Model_Method_Cc) {
                        $useFallback = false;
                        if ($creditCardHeaderAdded === false) {
                            $data[] = '"Credit Cards"';
                            $creditCardHeaderAdded = true;
                        }
                    }
                }

                if ($useFallback === true) {
                    $data[] = '"' . $Code . '"';
                }
            }
        }

        if ($this->getBreakdownStoreName()) {
            foreach (Mage::app()->getStores() as $Store) {
                $data[] = '"' . $Store->getName() . '"';
            }
        }

        $csv .= implode(',', $data) . "\n";

        foreach ($this->getCollection() as $item) {
            $data = array();
            foreach ($this->_columns as $column) {
                if (!$column->getIsSystem()) {
                    $data[] = '"' . str_replace(array('"', '\\'), array('""', '\\\\'),
                            $column->getRowFieldExport($item)) . '"';
                }
            }

            /** @var $item Itweb_Installments_Model_Installments */
            $item->load($item->getId());
            $Order = $item->getOrder();

            if ($this->getBreakdownPaymentMethod()) {
                $PaymentData = array();
                foreach ($Order->getAllPayments() as $OrderPayment) {
                    /** @var $OrderPayment Mage_Sales_Model_Order_Payment */

                    $AmountPaid = $OrderPayment->getAmountPaid();
                    $MethodInst = $OrderPayment->getMethodInstance();
                    $MethodName = $MethodInst->getCode();

                    if (!isset($PaymentData[$MethodName])) {
                        $PaymentData[$MethodName] = array(
                            'inst' => $MethodInst,
                            'cards' => array(),
                            'total' => 0
                        );
                    }

                    if ($MethodInst instanceof Mage_Payment_Model_Method_Cc) {
                        $CardType = $OrderPayment->getCcType();
                        if (in_array($CardType, $this->getExcludedCardTypes())) {
                            continue;
                        }

                        if (!isset($PaymentData[$MethodName]['cards'][$CardType])) {
                            $PaymentData[$MethodName]['cards'][$CardType] = 0;
                        }
                        $PaymentData[$MethodName]['cards'][$CardType] += $AmountPaid;
                    }
                    $PaymentData[$MethodName]['total'] += $AmountPaid;
                }

                $ccAdded = false;
                $addedCardTypes = array();
                foreach ($payments as $MethodName => $MethodInst) {
                    if ($MethodName == 'paypal_billing_agreement') {
                        continue;
                    }
                    $useFallback = true;
                    if ($this->getBreakdownPaymentMethodCards()) {
                        if ($MethodInst instanceof Mage_Payment_Model_Method_Cc) {
                            $useFallback = false;
                            foreach (Mage::getSingleton('payment/config')->getCcTypes() as $code => $name) {
                                if (in_array($code, $this->getExcludedCardTypes())) {
                                    continue;
                                }

                                if ($this->getCombineCreditCards() === false) {
                                    $data[] = '"' . (float)$PaymentData[$MethodName]['cards'][$code] . '"';
                                } else {
                                    if (in_array($name, $addedCardTypes) === false) {
                                        $addedCardTypes[] = $name;
                                        $colVal = 0;
                                        foreach ($PaymentData as $m => $mInfo) {
                                            if (isset($mInfo['cards'][$code])) {
                                                $colVal += $mInfo['cards'][$code];
                                            }
                                        }
                                        $data[] = '"' . $colVal . '"';
                                    }
                                }
                            }
                        }
                    } elseif ($this->getCombineCreditCards() === true) {
                        if ($MethodInst instanceof Mage_Payment_Model_Method_Cc) {
                            $useFallback = false;
                            if ($ccAdded === false) {
                                $colVal = 0;
                                $ccAdded = true;
                                foreach ($PaymentData as $c => $cInfo) {
                                    if ($cInfo['inst'] instanceof Mage_Payment_Model_Method_Cc) {
                                        $colVal += $cInfo['total'];
                                    }
                                }
                                $data[] = '"' . $colVal . '"';
                            }
                        }
                    }

                    if ($useFallback === true) {
                        $data[] = '"' . (float)$PaymentData[$MethodName]['total'] . '"';
                    }
                }
            }

            if ($this->getBreakdownStoreName()) {
                foreach (Mage::app()->getStores() as $Store) {
                    if ($Order->getStore()->getId() == $Store->getId()) {
                        $data[] = '"' . $Order->getTotalPaid() . '"';
                    } else {
                        $data[] = '"0"';
                    }
                }
            }

            $csv .= implode(',', $data) . "\n";
        }

        if ($this->getCountTotals()) {
            $data = array();
            foreach ($this->_columns as $column) {
                if (!$column->getIsSystem()) {
                    $data[] = '"' . str_replace(array('"', '\\'), array('""', '\\\\'),
                            $column->getRowFieldExport($this->getTotals())) . '"';
                }
            }
            $csv .= implode(',', $data) . "\n";
        }

        return $csv;
    }
}