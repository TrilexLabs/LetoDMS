<?php
/**
 * Implementation of Download controller
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
class LetoDMS_Controller_Download extends LetoDMS_Controller_Common {

	public function run() {
		$dms = $this->params['dms'];
		$type = $this->params['type'];

		switch($type) {
			case "version":
				$content = $this->params['content'];
				if(null === $this->callHook('version')) {
					if(file_exists($dms->contentDir . $content->getPath())) {
						header("Content-Transfer-Encoding: binary");
						header("Content-Length: " . filesize($dms->contentDir . $content->getPath() ));
						$efilename = rawurlencode($content->getOriginalFileName());
						header("Content-Disposition: attachment; filename=\"" . $efilename . "\"; filename*=UTF-8''".$efilename);
						header("Content-Type: " . $content->getMimeType());
						header("Cache-Control: must-revalidate");

						sendFile($dms->contentDir.$content->getPath());
					}
				}
				break;
		}
	}
}
