<?php
/**
 * Implementation of notifation system using email
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
 * Include parent class
 */
require_once("inc.ClassNotify.php");
require_once("Mail.php");

/**
 * Class to send email notifications to individuals or groups
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_EmailNotify extends LetoDMS_Notify {
	/**
	 * Instanz of DMS
	 */
	protected $_dms;

	protected $smtp_server;

	protected $smtp_port;

	protected $smtp_user;

	protected $smtp_password;

	protected $from_address;

	protected $lazy_ssl;

	protected $debug;

	function __construct($dms, $from_address='', $smtp_server='', $smtp_port='', $smtp_username='', $smtp_password='', $lazy_ssl=true) { /* {{{ */
		$this->_dms = $dms;
		$this->smtp_server = $smtp_server;
		$this->smtp_port = $smtp_port;
		$this->smtp_user = $smtp_username;
		$this->smtp_password = $smtp_password;
		$this->from_address = $from_address;
		$this->lazy_ssl = $lazy_ssl;
		$this->debug = false;
	} /* }}} */

	public function setDebug($debug=true) { /* {{{ */
		$this->debug = (bool) $debug;
	} /* }}} */

	/**
	 * Send mail to individual user
	 *
	 * @param mixed $sender individual sending the email. This can be a
	 *        user object or a string. If it is left empty, then
	 *        $this->from_address will be used.
	 * @param object $recipient individual receiving the mail
	 * @param string $subject key of string containing the subject of the mail
	 * @param string $message key of string containing the body of the mail
	 * @param array $params list of parameters which replaces placeholder in
	 *        the subject and body
	 * @return false or -1 in case of error, otherwise true
	 */
	function toIndividual($sender, $recipient, $subject, $message, $params=array()) { /* {{{ */
		if(is_object($recipient) && !strcasecmp(get_class($recipient), $this->_dms->getClassname('user')) && !$recipient->isDisabled() && $recipient->getEmail()!="") {
			$to = $recipient->getEmail();
			$lang = $recipient->getLanguage();
		} elseif(is_string($recipient) && trim($recipient) != "") {
			$to = $recipient;
			if(isset($params['__lang__']))
				$lang = $params['__lang__'];
			else
				$lang = 'en_GB';
		} else {
			return false;
		}

		$returnpath = $this->from_address;
		if(is_object($sender) && !strcasecmp(get_class($sender), $this->_dms->getClassname('user'))) {
			$from = $sender->getFullName() ." <". $sender->getEmail() .">";
			if(!$returnpath)
				$returnpath = $sender->getEmail();
		} elseif(is_string($sender) && trim($sender) != "") {
			$from = $sender;
			if(!$returnpath)
				$returnpath = $sender;
		} else {
			$from = $this->from_address;
		}


		$message = getMLText("email_header", array(), "", $lang)."\r\n\r\n".getMLText($message, $params, "", $lang);
		$message .= "\r\n\r\n".getMLText("email_footer", array(), "", $lang);

		$headers = array ();
		$headers['From'] = $from;
		if($returnpath)
			$headers['Return-Path'] = $returnpath;
		$headers['To'] = $to;
		$preferences = array("input-charset" => "UTF-8", "output-charset" => "UTF-8");
		$encoded_subject = iconv_mime_encode("Subject", getMLText($subject, $params, "", $lang), $preferences);
		$headers['Subject'] = substr($encoded_subject, strlen('Subject: '));
		$headers['Date'] = date('r', time());
		$headers['MIME-Version'] = "1.0";
		$headers['Content-type'] = "text/plain; charset=utf-8";

		$mail_params = array();
		if($this->smtp_server) {
			if($this->debug)
				$mail_params['debug'] = true;
			$mail_params['host'] = $this->smtp_server;
			if($this->smtp_port) {
				$mail_params['port'] = $this->smtp_port;
			}
			if($this->smtp_user) {
				$mail_params['auth'] = true;
				$mail_params['username'] = $this->smtp_user;
				$mail_params['password'] = $this->smtp_password;
			}
			/* See ticket #384 */
			if($this->lazy_ssl)
				$mail_params['socket_options'] = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false));

			$mail = Mail::factory('smtp', $mail_params);
		} else {
			$mail = Mail::factory('mail', $mail_params);
		}

		if (isset($GLOBALS['LetoDMS_HOOKS']['mailqueue'])) {
			foreach($GLOBALS['LetoDMS_HOOKS']['mailqueue'] as $queueService) {
        if(method_exists($queueService, 'queueMailJob')) {
					$ret = $queueService->queueMailJob($mail_params, $to, $headers, getMLText($subject, $params, "", $lang), $message);
					if($ret !== null)
						return $ret;
        }
			}
		}
		$result = $mail->send($to, $headers, $message);
		if (PEAR::isError($result)) {
			return false;
		} else {
			return true;
		}
	} /* }}} */

	function toGroup($sender, $groupRecipient, $subject, $message, $params=array()) { /* {{{ */
		if ((!is_object($sender) && strcasecmp(get_class($sender), $this->_dms->getClassname('user'))) ||
				(!is_object($groupRecipient) || strcasecmp(get_class($groupRecipient), $this->_dms->getClassname('group')))) {
			return false;
		}

		foreach ($groupRecipient->getUsers() as $recipient) {
			$this->toIndividual($sender, $recipient, $subject, $message, $params);
		}

		return true;
	} /* }}} */

	function toList($sender, $recipients, $subject, $message, $params=array()) { /* {{{ */
		if ((!is_object($sender) && strcasecmp(get_class($sender), $this->_dms->getClassname('user'))) ||
				(!is_array($recipients) && count($recipients)==0)) {
			return false;
		}

		foreach ($recipients as $recipient) {
			$this->toIndividual($sender, $recipient, $subject, $message, $params);
		}

		return true;
	} /* }}} */
}
?>
