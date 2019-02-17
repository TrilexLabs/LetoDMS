<?php
/**
 * Implementation of ExtensionMgr view
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2013 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include parent class
 */
require_once("class.Bootstrap.php");

/**
 * Class which outputs the html page for ExtensionMgr view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2013 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_ExtensionMgr extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript');
?>
		$(document).ready( function() {
			$('a.download').click(function(ev){
				var element = $(this);
				$('#'+element.data('extname')+'-download').submit();
/*
				var element = $(this);
				ev.preventDefault();
				$.ajax({url: '../op/op.ExtensionMgr.php',
					type: 'POST',
					dataType: "json",
					data: {action: 'download', 'formtoken': '<?= createFormKey('extensionmgr') ?>', 'extname': element.data('extname')},
					success: function(data) {
						noty({
							text: data.msg,
							type: (data.error) ? 'error' : 'success',
							dismissQueue: true,
							layout: 'topRight',
							theme: 'defaultTheme',
							timeout: 1500,
						});
					}
				});
*/
			});

			$('a.import').click(function(ev){
				var element = $(this);
				$('#'+element.data('extname')+'-import').submit();
			});
		});
<?php

		$this->printFileChooserJs();
	} /* }}} */

	function info() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$extmgr = $this->params['extmgr'];
		$extname = $this->params['extname'];

		echo "<table class=\"table _table-condensed\">\n";
		print "<thead>\n<tr>\n";
		print "<th></th>\n";	
		print "<th>".getMLText('name')."</th>\n";	
		print "<th>".getMLText('version')."</th>\n";	
		print "<th>".getMLText('author')."</th>\n";	
		print "<th></th>\n";	
		print "</tr></thead><tbody>\n";
		$list = $extmgr->getExtensionListByName($extname);
		foreach($list as $re) {
			$extmgr->checkExtension($re);
			$checkmsgs = $extmgr->getErrorMsgs();
			$needsupdate = !isset($GLOBALS['EXT_CONF'][$re['name']]) || LetoDMS_Extension_Mgr::cmpVersion($re['version'], $GLOBALS['EXT_CONF'][$re['name']]['version']) > 0;
			echo "<tr";
			if(isset($GLOBALS['EXT_CONF'][$re['name']])) {
				if($needsupdate)
					echo " class=\"warning\"";
				else
					echo " class=\"success\"";
			}
			echo ">";
			echo "<td width=\"32\">".($re['icon-data'] ? '<img width="32" height="32" alt="'.$re['name'].'" title="'.$re['name'].'" src="'.$re['icon-data'].'">' : '')."</td>";
			echo "<td>".$re['title']."<br /><small>".$re['description']."</small>";
			if($checkmsgs)
				echo "<div><img src=\"".$this->getImgPath("attention.gif")."\"> ".implode('<br /><img src="'.$this->getImgPath("attention.gif").'"> ', $checkmsgs)."</div>";
			echo "</td>";
			echo "<td nowrap>".$re['version']."<br /><small>".$re['releasedate']."</small></td>";
			echo "<td nowrap>".$re['author']['name']."<br /><small>".$re['author']['company']."</small></td>";
			echo "<td nowrap>";
			echo "<div class=\"list-action\">";
			if(!$checkmsgs && $extmgr->isWritableExtDir())
				echo "<form style=\"display: inline-block; margin: 0px;\" method=\"post\" action=\"../op/op.ExtensionMgr.php\" id=\"".$re['name']."-import\">".createHiddenFieldWithKey('extensionmgr')."<input type=\"hidden\" name=\"action\" value=\"import\" /><input type=\"hidden\" name=\"currenttab\" value=\"repository\" /><input type=\"hidden\" name=\"url\" value=\"".$re['filename']."\" /><a class=\"import\" data-extname=\"".$re['name']."\" title=\"".getMLText('import_extension')."\"><i class=\"icon-download\"></i></a></form>";
			echo "</div>";
			echo "</td>";
			echo "</tr>";
		}	
		echo "</tbody></table>\n";
	} /* }}} */

	function changelog() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$extdir = $this->params['extdir'];
		$extmgr = $this->params['extmgr'];
		$extname = $this->params['extname'];

		if(isset($GLOBALS['EXT_CONF'][$extname])) {
			$extconf = $GLOBALS['EXT_CONF'][$extname];
			if(!empty($extconf['changelog']) && file_exists($extdir."/".$extname."/".$extconf['changelog'])) {
				echo '<div style="white-space: pre-wrap; font-family: monospace; padding: 0px;">'.file_get_contents($extdir."/".$extname."/".$extconf['changelog'])."</div>";
			}
		}
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$httproot = $this->params['httproot'];
		$extdir = $this->params['extdir'];
		$version = $this->params['version'];
		$extmgr = $this->params['extmgr'];
		$currenttab = $this->params['currenttab'];
		$reposurl = $this->params['reposurl'];
	
		$this->htmlStartPage(getMLText("admin_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");
		$this->contentHeading(getMLText("extension_manager"));
?>
<div class="row-fluid">
	<div class="span4">
<?php
		if($extmgr->isWritableExtDir()) {
?>
		<form class="form-horizontal" method="post" enctype="multipart/form-data" action="../op/op.ExtensionMgr.php">
			<?= createHiddenFieldWithKey('extensionmgr') ?>
			<input type="hidden" name="action" value="upload" />
<?php
			$this->formField(
				getMLText("extension_archive"),
				$this->getFileChooserHtml('userfile', false)
			);
			$this->formSubmit("<i class=\"icon-upload\"></i> ".getMLText('import_extension'));
?>
		</form>
<?php
		} else {
			echo "<div class=\"alert alert-warning\">".getMLText('extension_mgr_no_upload')."</div>";
		}
?>
	</div>
	<div class="span8">
		<ul class="nav nav-tabs" id="extensionstab">
			<li class="<?php if(!$currenttab || $currenttab == 'installed') echo 'active'; ?>"><a data-target="#installed" data-toggle="tab"><?= getMLText('extension_mgr_installed'); ?></a></li>
			<li class="<?php if($currenttab == 'repository') echo 'active'; ?>"><a data-target="#repository" data-toggle="tab"><?= getMLText('extension_mgr_repository'); ?></a></li>
		</ul>
		<div class="tab-content">
			<div class="tab-pane <?php if(!$currenttab || $currenttab == 'installed') echo 'active'; ?>" id="installed">
<?php
//		$this->contentContainerStart();
		echo "<table class=\"table _table-condensed\">\n";
		print "<thead>\n<tr>\n";
		print "<th></th>\n";	
		print "<th>".getMLText('name')."</th>\n";	
		print "<th>".getMLText('version')."</th>\n";	
		print "<th>".getMLText('author')."</th>\n";	
		print "<th></th>\n";	
		print "</tr></thead><tbody>\n";
		$errmsgs = array();
		foreach($GLOBALS['EXT_CONF'] as $extname=>$extconf) {
			$errmsgs = array();
			if(!isset($extconf['disable']) || $extconf['disable'] == false) {
				$extmgr->checkExtension($extname);
				$errmsgs = $extmgr->getErrorMsgs();
				if($errmsgs)
					echo "<tr class=\"error\">";
				else
					echo "<tr class=\"success\">";
			} else
				echo "<tr class=\"warning\">";
			echo "<td width=\"32\">";
			if($extconf['icon'])
				echo "<img width=\"32\" height=\"32\" src=\"".$httproot."ext/".$extname."/".$extconf['icon']."\" alt=\"".$extname."\" title=\"".$extname."\">";
			echo "</td>";
			echo "<td>".$extconf['title'];
			echo "<br /><small>".$extconf['description']."</small>";
			if($errmsgs)
				echo "<div><img src=\"".$this->getImgPath("attention.gif")."\"> ".implode('<br /><img src="'.$this->getImgPath("attention.gif").'"> ', $errmsgs)."</div>";
			echo "</td>";
			echo "<td nowrap>".$extconf['version'];
			echo "<br /><small>".$extconf['releasedate']."</small>";
			echo "</td>";
			echo "<td nowrap><a href=\"mailto:".$extconf['author']['email']."\">".$extconf['author']['name']."</a><br /><small>".$extconf['author']['company']."</small></td>";
			echo "<td nowrap>";
			echo "<div class=\"list-action\">";
			if(!empty($extconf['changelog']) && file_exists($extdir."/".$extname."/".$extconf['changelog'])) {
				echo "<a data-target=\"#extensionChangelog\" href=\"../out/out.ExtensionMgr.php?action=changelog&extensionname=".$extname."\" data-toggle=\"modal\" title=\"".getMLText('show_extension_changelog')."\"><i class=\"icon-reorder\"></i></a>\n";
			}
			if($extconf['config'])
				echo "<a href=\"../out/out.Settings.php?currenttab=extensions#".$extname."\" title=\"".getMLText('configure_extension')."\"><i class=\"icon-cogs\"></i></a>";
			echo "<form style=\"display: inline-block; margin: 0px;\" method=\"post\" action=\"../op/op.ExtensionMgr.php\" id=\"".$extname."-download\">".createHiddenFieldWithKey('extensionmgr')."<input type=\"hidden\" name=\"action\" value=\"download\" /><input type=\"hidden\" name=\"extname\" value=\"".$extname."\" /><a class=\"download\" data-extname=\"".$extname."\" title=\"".getMLText('download_extension')."\"><i class=\"icon-download\"></i></a></form>";
			echo "</div>";
			echo "</td>";
			echo "</tr>\n";
		}
		echo "</tbody></table>\n";
?>
<form action="../op/op.ExtensionMgr.php" name="form1" method="post">
  <?php echo createHiddenFieldWithKey('extensionmgr'); ?>
	<input type="hidden" name="action" value="refresh" />
	<p><button type="submit" class="btn"><i class="icon-refresh"></i> <?php printMLText("refresh");?></button></p>
</form>
<?php
//		$this->contentContainerEnd();
?>
			</div>

			<div class="tab-pane <?php if($currenttab == 'repository') echo 'active'; ?>" id="repository">
<?php
		echo "<table class=\"table _table-condensed\">\n";
		print "<thead>\n<tr>\n";
		print "<th></th>\n";	
		print "<th>".getMLText('name')."</th>\n";	
		print "<th>".getMLText('version')."</th>\n";	
		print "<th>".getMLText('author')."</th>\n";	
		print "<th></th>\n";	
		print "</tr></thead><tbody>\n";
		$list = $extmgr->getExtensionList();
		foreach($list as $re) {
			if(!$re)
				continue;
			$extmgr->checkExtension($re);
			$checkmsgs = $extmgr->getErrorMsgs();
			$needsupdate = !isset($GLOBALS['EXT_CONF'][$re['name']]) || LetoDMS_Extension_Mgr::cmpVersion($re['version'], $GLOBALS['EXT_CONF'][$re['name']]['version']) > 0;
			echo "<tr";
			if(isset($GLOBALS['EXT_CONF'][$re['name']])) {
				if($needsupdate)
					echo " class=\"warning\"";
				else
					echo " class=\"success\"";
			}
			echo ">";
			echo "<td width=\"32\">".($re['icon-data'] ? '<img width="32" height="32" alt="'.$re['name'].'" title="'.$re['name'].'" src="'.$re['icon-data'].'">' : '')."</td>";
			echo "<td>".$re['title'];
			echo "<br /><small>".$re['description']."</small>";
			if($checkmsgs)
				echo "<div><img src=\"".$this->getImgPath("attention.gif")."\"> ".implode('<br /><img src="'.$this->getImgPath("attention.gif").'"> ', $checkmsgs)."</div>";
			echo "</td>";
			echo "<td nowrap>".$re['version']."<br /><small>".$re['releasedate']."</small></td>";
			echo "<td nowrap>".$re['author']['name']."<br /><small>".$re['author']['company']."</small></td>";
			echo "<td nowrap>";
			echo "<div class=\"list-action\">";
			echo "<a data-target=\"#extensionInfo\" href=\"../out/out.ExtensionMgr.php?action=info&extensionname=".$re['name']."\" data-toggle=\"modal\" title=\"".getMLText('show_extension_version_list')."\"><i class=\"icon-list-ol\"></i></a>\n";
			if(!$checkmsgs && $extmgr->isWritableExtDir())
				echo "<form style=\"display: inline-block; margin: 0px;\" method=\"post\" action=\"../op/op.ExtensionMgr.php\" id=\"".$re['name']."-import\">".createHiddenFieldWithKey('extensionmgr')."<input type=\"hidden\" name=\"action\" value=\"import\" /><input type=\"hidden\" name=\"currenttab\" value=\"repository\" /><input type=\"hidden\" name=\"url\" value=\"".$re['filename']."\" /><a class=\"import\" data-extname=\"".$re['name']."\" title=\"".getMLText('import_extension')."\"><i class=\"icon-download\"></i></a></form>";
			echo "</div>";
			echo "</td>";
			echo "</tr>";
		}	
		echo "</tbody></table>\n";
?>
				<div>
					<form method="post" action="../op/op.ExtensionMgr.php">
					<?= createHiddenFieldWithKey('extensionmgr'); ?>
					<input type="hidden" name="action" value="getlist" />
					<input type="hidden" name="currenttab" value="repository" />
					<input type="hidden" name="forceupdate" value="1" />
          <button type="submit" class="btn btn-delete"><i class="icon-refresh"></i> <?= getMLText('force_update')?></button>
					</form>
</div>
			</div>
		</div>
  </div>
</div>
<div class="modal modal-wide hide" id="extensionInfo" tabindex="-1" role="dialog" aria-labelledby="extensionInfoLabel" aria-hidden="true">
  <div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
    <h3 id="extensionInfoLabel"><?= getMLText("extension_version_list") ?></h3>
  </div>
  <div class="modal-body">
		<p><?php printMLText('extension_loading') ?></p>
  </div>
  <div class="modal-footer">
    <button class="btn btn-primary" data-dismiss="modal" aria-hidden="true"><?php printMLText("close") ?></button>
  </div>
</div>
<div class="modal modal-wide hide" id="extensionChangelog" tabindex="-1" role="dialog" aria-labelledby="extensionChangelogLabel" aria-hidden="true">
  <div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
    <h3 id="extensionChangelogLabel"><?= getMLText("extension_changelog") ?></h3>
  </div>
  <div class="modal-body">
		<p><?php printMLText('extension_loading') ?></p>
  </div>
  <div class="modal-footer">
    <button class="btn btn-primary" data-dismiss="modal" aria-hidden="true"><?php printMLText("close") ?></button>
  </div>
</div>

<?php
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
