<?php
/**
 * Implementation of EditDocument controller
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
 * Class which does the busines logic for editing a document
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2013 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Controller_EditDocument extends LetoDMS_Controller_Common {

	public function run() {
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$settings = $this->params['settings'];
		$document = $this->params['document'];
		$name = $this->params['name'];

		if(false === $this->callHook('preEditDocument')) {
			if(empty($this->errormsg))
				$this->errormsg = 'hook_preEditDocument_failed';
			return null;
		}

		$result = $this->callHook('editDocument', $document);
		if($result === null) {
			$name = $this->params['name'];
			$oldname = $document->getName();
			if($oldname != $name)
				if(!$document->setName($name))
					return false;

			$comment = $this->params['comment'];
			if(($oldcomment = $document->getComment()) != $comment)
				if(!$document->setComment($comment))
					return false;

			$expires = $this->params['expires'];
			$oldexpires = $document->getExpires();
			if ($expires != $oldexpires) {
				if(!$this->callHook('preSetExpires', $document, $expires)) {
				}

				if(!$document->setExpires($expires)) {
					return false;
				}

				$document->verifyLastestContentExpriry();

				if(!$this->callHook('postSetExpires', $document, $expires)) {
				}
			}

			$keywords = $this->params['keywords'];
			$oldkeywords = $document->getKeywords();
			if ($oldkeywords != $keywords) {
				if(!$this->callHook('preSetKeywords', $document, $keywords, $oldkeywords)) {
				}

				if(!$document->setKeywords($keywords)) {
					return false;
				}

				if(!$this->callHook('postSetKeywords', $document, $keywords, $oldkeywords)) {
				}
			}

			$categories = $this->params['categories'];
			$oldcategories = $document->getCategories();
			if($categories) {
				$categoriesarr = array();
				foreach($categories as $catid) {
					if($cat = $dms->getDocumentCategory($catid)) {
						$categoriesarr[] = $cat;
					}
					
				}
				$oldcatsids = array();
				foreach($oldcategories as $oldcategory)
					$oldcatsids[] = $oldcategory->getID();

				if (count($categoriesarr) != count($oldcategories) ||
						array_diff($categories, $oldcatsids)) {
					if(!$this->callHook('preSetCategories', $document, $categoriesarr, $oldcategories)) {
					}
					if(!$document->setCategories($categoriesarr)) {
						return false;
					}
					if(!$this->callHook('postSetCategories', $document, $categoriesarr, $oldcategories)) {
					}
				}
			} elseif($oldcategories) {
				if(!$this->callHook('preSetCategories', $document, array(), $oldcategories)) {
				}
				if(!$document->setCategories(array())) {
					return false;
				}
				if(!$this->callHook('postSetCategories', $document, array(), $oldcategories)) {
				}
			}

			$attributes = $this->params['attributes'];
			$oldattributes = $document->getAttributes();
			if($attributes) {
				foreach($attributes as $attrdefid=>$attribute) {
					$attrdef = $dms->getAttributeDefinition($attrdefid);
					if($attribute) {
						if(!$attrdef->validate($attribute)) {
							$this->errormsg	= getAttributeValidationText($attrdef->getValidationError(), $attrdef->getName(), $attribute);
							return false;
						}

						if(!isset($oldattributes[$attrdefid]) || $attribute != $oldattributes[$attrdefid]->getValue()) {
							if(!$document->setAttributeValue($dms->getAttributeDefinition($attrdefid), $attribute))
								return false;
						}
					} elseif($attrdef->getMinValues() > 0) {
						$this->errormsg = getMLText("attr_min_values", array("attrname"=>$attrdef->getName()));
					} elseif(isset($oldattributes[$attrdefid])) {
						if(!$document->removeAttribute($dms->getAttributeDefinition($attrdefid)))
							return false;
					}
				}
			}
			foreach($oldattributes as $attrdefid=>$oldattribute) {
				if(!isset($attributes[$attrdefid])) {
					if(!$document->removeAttribute($dms->getAttributeDefinition($attrdefid)))
						return false;
				}
			}

			$sequence = $this->params['sequence'];
			if(strcasecmp($sequence, "keep")) {
				if($document->setSequence($sequence)) {
				} else {
					return false;
				}
			}

			if(!$this->callHook('postEditDocument')) {
			}

		} else
			return $result;

		return true;
	}
}
