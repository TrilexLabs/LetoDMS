<?php
/**
 * Implementation of Clipboard view
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include parent class
 */
require_once("class.Bootstrap.php");

/**
 * Class which outputs the html page for clipboard view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_Session extends LetoDMS_Bootstrap_Style {
	/**
	 * Returns the html needed for the clipboard list in the menu
	 *
	 * This function renders the clipboard in a way suitable to be
	 * used as a menu
	 *
	 * @param array $clipboard clipboard containing two arrays for both
	 *        documents and folders.
	 * @return string html code
	 */
	public function menuSessions() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];

		$sessionmgr = new LetoDMS_SessionMgr($dms->getDB());
		$sessions = $sessionmgr->getLastAccessedSessions(date('Y-m-d H:i:s', time()-3600));
		if(!$sessions)
			return '';

		if ($user->isGuest() || count($sessions) == 0) {
			return '';
		}
		$content = '';
		$content .= "   <ul id=\"main-menu-session\" class=\"nav pull-right\">\n";
		$content .= "    <li class=\"dropdown add-session-area\">\n";
		$content .= "     <a href=\"#\" class=\"dropdown-toggle\" data-toggle=\"dropdown\" class=\"add-session-area\">".getMLText('sessions')." (".count($sessions).") <i class=\"icon-caret-down\"></i></a>\n";
		$content .= "     <ul class=\"dropdown-menu\" role=\"menu\">\n";
		foreach($sessions as $session) {
			if($sesuser = $dms->getUser($session->getUser()))
				if(!$sesuser->isHidden())
					$content .= "    <li><a _href=\"\"><i class=\"icon-user\"></i> ".htmlspecialchars($sesuser->getFullName())." ".getReadableDuration(time()-$session->getLastAccess())."</a></li>\n";
		}
		$content .= "     </ul>\n";
		$content .= "    </li>\n";
		$content .= "   </ul>\n";
		echo $content;
	} /* }}} */

}
