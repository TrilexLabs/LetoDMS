<?php
/**
 * Implementation of an indexed document
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
 * @uses LetoDMS_SQLiteFTS_Document
 */
require_once('Document.php');


/**
 * Class for managing an indexed document.
 *
 * @category   DMS
 * @package    LetoDMS_SQLiteFTS
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2011, Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_SQLiteFTS_IndexedDocument extends LetoDMS_SQLiteFTS_Document {

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
		$this->addField('document_id', $document->getID());
		if($version) {
			$this->addField('mimetype', $version->getMimeType());
			$this->addField('origfilename', $version->getOriginalFileName());
			if(!$nocontent)
				$this->addField('created', $version->getDate(), 'unindexed');
			if($attributes = $version->getAttributes()) {
				foreach($attributes as $attribute) {
					$attrdef = $attribute->getAttributeDefinition();
					if($attrdef->getValueSet() != '')
						$this->addField('attr_'.str_replace(' ', '_', $attrdef->getName()), $attribute->getValue());
					else
						$this->addField('attr_'.str_replace(' ', '_', $attrdef->getName()), $attribute->getValue());
				}
			}
		}
		$this->addField('title', $document->getName());
		if($categories = $document->getCategories()) {
			$names = array();
			foreach($categories as $cat) {
				$names[] = $cat->getName();
			}
			$this->addField('category', implode(' ', $names));
		}
		if($attributes = $document->getAttributes()) {
			foreach($attributes as $attribute) {
				$attrdef = $attribute->getAttributeDefinition();
				if($attrdef->getValueSet() != '')
					$this->addField('attr_'.str_replace(' ', '_', $attrdef->getName()), $attribute->getValue());
				else
					$this->addField('attr_'.str_replace(' ', '_', $attrdef->getName()), $attribute->getValue());
			}
		}

		$owner = $document->getOwner();
		$this->addField('owner', $owner->getLogin());
		if($keywords = $document->getKeywords()) {
			$this->addField('keywords', $keywords);
		}
		if($comment = $document->getComment()) {
			$this->addField('comment', $comment);
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
						$this->addField('content', $content['stdout'], 'unstored');
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
