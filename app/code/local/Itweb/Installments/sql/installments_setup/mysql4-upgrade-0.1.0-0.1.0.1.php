<?php
/** @var $_installer Mage_Sales_Model_Mysql4_Setup */
$_installer = $this;
$_installer->startSetup();

/**
 * Create table for saving calculated installments
 * */
$_installer->getConnection()->dropTable($_installer->getTable('installments/installmentscalculation'));
$_table = $_installer->getConnection()
    ->newTable($_installer->getTable('installments/installmentscalculation'))
    ->addColumn('calculation_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array(
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
            'identity' => true,
        ))
    ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array(
            'nullable' => true,
            'default' => null
        ), 'Order ID for Invoice installment')
    ->addColumn('quote_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array(
            'nullable' => true,
            'default' => null
        ), 'Quote ID for Invoice installment')
    ->addColumn('invoice_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array(
            'nullable' => true,
            'default' => null
        ), 'Invoice ID for Invoice installment')
    ->addColumn('installments_serialize', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', array(
        'nullable' => true
    ), 'Serialized installments array')
    ->addIndex('calculation_id_idx', 'calculation_id')
    ->addForeignKey($_installer->getFkName('installments/installmentscalculation', 'order_id', 'sales/order', 'entity_id'), 'order_id', $_installer->getTable('sales/order'), 'entity_id', Varien_Db_Ddl_Table::ACTION_CASCADE)
    ->addForeignKey($_installer->getFkName('installments/installmentscalculation', 'quote_id', 'sales/quote', 'entity_id'), 'quote_id', $_installer->getTable('sales/quote'), 'entity_id', Varien_Db_Ddl_Table::ACTION_CASCADE);
$_installer->getConnection()->createTable($_table);

$_installer->endSetup();