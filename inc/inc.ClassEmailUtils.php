<?php
/**
 * Implementation of email utils
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Class to send email
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_EmailUtils {

	function sendPassword($sender, $recipient, $subject, $message) { /* {{{ */
		$headers   = array();
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-type: text/plain; charset=utf-8";
		$headers[] = "From: ". $sender;
		$headers[] = "Reply-To: ". $sender;

		$subject = "=?UTF-8?B?".base64_encode($this->replaceMarker($subject))."?=";
		return (mail($recipient->getEmail(), $subject, $this->replaceMarker($message), implode("\r\n", $headers)) ? 0 : -1);
	} /* }}} */

	function replaceMarker($text) { /* {{{ */
		global $settings;

		return(str_replace(
			array('###SITENAME###', '###HTTP_ROOT###', '###URL_PREFIX###'),
			array($settings->_siteName, $settings->_httpRoot, "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot),
			$text));
	} /* }}} */

}
?>
