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
 * Abstract class to authenticate user against ldap server
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2016 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_LdapAuthentication extends LetoDMS_Authentication {
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
	 * Do ldap authentication
	 *
	 * This method supports active directory and open ldap servers. Others may work but
	 * are not tested.
	 * The authentication is done in two steps.
	 * 1. First an anonymous bind is done and the user who wants to login is searched
	 * for. If it is found the cn of that user will be used for the bind in step 2.
	 * If the user cannot be found the second step will use a cn: cn=<username>,<basedn>
	 * 2. A second bind with a password and cn will be executed. This is the actuall
	 * authentication. If that succeeds the user is logged in. If the user doesn't
	 * exist in the database, it will be created.
	 *
	 * @param string $username
	 * @param string $password
	 * @return object|boolean user object if authentication was successful otherwise false
	 */
	public function authenticate($username, $password) { /* {{{ */
		$settings = $this->settings;
		$dms = $this->dms;

		if (isset($settings->_ldapPort) && is_int($settings->_ldapPort)) {
			$ds = ldap_connect($settings->_ldapHost, $settings->_ldapPort);
		} else {
			$ds = ldap_connect($settings->_ldapHost);
		}

		if (!is_bool($ds)) {
			/* Check if ldap base dn is set, and use ldap server if it is */
			if (isset($settings->_ldapBaseDN)) {
				$ldapSearchAttribut = "uid=";
				$tmpDN = "cn=".$username.",".$settings->_ldapBaseDN;
			}

			/* Active directory has a different base dn */
			if (isset($settings->_ldapType)) {
				if ($settings->_ldapType==1) {
					$ldapSearchAttribut = "sAMAccountName=";
					$tmpDN = $username.'@'.$settings->_ldapAccountDomainName;
					// Add the following if authentication with an Active Dir doesn't work
					// See https://sourceforge.net/p/LetoDMS/discussion/general/thread/19c70d8d/
					// and http://stackoverflow.com/questions/6222641/how-to-php-ldap-search-to-get-user-ou-if-i-dont-know-the-ou-for-base-dn
					ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
				}
			}

			// Ensure that the LDAP connection is set to use version 3 protocol.
			// Required for most authentication methods, including SASL.
			ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);

			// try an authenticated/anonymous bind first.
			// If it succeeds, get the DN for the user and use it for an authentication
			// with the users password.
			$bind = false;
			if (isset($settings->_ldapBindDN)) {
				$bind = @ldap_bind($ds, $settings->_ldapBindDN, $settings->_ldapBindPw);
			} else {
				$bind = @ldap_bind($ds);
			}
			$dn = false;
			/* If bind succeed, then get the dn of for the user */
			if ($bind) {
				if (isset($settings->_ldapFilter) && strlen($settings->_ldapFilter) > 0) {
					$search = ldap_search($ds, $settings->_ldapBaseDN, "(&(".$ldapSearchAttribut.$username.")".$settings->_ldapFilter.")");
				} else {
					$search = ldap_search($ds, $settings->_ldapBaseDN, $ldapSearchAttribut.$username);
				}
				if (!is_bool($search)) {
					$info = ldap_get_entries($ds, $search);
					if (!is_bool($info) && $info["count"]>0) {
						$dn = $info[0]['dn'];
					}
				}
			}

			/* If the previous bind failed, try it with the users creditionals
			 * by simply setting $dn to a default string
			 */
			if (is_bool($dn)) {
				$dn = $tmpDN;
			}

			/* No do the actual authentication of the user */
			$bind = @ldap_bind($ds, $dn, $password);
			$user = $dms->getUserByLogin($username);
			if($user === false) {
				ldap_close($ds);
				return false;
			}
			if ($bind) {
				// Successfully authenticated. Now check to see if the user exists within
				// the database. If not, add them in if _restricted is not set,
				// but do not add their password.
				if (is_null($user) && !$settings->_restricted) {
					// Retrieve the user's LDAP information.
					if (isset($settings->_ldapFilter) && strlen($settings->_ldapFilter) > 0) {
						$search = ldap_search($ds, $settings->_ldapBaseDN, "(&(".$ldapSearchAttribut.$username.")".$settings->_ldapFilter.")");
					} else {
						$search = ldap_search($ds, $settings->_ldapBaseDN, $ldapSearchAttribut.$username);
					}

					if (!is_bool($search)) {
						$info = ldap_get_entries($ds, $search);
						if (!is_bool($info) && $info["count"]==1 && $info[0]["count"]>0) {
							$user = $dms->addUser($username, null, $info[0]['cn'][0], $info[0]['mail'][0], $settings->_language, $settings->_theme, "", 0);
						}
					}
				}
			} elseif($user) {
				$userid = $user->getID();
				if($settings->_loginFailure) {
					$failures = $user->addLoginFailure();
					if($failures >= $settings->_loginFailure)
						$user->setDisabled(true);
				}
				$user = false;
			}
			ldap_close($ds);

			return $user;
		} else {
			return false;
		}
	} /* }}} */
}
