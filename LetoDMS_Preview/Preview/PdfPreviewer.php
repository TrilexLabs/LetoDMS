<?php
/**
 * Implementation of pdf preview documents
 *
 * @category   DMS
 * @package    LetoDMS_Preview
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010, Uwe Steinmann
 * @version    Release: @package_version@
 */


/**
 * Class for managing creation of pdf preview for documents.
 *
 * @category   DMS
 * @package    LetoDMS_Preview
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2011, Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Preview_PdfPreviewer extends LetoDMS_Preview_Base {

	function __construct($previewDir, $timeout=5) { /* {{{ */
		parent::__construct($previewDir, $timeout);
		$this->converters = array(
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => "unoconv -d document -f pdf --stdout -v '%f' > '%o'",
			'application/vnd.oasis.opendocument.text' => "unoconv -d document -f pdf --stdout -v '%f' > '%o'",
			'text/rtf' => "unoconv -d document -f pdf --stdout -v '%f' > '%o'",
			'application/msword' => "unoconv -d document -f pdf --stdout -v '%f' > '%o'",
			'application/vnd.ms-excel' => "unoconv -d document -f pdf --stdout -v '%f' > '%o'",
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => "unoconv -d document -f pdf --stdout -v '%f' > '%o'",
			'text/plain' => "unoconv -d document -f pdf --stdout -v '%f' > '%o'",
			'application/postscript' => "ps2pdf '%f' - > '%o'",
			'image/jpeg' => "convert '%f' pdf:- > '%o'",
			'image/png' => "convert '%f' pdf:- > '%o'",
			'image/gif' => "convert '%f' pdf:- > '%o'",
			'video/mp4' => "convert '%f[1-20]' pdf:- > '%o'",
		);
	} /* }}} */

	/**
	 * Return the physical filename of the preview image on disk
	 *
	 * @param object $object document content or document file
	 * @return string file name of preview image
	 */
	protected function getFileName($object) { /* {{{ */
		if(!$object)
			return false;

		$document = $object->getDocument();
		$dir = $this->previewDir.'/'.$document->getDir();
		switch(get_class($object)) {
			case "LetoDMS_Core_DocumentContent":
				$target = $dir.'p'.$object->getVersion();
				break;
			case "LetoDMS_Core_DocumentFile":
				$target = $dir.'f'.$object->getID();
				break;
			default:
				return false;
		}
		return $target;
	} /* }}} */

	/**
	 * Create a pdf preview for a given file
	 *
	 * This method creates a preview in pdf format for a regular file
	 * in the file system and stores the result in the directory $dir relative
	 * to the configured preview directory. The filename of the resulting preview
	 * image is either $target.pdf (if set) or md5($infile).pdf.
	 * The $mimetype is used to select the propper conversion programm.
	 * An already existing pdf preview is replaced.
	 *
	 * @param string $infile name of input file including full path
	 * @param string $dir directory relative to $this->previewDir
	 * @param string $mimetype MimeType of input file
	 * @param string $target optional name of preview image (without extension)
	 * @return boolean true on success, false on failure
	 */
	public function createRawPreview($infile, $dir, $mimetype, $target='') { /* {{{ */
		if(!$this->previewDir)
			return false;
		if(!is_dir($this->previewDir.'/'.$dir)) {
			if (!LetoDMS_Core_File::makeDir($this->previewDir.'/'.$dir)) {
				return false;
			}
		}
		if(!file_exists($infile))
			return false;
		if(!$target)
			$target = $this->previewDir.$dir.md5($infile);
		if($target != '' && (!file_exists($target.'.pdf') || filectime($target.'.pdf') < filectime($infile))) {
			$cmd = '';
			$mimeparts = explode('/', $mimetype, 2);
			if(isset($this->converters[$mimetype])) {
				$cmd = str_replace(array('%f', '%o', '%m'), array($infile, $target.'.pdf', $mimetype), $this->converters[$mimetype]);
			} elseif(isset($this->converters[$mimeparts[0].'/*'])) {
				$cmd = str_replace(array('%f', '%o', '%m'), array($infile, $target.'.pdf', $mimetype), $this->converters[$mimeparts[0].'/*']);
			} elseif(isset($this->converters['*'])) {
				$cmd = str_replace(array('%f', '%o', '%m'), array($infile, $target.'.pdf', $mimetype), $this->converters['*']);
			}
			if($cmd) {
				try {
					self::execWithTimeout($cmd, $this->timeout);
				} catch(Exception $e) {
					return false;
				}
			}
			return true;
		}
		return true;
			
	} /* }}} */

	/**
	 * Create preview image
	 *
	 * This function creates a preview image for the given document
	 * content or document file. It internally uses
	 * {@link LetoDMS_Preview::createRawPreview()}. The filename of the
	 * preview image is created by {@link LetoDMS_Preview_Previewer::getFileName()}
	 *
	 * @param object $object instance of LetoDMS_Core_DocumentContent
	 * or LetoDMS_Core_DocumentFile
	 * @return boolean true on success, false on failure
	 */
	public function createPreview($object) { /* {{{ */
		if(!$object)
			return false;

		$document = $object->getDocument();
		$file = $document->_dms->contentDir.$object->getPath();
		$target = $this->getFileName($object);
		return $this->createRawPreview($file, $document->getDir(), $object->getMimeType(), $target);
	} /* }}} */

	/**
	 * Check if a preview image already exists.
	 *
	 * This function is a companion to {@link LetoDMS_Preview_Previewer::createRawPreview()}.
	 *
	 * @param string $infile name of input file including full path
	 * @param string $dir directory relative to $this->previewDir
	 * @return boolean true if preview exists, otherwise false
	 */
	public function hasRawPreview($infile, $dir) { /* {{{ */
		if(!$this->previewDir)
			return false;
		$target = $this->previewDir.$dir.md5($infile);
		if($target !== false && file_exists($target.'.pdf') && filectime($target.'.pdf') >= filectime($infile)) {
			return true;
		}
		return false;
	} /* }}} */

	/**
	 * Check if a preview image already exists.
	 *
	 * This function is a companion to {@link LetoDMS_Preview_Previewer::createPreview()}.
	 *
	 * @param object $object instance of LetoDMS_Core_DocumentContent
	 * or LetoDMS_Core_DocumentFile
	 * @return boolean true if preview exists, otherwise false
	 */
	public function hasPreview($object) { /* {{{ */
		if(!$object)
			return false;

		if(!$this->previewDir)
			return false;
		$target = $this->getFileName($object);
		if($target !== false && file_exists($target.'.pdf') && filectime($target.'.pdf') >= $object->getDate()) {
			return true;
		}
		return false;
	} /* }}} */

	/**
	 * Return a preview image.
	 *
	 * This function returns the content of a preview image if it exists..
	 *
	 * @param string $infile name of input file including full path
	 * @param string $dir directory relative to $this->previewDir
	 * @return boolean/string image content if preview exists, otherwise false
	 */
	public function getRawPreview($infile, $dir) { /* {{{ */
		if(!$this->previewDir)
			return false;

		$target = $this->previewDir.$dir.md5($infile);
		if($target && file_exists($target.'.pdf')) {
			$this->sendFile($target.'.pdf');
		}
	} /* }}} */

	/**
	 * Return a preview image.
	 *
	 * This function returns the content of a preview image if it exists..
	 *
	 * @param object $object instance of LetoDMS_Core_DocumentContent
	 * or LetoDMS_Core_DocumentFile
	 * @return boolean/string image content if preview exists, otherwise false
	 */
	public function getPreview($object) { /* {{{ */
		if(!$this->previewDir)
			return false;

		$target = $this->getFileName($object);
		if($target && file_exists($target.'.pdf')) {
			$this->sendFile($target.'.pdf');
		}
	} /* }}} */

	/**
	 * Return file size preview image.
	 *
	 * @param object $object instance of LetoDMS_Core_DocumentContent
	 * or LetoDMS_Core_DocumentFile
	 * @return boolean/integer size of preview image or false if image
	 * does not exist
	 */
	public function getFilesize($object) { /* {{{ */
		$target = $this->getFileName($object);
		if($target && file_exists($target.'.pdf')) {
			return(filesize($target.'.pdf'));
		} else {
			return false;
		}

	} /* }}} */

	/**
	 * Delete preview image.
	 *
	 * @param object $object instance of LetoDMS_Core_DocumentContent
	 * or LetoDMS_Core_DocumentFile
	 * @return boolean true if deletion succeded or false if file does not exist
	 */
	public function deletePreview($object) { /* {{{ */
		if(!$this->previewDir)
			return false;

		$target = $this->getFileName($object);
		if($target && file_exists($target.'.pdf')) {
			return(unlink($target.'.pdf'));
		} else {
			return false;
		}
	} /* }}} */

	static function recurseRmdir($dir) {
		$files = array_diff(scandir($dir), array('.','..'));
		foreach ($files as $file) {
			(is_dir("$dir/$file")) ? LetoDMS_Preview_Previewer::recurseRmdir("$dir/$file") : unlink("$dir/$file");
		}
		return rmdir($dir);
	}

	/**
	 * Delete all preview images belonging to a document
	 *
	 * This function removes the preview images of all versions and
	 * files of a document including the directory. It actually just
	 * removes the directory for the document in the cache.
	 *
	 * @param object $document instance of LetoDMS_Core_Document
	 * @return boolean true if deletion succeded or false if file does not exist
	 */
	public function deleteDocumentPreviews($document) { /* {{{ */
		if(!$this->previewDir)
			return false;

		$dir = $this->previewDir.'/'.$document->getDir();
		if(file_exists($dir) && is_dir($dir)) {
			return LetoDMS_Preview_Previewer::recurseRmdir($dir);
		} else {
			return false;
		}

	} /* }}} */
}
?>
