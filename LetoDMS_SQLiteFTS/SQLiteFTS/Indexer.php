<?php
/**
 * Implementation of SQLiteFTS index
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
 * Class for managing a SQLiteFTS index.
 *
 * @category   DMS
 * @package    LetoDMS_Lucene
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2011, Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_SQLiteFTS_Indexer {
	/**
	 * @var object $index sqlite index
	 * @access protected
	 */
	protected $_conn;

	/**
	 * Constructor
	 *
	 */
	function __construct($indexerDir) { /* {{{ */
		$this->_conn = new PDO('sqlite:'.$indexerDir.'/index.db');
	} /* }}} */

	/**
	 * Open an existing index
	 *
	 * @param string $indexerDir directory on disk containing the index
	 */
	static function open($indexerDir) { /* {{{ */
		if(file_exists($indexerDir.'/index.db')) {
			return new LetoDMS_SQLiteFTS_Indexer($indexerDir);
		} else
			return self::create($indexerDir);
	} /* }}} */

	/**
	 * Create a new index
	 *
	 * @param string $indexerDir directory on disk containing the index
	 */
	static function create($indexerDir) { /* {{{ */
		if(file_exists($indexerDir.'/index.db'))
			unlink($indexerDir.'/index.db');
		$index =  new LetoDMS_SQLiteFTS_Indexer($indexerDir);
		/* Make sure the sequence of fields is identical to the field list
		 * in LetoDMS_SQLiteFTS_Term
		 */
		$version = SQLite3::version();
		if($version['versionNumber'] >= 3008000)
			$sql = 'CREATE VIRTUAL TABLE docs USING fts4(title, comment, keywords, category, mimetype, origfilename, owner, content, created, notindexed=created, matchinfo=fts3)';
		else
			$sql = 'CREATE VIRTUAL TABLE docs USING fts4(title, comment, keywords, category, mimetype, origfilename, owner, content, created, matchinfo=fts3)';
		$res = $index->_conn->exec($sql);
		if($res === false) {
			return null;
		}
		$sql = 'CREATE VIRTUAL TABLE docs_terms USING fts4aux(docs);';
		$res = $index->_conn->exec($sql);
		if($res === false) {
			return null;
		}
		return($index);
	} /* }}} */

	/**
	 * Do some initialization
	 *
	 */
	static function init($stopWordsFile='') { /* {{{ */
	} /* }}} */

	/**
	 * Add document to index
	 *
	 * @param object $doc indexed document of class 
	 * LetoDMS_SQLiteFTS_IndexedDocument
	 * @return boolean false in case of an error, otherwise true
	 */
	function addDocument($doc) { /* {{{ */
		if(!$this->_conn)
			return false;

		$sql = "INSERT INTO docs (docid, title, comment, keywords, category, owner, content, mimetype, origfilename, created) VALUES(".$doc->getFieldValue('document_id').", ".$this->_conn->quote($doc->getFieldValue('title')).", ".$this->_conn->quote($doc->getFieldValue('comment')).", ".$this->_conn->quote($doc->getFieldValue('keywords')).", ".$this->_conn->quote($doc->getFieldValue('category')).", ".$this->_conn->quote($doc->getFieldValue('owner')).", ".$this->_conn->quote($doc->getFieldValue('content')).", ".$this->_conn->quote($doc->getFieldValue('mimetype')).", ".$this->_conn->quote($doc->getFieldValue('origfilename')).", ".time().")";
		$res = $this->_conn->exec($sql);
		if($res === false) {
			var_dump($this->_conn->errorInfo());
		}
		return $res;
	} /* }}} */

	/**
	 * Remove document from index
	 *
	 * @param object $doc indexed document of class 
	 * LetoDMS_SQLiteFTS_IndexedDocument
	 * @return boolean false in case of an error, otherwise true
	 */
	public function delete($id) { /* {{{ */
		if(!$this->_conn)
			return false;

		$sql = "DELETE FROM docs WHERE docid=".(int) $id;
		$res = $this->_conn->exec($sql);
		return $res;
	} /* }}} */

	/**
	 * Check if document was deleted
	 *
	 * Just for compatibility with lucene.
	 *
	 * @return boolean always false
	 */
	public function isDeleted($id) { /* {{{ */
		return false;
	} /* }}} */

	/**
	 * Find documents in index
	 *
	 * @param object $doc indexed document of class 
	 * LetoDMS_SQLiteFTS_IndexedDocument
	 * @return boolean false in case of an error, otherwise true
	 */
	public function find($query) { /* {{{ */
		if(!$this->_conn)
			return false;

		$sql = "SELECT docid FROM docs WHERE docs MATCH ".$this->_conn->quote($query);
		$res = $this->_conn->query($sql);
		$hits = array();
		if($res) {
			foreach($res as $rec) {
				$hit = new LetoDMS_SQLiteFTS_QueryHit($this);
				$hit->id = $rec['docid'];
				$hits[] = $hit;
			}
		}
		return $hits;
	} /* }}} */

	/**
	 * Get a single document from index
	 *
	 * @param integer $id id of document
	 * @return boolean false in case of an error, otherwise true
	 */
	public function findById($id) { /* {{{ */
		if(!$this->_conn)
			return false;

		$sql = "SELECT docid FROM docs WHERE docid=".(int) $id;
		$res = $this->_conn->query($sql);
		$hits = array();
		if($res) {
			while($rec = $res->fetch(PDO::FETCH_ASSOC)) {
				$hit = new LetoDMS_SQLiteFTS_QueryHit($this);
				$hit->id = $rec['docid'];
				$hits[] = $hit;
			}
		}
		return $hits;
	} /* }}} */

	/**
	 * Get a single document from index
	 *
	 * @param integer $id id of document
	 * @return boolean false in case of an error, otherwise true
	 */
	public function getDocument($id) { /* {{{ */
		if(!$this->_conn)
			return false;

		$sql = "SELECT title, comment, owner, keywords, category, mimetype, origfilename, created FROM docs WHERE docid=".(int) $id;
		$res = $this->_conn->query($sql);
		$doc = false;
		if($res) {
			$rec = $res->fetch(PDO::FETCH_ASSOC);
			$doc = new LetoDMS_SQLiteFTS_Document();
			$doc->addField('title', $rec['title']);
			$doc->addField('comment', $rec['comment']);
			$doc->addField('keywords', $rec['keywords']);
			$doc->addField('category', $rec['category']);
			$doc->addField('mimetype', $rec['mimetype']);
			$doc->addField('origfilename', $rec['origfilename']);
			$doc->addField('owner', $rec['owner']);
			$doc->addField('created', $rec['created']);
		}
		return $doc;
	} /* }}} */

	/**
	 * Return list of terms in index
	 *
	 * This function does nothing!
	 */
	public function terms() { /* {{{ */
		if(!$this->_conn)
			return false;

		$sql = "SELECT term, col, occurrences FROM docs_terms WHERE col!='*' ORDER BY col";
		$res = $this->_conn->query($sql);
		$terms = array();
		if($res) {
			while($rec = $res->fetch(PDO::FETCH_ASSOC)) {
				$term = new LetoDMS_SQLiteFTS_Term($rec['term'], $rec['col'], $rec['occurrences']);
				$terms[] = $term;
			}
		}
		return $terms;
	} /* }}} */

	/**
	 * Return list of documents in index
	 *
	 */
	public function count() { /* {{{ */
		$sql = "SELECT count(*) c FROM docs";
		$res = $this->_conn->query($sql);
		if($res) {
			$rec = $res->fetch(PDO::FETCH_ASSOC);
			return $rec['c'];
		}
		return 0;
	} /* }}} */

	/**
	 * Commit changes
	 *
	 * This function does nothing!
	 */
	function commit() { /* {{{ */
	} /* }}} */

	/**
	 * Optimize index
	 *
	 * This function does nothing!
	 */
	function optimize() { /* {{{ */
	} /* }}} */
}
?>
