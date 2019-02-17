<?php
/**
 * Implementation of EditFolder controller
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
 * Class which does the busines logic for editing a folder
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2013 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Controller_EditFolder extends LetoDMS_Controller_Common {

	public function run() {
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$settings = $this->params['settings'];
		$folder = $this->params['folder'];

		if(false === $this->callHook('preEditFolder')) {
			if(empty($this->errormsg))
				$this->errormsg = 'hook_preEditFolder_failed';
			return null;
		}

		$result = $this->callHook('editFolder', $folder);
		if($result === null) {
			$name = $this->params['name'];
			if(($oldname = $folder->getName()) != $name)
				if(!$folder->setName($name))
					return false;

			$comment = $this->params['comment'];
			if(($oldcomment = $folder->getComment()) != $comment)
				if(!$folder->setComment($comment))
					return false;

			$attributes = $this->params['attributes'];
			$oldattributes = $folder->getAttributes();
			if($attributes) {
				foreach($attributes as $attrdefid=>$attribute) {
					$attrdef = $dms->getAttributeDefinition($attrdefid);
					if($attribute) {
						if(!$attrdef->validate($attribute)) {
							$this->errormsg	= getAttributeValidationText($attrdef->getValidationError(), $attrdef->getName(), $attribute);
							return false;
						}

						if(!isset($oldattributes[$attrdefid]) || $attribute != $oldattributes[$attrdefid]->getValue()) {
							if(!$folder->setAttributeValue($dms->getAttributeDefinition($attrdefid), $attribute))
								return false;
						}
					} elseif($attrdef->getMinValues() > 0) {
						$this->errormsg = getMLText("attr_min_values", array("attrname"=>$attrdef->getName()));
					} elseif(isset($oldattributes[$attrdefid])) {
						if(!$folder->removeAttribute($dms->getAttributeDefinition($attrdefid)))
							return false;
					}
				}
			}
			foreach($oldattributes as $attrdefid=>$oldattribute) {
				if(!isset($attributes[$attrdefid])) {
					if(!$folder->removeAttribute($dms->getAttributeDefinition($attrdefid)))
						return false;
				}
			}

			$sequence = $this->params['sequence'];
			if(strcasecmp($sequence, "keep")) {
				if($folder->setSequence($sequence)) {
				} else {
					return false;
				}
			}

			if(!$this->callHook('postEditFolder')) {
			}

		} else
			return $result;

		return true;
	}
}
