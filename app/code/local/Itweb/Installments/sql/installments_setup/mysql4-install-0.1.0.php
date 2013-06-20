<?php
/** @var $installer Mage_Sales_Model_Mysql4_Setup */
$installer = $this;
$installer->startSetup();

/**
 * Create the agreement table
 */

$decimalDefaults = array(
	'default' => '0.0000'
);
$table = $installer->getConnection()
	->newTable($installer->getTable('installments/installments'))
	->addColumn('installment_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
	array(
		'unsigned' => true,
		'nullable' => false,
		'primary'  => true,
		'identity' => true,
	))
	->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER)
	->addColumn('state', Varien_Db_Ddl_Table::TYPE_INTEGER)
	->addColumn('num_of_installments', Varien_Db_Ddl_Table::TYPE_INTEGER)
	->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME)
	->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME)
	->addColumn('total_paid', Varien_Db_Ddl_Table::TYPE_DECIMAL, '15,4', $decimalDefaults, 'Total Amount Paid')
	->addColumn('total_canceled', Varien_Db_Ddl_Table::TYPE_DECIMAL, '15,4', $decimalDefaults, 'Total Amount Cancelled')
	->addColumn('total_refunded', Varien_Db_Ddl_Table::TYPE_DECIMAL, '15,4', $decimalDefaults, 'Total Amount Refunded')
	->addColumn('total_due', Varien_Db_Ddl_Table::TYPE_DECIMAL, '15,4', $decimalDefaults, 'Total Amount Due')
	->addColumn('tax_paid', Varien_Db_Ddl_Table::TYPE_DECIMAL, '15,4', $decimalDefaults, 'Total Tax Paid')
	->addColumn('tax_refunded', Varien_Db_Ddl_Table::TYPE_DECIMAL, '15,4', $decimalDefaults, 'Total Tax Refunded')
	->addColumn('shipping_paid', Varien_Db_Ddl_Table::TYPE_DECIMAL, '15,4', $decimalDefaults, 'Total Shipping Paid')
	->addColumn('shipping_refunded', Varien_Db_Ddl_Table::TYPE_DECIMAL, '15,4', $decimalDefaults, 'Total Shipping Refunded')
	->addIndex('installment_id_idx', 'installment_id');

$installer->getConnection()->createTable($table);

/**
 * Create the payment table
 */
$table = $installer->getConnection()
	->newTable($installer->getTable('installments/installmentspayments'))
	->addColumn('installment_payment_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
	array(
		'unsigned' => true,
		'nullable' => false,
		'primary'  => true,
		'identity' => true,
	))
	->addColumn('installment_id', Varien_Db_Ddl_Table::TYPE_INTEGER)
	->addColumn('state', Varien_Db_Ddl_Table::TYPE_INTEGER)
	->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME)
	->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME)
	->addColumn('amount_due', Varien_Db_Ddl_Table::TYPE_DECIMAL, '15,4', $decimalDefaults, 'Payment Amount Due')
	->addColumn('amount_paid', Varien_Db_Ddl_Table::TYPE_DECIMAL, '15,4', $decimalDefaults, 'Payment Amount Paid')
	->addColumn('amount_canceled', Varien_Db_Ddl_Table::TYPE_DECIMAL, '15,4', $decimalDefaults, 'Payment Amount Canceled')
	->addColumn('amount_refunded', Varien_Db_Ddl_Table::TYPE_DECIMAL, '15,4', $decimalDefaults, 'Payment Amount Refunded')
	->addIndex('installment_payment_id_idx', 'installment_payment_id');

$installer->getConnection()->createTable($table);

/**
 * Create the table that holds the invoice to payment relationship
 */
$table = $installer->getConnection()
	->newTable($installer->getTable('installments/installmentspaymentsinvoices'))
	->addColumn('installment_payment_invoice_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
	'unsigned' => true,
	'nullable' => false,
	'primary'  => true,
	'identity' => true,
))
	->addColumn('installment_payment_id', Varien_Db_Ddl_Table::TYPE_INTEGER)
	->addColumn('invoice_id', Varien_Db_Ddl_Table::TYPE_INTEGER)
	->addIndex('installment_payment_invoice_id_idx', 'installment_payment_invoice_id');

$installer->getConnection()->createTable($table);

$installer->addAttribute("quote", "use_installments", array("type" => "int"));
$installer->addAttribute("order", "use_installments", array("type" => "int"));
//demo
//Mage::getModel('core/url_rewrite')->setId(null);
//demo 
$installer->endSetup();
	 