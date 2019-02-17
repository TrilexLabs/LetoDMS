<?php
/**
 * Implementation of search in lucene index
 *
 * @category   DMS
 * @package    LetoDMS_Lucene
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010, Uwe Steinmann
 * @version    Release: @package_version@
 */


/**
 * Class for searching in a lucene index.
 *
 * @category   DMS
 * @package    LetoDMS_Lucene
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2011, Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Lucene_Search {
	/**
	 * @var object $index lucene index
	 * @access protected
	 */
	protected $index;

	/**
	 * Create a new instance of the search
	 *
	 * @param object $index lucene index
	 * @return object instance of LetoDMS_Lucene_Search
	 */
	function __construct($index) { /* {{{ */
		$this->index = $index;
		$this->version = '@package_version@';
		if($this->version[0] == '@')
			$this->version = '3.0.0';
	} /* }}} */

	/**
	 * Get document from index
	 *
	 * @param object $index lucene index
	 * @return object instance of LetoDMS_Lucene_Document of false
	 */
	function getDocument($id) { /* {{{ */
		$hits = $this->index->find('document_id:'.$id);
		return $hits ? $hits[0] : false;
	} /* }}} */

	/**
	 * Search in index
	 *
	 * @param object $index lucene index
	 * @return object instance of LetoDMS_Lucene_Search
	 */
	function search($term, $owner, $status='', $categories=array(), $fields=array()) { /* {{{ */
		$querystr = '';
		if($fields) {
		} else {
			if($term)
				$querystr .= trim($term);
		}
		if($owner) {
			if($querystr)
				$querystr .= ' && ';
			$querystr .= 'owner:'.$owner;
		}
		if($categories) {
			if($querystr)
				$querystr .= ' && ';
			$querystr .= '(category:"';
			$querystr .= implode('" || category:"', $categories);
			$querystr .= '")';
		}
		try {
			$query = Zend_Search_Lucene_Search_QueryParser::parse($querystr);
			try {
				$hits = $this->index->find($query);
				$recs = array();
				foreach($hits as $hit) {
					$recs[] = array('id'=>$hit->id, 'document_id'=>$hit->document_id);
				}
				return $recs;
			} catch (Zend_Search_Lucene_Exception $e) {
				return false;
			}
		} catch (Zend_Search_Lucene_Search_QueryParserException $e) {
			return false;
		}
	} /* }}} */
}
?>
