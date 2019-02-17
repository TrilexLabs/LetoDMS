<?php
/**
 * Implementation of MyAccount view
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
 * Class which outputs the html page for MyAccount view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_MyAccount extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$enableuserimage = $this->params['enableuserimage'];
		$passwordexpiration = $this->params['passwordexpiration'];
		$httproot = $this->params['httproot'];
		$quota = $this->params['quota'];

		$this->htmlStartPage(getMLText("my_account"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("my_account"), "my_account");

		if($quota > 0) {
			if(($remain = checkQuota($user)) < 0) {
				$this->warningMsg(getMLText('quota_warning', array('bytes'=>LetoDMS_Core_File::format_filesize(abs($remain)))));
			}
		}
		$this->contentHeading(getMLText("user_info"));
		$this->contentContainerStart();


		echo "<div class=\"row-fluid\">\n";
		if ($enableuserimage){
			echo "<div class=\"span2\">\n";
			print ($user->hasImage() ? "<img class=\"userImage\" src=\"".$httproot . "out/out.UserImage.php?userid=".$user->getId()."\">" : getMLText("no_user_image"))."\n";
			echo "</div>\n";
			echo "<div class=\"span10\">\n";
		} else {
			echo "<div class=\"span12\">\n";
		}

		print "<table class=\"table-condensed\">\n";
		print "<tr>\n";
		print "<td>".getMLText("name")." : </td>\n";
		print "<td>".htmlspecialchars($user->getFullName()).($user->isAdmin() ? " (".getMLText("admin").")" : "")."</td>\n";
		print "</tr>\n<tr>\n";
		print "<td>".getMLText("user_login")." : </td>\n";
		print "<td>".$user->getLogin()."</td>\n";
		print "</tr>\n<tr>\n";
		print "<td>".getMLText("email")." : </td>\n";
		print "<td>".htmlspecialchars($user->getEmail())."</td>\n";
		print "</tr>\n<tr>\n";
		print "<td>".getMLText("comment")." : </td>\n";
		print "<td>".htmlspecialchars($user->getComment())."</td>\n";
		print "</tr>\n";
		if($passwordexpiration > 0) {
			print "<tr>\n";
			print "<td>".getMLText("password_expiration")." : </td>\n";
			print "<td>".htmlspecialchars($user->getPwdExpiration())."</td>\n";
			print "</tr>\n";
		}
		print "<tr>\n";
		print "<td>".getMLText("used_discspace")." : </td>\n";
		print "<td>".LetoDMS_Core_File::format_filesize($user->getUsedDiskSpace())."</td>\n";
		print "</tr>\n";
		if($quota > 0) {
			print "<tr>\n";
			print "<td>".getMLText("quota")." : </td>\n";
			print "<td>".LetoDMS_Core_File::format_filesize($user->getQuota())."</td>\n";
			print "</tr>\n";
			if($user->getQuota() > $user->getUsedDiskSpace()) {
				$used = (int) ($user->getUsedDiskSpace()/$user->getQuota()*100.0+0.5);
				$free = 100-$used;
			} else {
				$free = 0;
				$used = 100;
			}
			print "<tr>\n";
			print "<td>\n";
			print "</td>\n";
			print "<td>\n";
?>
		<div class="progress">
			<div class="bar bar-danger" style="width: <?php echo $used; ?>%;"></div>
		  <div class="bar bar-success" style="width: <?php echo $free; ?>%;"></div>
		</div>
<?php
			print "</td>\n";
			print "</tr>\n";
		}
		print "</table>\n";
		print "</div>\n";
		print "</div>\n";

		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
