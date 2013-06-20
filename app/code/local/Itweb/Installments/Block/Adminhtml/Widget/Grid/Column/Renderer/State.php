<?php
class Itweb_Installments_Block_Adminhtml_Widget_Grid_Column_Renderer_State
	extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
	/**
	 * Format variables pattern
	 *
	 * @var string
	 */
	protected $_variablePattern = '/([0-9]+)/i';

	/**
	 * Renders grid column
	 *
	 * @param Varien_Object $row
	 * @return mixed
	 */
	public function _getValue(Varien_Object $row)
	{
		$format = ( $this->getColumn()->getFormat() ) ? $this->getColumn()->getFormat() : null;
		$defaultValue = $this->getColumn()->getDefault();
		if (is_null($format)) {
			// If no format and it column not filtered specified return data as is.
			$data = parent::_getValue($row);
			$string = is_null($data) ? $defaultValue : $data;
			return $this->escapeHtml(Itweb_Installments_Helper_Data::getAgreementStateName($string));
		}
		elseif (preg_match_all($this->_variablePattern, $format, $matches)) {
			// Parsing of format string
			$formattedString = $format;
			foreach ($matches[0] as $matchIndex=>$match) {
				$value = $row->getData($matches[1][$matchIndex]);
				$formattedString = str_replace($match, $value, $formattedString);
			}
			return Itweb_Installments_Helper_Data::getAgreementStateName($formattedString);
		} else {
			return $this->escapeHtml(Itweb_Installments_Helper_Data::getAgreementStateName($format));
		}
	}
}
