<?php
/**
 * Implementation of an indexed document
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
 * Class for managing an indexed document.
 *
 * @category   DMS
 * @package    LetoDMS_Lucene
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2011, Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Lucene_IndexedDocument extends Zend_Search_Lucene_Document {

	/**
	 * @var string
	 */
	protected $errormsg;

	/**
	 * @var string
	 */
	protected $mimetype;

	/**
	 * @var string
	 */
	protected $cmd;

	/**
	 * @param $cmd
	 * @param int $timeout
	 * @return string
	 * @throws Exception
	 */
	static function execWithTimeout($cmd, $timeout=2) { /* {{{ */
		$descriptorspec = array(
			0 => array("pipe", "r"),
			1 => array("pipe", "w"),
			2 => array("pipe", "w")
		);
		$pipes = array();

		$timeout += time();
		$process = proc_open($cmd, $descriptorspec, $pipes);
		if (!is_resource($process)) {
			throw new Exception("proc_open failed on: " . $cmd);
		}
		stream_set_blocking($pipes[1], 0);
		stream_set_blocking($pipes[2], 0);
			 
		$output = $error = '';
		$timeleft = $timeout - time();
		$read = array($pipes[1], $pipes[2]);
		$write = NULL;
		$exeptions = NULL;
		do {
			$num_changed_streams = stream_select($read, $write, $exeptions, $timeleft, 200000);

			if ($num_changed_streams === false) {
				proc_terminate($process);
				throw new Exception("stream select failed on: " . $cmd);
			} elseif ($num_changed_streams > 0) {
				$output .= fread($pipes[1], 8192);
				$error .= fread($pipes[2], 8192);
			}
			$timeleft = $timeout - time();
		} while (!feof($pipes[1]) && $timeleft > 0);
 
		if ($timeleft <= 0) {
			proc_terminate($process);
			throw new Exception("command timeout on: " . $cmd);
		} else {
			return array('stdout'=>$output, 'stderr'=>$error);
		}
	} /* }}} */

	/**
	 * Constructor. Creates our indexable document and adds all
	 * necessary fields to it using the passed in document
	 * @param LetoDMS_Core_DMS $dms
	 * @param LetoDMS_Core_Document $document
	 * @param null $convcmd
	 * @param bool $nocontent
	 * @param int $timeout
	 */
	public function __construct($dms, $document, $convcmd=null, $nocontent=false, $timeout=5) { /* {{{ */
		$this->errormsg = '';
		$this->cmd = '';
		$this->mimetype = '';
		$_convcmd = array(
			'application/pdf' => 'pdftotext -enc UTF-8 -nopgbrk %s - |sed -e \'s/ [a-zA-Z0-9.]\{1\} / /g\' -e \'s/[0-9.]//g\'',
			'application/postscript' => 'ps2pdf14 %s - | pdftotext -enc UTF-8 -nopgbrk - - | sed -e \'s/ [a-zA-Z0-9.]\{1\} / /g\' -e \'s/[0-9.]//g\'',
			'application/msword' => 'catdoc %s',
			'application/vnd.ms-excel' => 'ssconvert -T Gnumeric_stf:stf_csv -S %s fd://1',
			'audio/mp3' => "id3 -l -R %s | egrep '(Title|Artist|Album)' | sed 's/^[^:]*: //g'",
			'audio/mpeg' => "id3 -l -R %s | egrep '(Title|Artist|Album)' | sed 's/^[^:]*: //g'",
			'text/plain' => 'cat %s',
		);
		if($convcmd) {
			$_convcmd = $convcmd;
		}

		$version = $document->getLatestContent();
		$this->addField(Zend_Search_Lucene_Field::Keyword('document_id', $document->getID()));
		if($version) {
			$this->addField(Zend_Search_Lucene_Field::Keyword('mimetype', $version->getMimeType()));
			$this->addField(Zend_Search_Lucene_Field::Keyword('origfilename', $version->getOriginalFileName(), 'utf-8'));
			if(!$nocontent)
				$this->addField(Zend_Search_Lucene_Field::UnIndexed('created', $version->getDate()));
			if($attributes = $version->getAttributes()) {
				foreach($attributes as $attribute) {
					$attrdef = $attribute->getAttributeDefinition();
					if($attrdef->getValueSet() != '')
						$this->addField(Zend_Search_Lucene_Field::Keyword('attr_'.str_replace(' ', '_', $attrdef->getName()), $attribute->getValue(), 'utf-8'));
					else
						$this->addField(Zend_Search_Lucene_Field::Text('attr_'.str_replace(' ', '_', $attrdef->getName()), $attribute->getValue(), 'utf-8'));
				}
			}
		}
		$this->addField(Zend_Search_Lucene_Field::Text('title', $document->getName(), 'utf-8'));
		if($categories = $document->getCategories()) {
			$names = array();
			foreach($categories as $cat) {
				$names[] = $cat->getName();
			}
			$this->addField(Zend_Search_Lucene_Field::Text('category', implode(' ', $names), 'utf-8'));
		}
		if($attributes = $document->getAttributes()) {
			foreach($attributes as $attribute) {
				$attrdef = $attribute->getAttributeDefinition();
				if($attrdef->getValueSet() != '')
					$this->addField(Zend_Search_Lucene_Field::Keyword('attr_'.str_replace(' ', '_', $attrdef->getName()), $attribute->getValue(), 'utf-8'));
				else
					$this->addField(Zend_Search_Lucene_Field::Text('attr_'.str_replace(' ', '_', $attrdef->getName()), $attribute->getValue(), 'utf-8'));
			}
		}

		$owner = $document->getOwner();
		$this->addField(Zend_Search_Lucene_Field::Text('owner', $owner->getLogin(), 'utf-8'));
		if($keywords = $document->getKeywords()) {
			$this->addField(Zend_Search_Lucene_Field::Text('keywords', $keywords, 'utf-8'));
		}
		if($comment = $document->getComment()) {
			$this->addField(Zend_Search_Lucene_Field::Text('comment', $comment, 'utf-8'));
		}
		if($version && !$nocontent) {
			$path = $dms->contentDir . $version->getPath();
			$content = '';
			$mimetype = $version->getMimeType();
			$this->mimetype = $mimetype;
			$cmd = '';
			$mimeparts = explode('/', $mimetype, 2);
			if(isset($_convcmd[$mimetype])) {
				$cmd = sprintf($_convcmd[$mimetype], $path);
			} elseif(isset($_convcmd[$mimeparts[0].'/*'])) {
				$cmd = sprintf($_convcmd[$mimetype], $path);
			} elseif(isset($_convcmd['*'])) {
				$cmd = sprintf($_convcmd[$mimetype], $path);
			}
			if($cmd) {
				$this->cmd = $cmd;
				try {
					$content = self::execWithTimeout($cmd, $timeout);
					if($content['stdout']) {
						$this->addField(Zend_Search_Lucene_Field::UnStored('content', $content['stdout'], 'utf-8'));
					}
					if($content['stderr']) {
						$this->errormsg = $content['stderr'];
					}
				} catch (Exception $e) {
				}
			}
		} 
	} /* }}} */

	public function getErrorMsg() { /* {{{ */
		return $this->errormsg;
	} /* }}} */

	public function getMimeType() { /* {{{ */
		return $this->mimetype;
	} /* }}} */

	public function getCmd() { /* {{{ */
		return $this->cmd;
	} /* }}} */
}
?>
