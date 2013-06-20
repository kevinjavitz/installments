<?php
class Itweb_Installments_Block_Adminhtml_Grid
	extends Mage_Adminhtml_Block_Widget_Grid_Container
{

	//protected $_addButtonLabel = 'Add New Example';

	public function __construct()
	{
		parent::__construct();
		$this->_controller = 'adminhtml_installmentsbackend';
		$this->_blockGroup = 'installments';
		$this->_headerText = Mage::helper('installments')->__('Installments');
		$this->removeButton('add');
	}

	protected function _prepareLayout()
	{
		$this->setChild('grid',
			$this->getLayout()->createBlock($this->_blockGroup . '/' . $this->_controller . '_grid',
				$this->_controller . '.grid')->setSaveParametersInSession(true));
		return parent::_prepareLayout();
	}
}