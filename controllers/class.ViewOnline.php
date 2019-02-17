<?php
/**
 * Implementation of ViewOnline controller
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
 * Class which does the busines logic for downloading a document
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2013 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Controller_ViewOnline extends LetoDMS_Controller_Common {

	public function run() {
		$dms = $this->params['dms'];
		$settings = $this->params['settings'];
		$type = $this->params['type'];
		$content = $this->params['content'];
		$document = $content->getDocument();

		switch($type) {
			case "version":
				if(null === $this->callHook('version')) {
					header("Content-Type: " . $content->getMimeType());
					$efilename = rawurlencode($content->getOriginalFileName());
					if (!isset($settings->_viewOnlineFileTypes) || !is_array($settings->_viewOnlineFileTypes) || !in_array(strtolower($content->getFileType()), $settings->_viewOnlineFileTypes)) {
						header("Content-Disposition: attachment; filename=\"" . $efilename . "\"; filename*=UTF-8''".$efilename);
					} else {
						header("Content-Disposition: filename=\"" . $efilename . "\"; filename*=UTF-8''".$efilename);
					}
					header("Content-Length: " . filesize($dms->contentDir . $content->getPath()));
					header("Cache-Control:  must-revalidate");

					sendFile($dms->contentDir.$content->getPath());
				}
				break;
		}
	}
}
