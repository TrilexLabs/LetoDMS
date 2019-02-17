<?php
/**
 * Implementation of a term
 *
 * @category   DMS
 * @package    LetoDMS_SQLiteFTS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010, Uwe Steinmann
 * @version    Release: @package_version@
 */


/**
 * Class for managing a term.
 *
 * @category   DMS
 * @package    LetoDMS_SQLiteFTS
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2011, Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_SQLiteFTS_Term {

	/**
	 * @var string $text
	 * @access public
	 */
	public $text;

	/**
	 * @var string $field
	 * @access public
	 */
	public $field;

	/**
	 * @var integer $occurrence 
	 * @access public
	 */
	public $_occurrence;

	/**
	 *
	 */
	public function __construct($term, $col, $occurrence) { /* {{{ */
		$this->text = $term;
		$fields = array(
			0 => 'title',
			1 => 'comment',
			2 => 'keywords',
			3 => 'category',
			4 => 'mimetype',
			5 => 'origfilename',
			6 => 'owner',
			7 => 'content',
			8 => 'created'
		);
		$this->field = $fields[$col];
		$this->_occurrence = $occurrence;
	} /* }}} */

}
?>
