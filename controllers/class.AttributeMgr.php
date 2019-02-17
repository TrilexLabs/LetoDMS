<?php
/**
 * Implementation of Attribute Definition manager controller
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2013 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Class which does the busines logic for attribute definition manager
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2013 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Controller_AttributeMgr extends LetoDMS_Controller_Common {

	public function run() { /* {{{ */
	} /* }}} */

	public function addattrdef() { /* {{{ */
		$dms = $this->params['dms'];
		$name = $this->params['name'];
		$type = $this->params['type'];
		$objtype = $this->params['objtype'];
		$multiple = $this->params['multiple'];
		$minvalues = $this->params['minvalues'];
		$maxvalues = $this->params['maxvalues'];
		$valueset = $this->params['valueset'];
		$regex = $this->params['regex'];

		return($dms->addAttributeDefinition($name, $objtype, $type, $multiple, $minvalues, $maxvalues, $valueset, $regex));
	} /* }}} */

	public function removeattrdef() { /* {{{ */
		$attrdef = $this->params['attrdef'];
		return $attrdef->remove();
	} /* }}} */

	public function editattrdef() { /* {{{ */
		$dms = $this->params['dms'];
		$name = $this->params['name'];
		$attrdef = $this->params['attrdef'];
		$type = $this->params['type'];
		$objtype = $this->params['objtype'];
		$multiple = $this->params['multiple'];
		$minvalues = $this->params['minvalues'];
		$maxvalues = $this->params['maxvalues'];
		$valueset = $this->params['valueset'];
		$regex = $this->params['regex'];

		if (!$attrdef->setName($name)) {
			return false;
		}
		if (!$attrdef->setType($type)) {
			return false;
		}
		if (!$attrdef->setObjType($objtype)) {
			return false;
		}
		if (!$attrdef->setMultipleValues($multiple)) {
			return false;
		}
		if (!$attrdef->setMinValues($minvalues)) {
			return false;
		}
		if (!$attrdef->setMaxValues($maxvalues)) {
			return false;
		}
		if (!$attrdef->setValueSet($valueset)) {
			return false;
		}
		if (!$attrdef->setRegex($regex)) {
			return false;
		}

		return true;
	} /* }}} */

	public function removeattrvalue() { /* {{{ */
		$attrdef = $this->params['attrdef'];
		$attrval = $this->params['attrval'];
		//$attrdef->getObjects($attrval);
		return $attrdef->removeValue($attrval);
	} /* }}} */
}

