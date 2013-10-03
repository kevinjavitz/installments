<?php
/** @var $_installer Mage_Sales_Model_Mysql4_Setup */
$_installer = $this;
$_installer->startSetup();
$_statusTable = $_installer->getTable('sales/order_status');
$_statusStateTable = $_installer->getTable('sales/order_status_state');

$_statuses = Mage::getConfig()->getNode('global/sales/order/statuses')->asArray();
$_availableStatusesCollection = Mage::getResourceModel('sales/order_status_collection')->addFieldToSelect('*');
$_availableStatusesAr = array();
foreach ($_availableStatusesCollection as $_availableStatuses) {
    $_availableStatusesAr[] = $_availableStatuses->getStatus();
}
$_availableStatusesAr = array_flip($_availableStatusesAr);
$_statusForDb = array();
$_data = array();
foreach ($_statuses as $_code => $_info) {
    if (array_key_exists($_code, $_availableStatusesAr)) continue;
    $_statusForDb[] = $_code;
    $_data[] = array(
        'status' => $_code,
        'label' => $_info['label']
    );
}
if (count($_data)) $_installer->getConnection()->insertArray($_statusTable, array('status', 'label'), $_data);
$_statusForDb = array_flip($_statusForDb);
$_states = Mage::getConfig()->getNode('global/sales/order/states')->asArray();
$_data = array();
foreach ($_states as $_code => $_info) {
    if (!array_key_exists($_code, $_statusForDb)) continue;
    if (isset($_info['statuses'])) {
        foreach ($_info['statuses'] as $_status => $_statusInfo) {
            $_data[] = array(
                'status' => $_status,
                'state' => $_code,
                'is_default' => is_array($_statusInfo) && isset($_statusInfo['@']['default']) ? 1 : 0
            );
        }
    }
}
if (count($_data)) $_installer->getConnection()->insertArray($_statusStateTable, array('status', 'state', 'is_default'), $_data);
$_installer->endSetup();