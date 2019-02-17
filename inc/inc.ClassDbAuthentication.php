<?php
/**
 * Implementation of user authentication
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2016 Uwe Steinmann
 * @version    Release: @package_version@
 */

require_once "inc.ClassAuthentication.php";

/**
 * Abstract class to authenticate user against ѕeeddms database
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2016 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_DbAuthentication extends LetoDMS_Authentication {
	/**
	 * @var object $dms object of dms
	 * @access protected
	 */
	private $dms;

	/**
	 * @var object $settings LetoDMS Settings
	 * @access protected
	 */
	private $settings;

	function __construct($dms, $settings) { /* {{{ */
		$this->dms = $dms;
		$this->settings = $settings;
	} /* }}} */

	/**
	 * Do Authentication
	 *
	 * @param string $username
	 * @param string $password
	 * @return object|boolean user object if authentication was successful otherwise false
	 */
	public function authenticate($username, $password) { /* {{{ */
		$settings = $this->settings;
		$dms = $this->dms;

		// Try to find user with given login.
		if($user = $dms->getUserByLogin($username)) {
			$userid = $user->getID();

			// Check if password matches (if not a guest user)
			// Assume that the password has been sent via HTTP POST. It would be careless
			// (and dangerous) for passwords to be sent via GET.
			if (md5($password) != $user->getPwd()) {
				/* if counting of login failures is turned on, then increment its value */
				if($settings->_loginFailure) {
					$failures = $user->addLoginFailure();
					if($failures >= $settings->_loginFailure)
						$user->setDisabled(true);
				}
				$user = false;
			}
		}

		return $user;
	} /* }}} */
}
