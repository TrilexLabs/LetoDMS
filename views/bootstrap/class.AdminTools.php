<?php
/**
 * Implementation of AdminTools view
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
 * Class which outputs the html page for AdminTools view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_AdminTools extends LetoDMS_Bootstrap_Style {

	static function wrapRow($content) { /* {{{ */
		return self::startRow().$content.self::endRow();
	} /* }}} */

	static function startRow() { /* {{{ */
		return '<div class="row-fluid">';
	} /* }}} */

	static function endRow() { /* {{{ */
		return '</div>';
	} /* }}} */

	static function rowButton($link, $icon, $label) { /* {{{ */
		return '<a href="'.$link.'" class="span2 btn btn-medium"><i class="icon-'.$icon.'"></i><br />'.getMLText($label).'</a>';
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$logfileenable = $this->params['logfileenable'];
		$enablefullsearch = $this->params['enablefullsearch'];

		$this->htmlStartPage(getMLText("admin_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");
//		$this->contentHeading(getMLText("admin_tools"));
		$this->contentContainerStart();
?>
	<div id="admin-tools">
	<?php echo $this->callHook('beforeRows'); ?>
	<?= self::startRow(); ?>
		<?= self::rowButton("../out/out.UsrMgr.php", "user", "user_management"); ?>
		<?= self::rowButton("../out/out.GroupMgr.php", "group", "group_management"); ?>
		<?php echo $this->callHook('endOfRow', 1); ?>
	<?= self::endRow(); ?>
	<?= self::startRow(); ?>
		<?= self::rowButton("../out/out.BackupTools.php", "hdd", "backup_tools"); ?>
<?php		
		if ($logfileenable)
			echo self::rowButton("../out/out.LogManagement.php", "list", "log_management");
?>
		<?php echo $this->callHook('endOfRow', 2); ?>
	</div>
	<?= self::startRow(); ?>
		<?= self::rowButton("../out/out.DefaultKeywords.php", "reorder", "global_default_keywords"); ?>
		<?= self::rowButton("../out/out.Categories.php", "columns", "global_document_categories"); ?>
		<?= self::rowButton("../out/out.AttributeMgr.php", "tags", "global_attributedefinitions"); ?>
		<?php echo $this->callHook('endOfRow', 3); ?>
	<?= self::endRow(); ?>
<?php
	if($this->params['workflowmode'] == 'advanced') {
?>
	<?= self::startRow(); ?>
		<?= self::rowButton("../out/out.WorkflowMgr.php", "sitemap", "global_workflows"); ?>
		<?= self::rowButton("../out/out.WorkflowStatesMgr.php", "star", "global_workflow_states"); ?>
		<?= self::rowButton("../out/out.WorkflowActionsMgr.php", "bolt", "global_workflow_actions"); ?>
		<?php echo $this->callHook('endOfRow', 4); ?>
	<?= self::endRow(); ?>
<?php
		}
		if($enablefullsearch) {
?>
	<?= self::startRow(); ?>
		<?= self::rowButton("../out/out.Indexer.php", "refresh", "update_fulltext_index"); ?>
		<?= self::rowButton("../out/out.CreateIndex.php", "search", "create_fulltext_index"); ?>
		<?= self::rowButton("../out/out.IndexInfo.php", "info-sign", "fulltext_info"); ?>
		<?php echo $this->callHook('endOfRow', 5); ?>
	<?= self::endRow(); ?>
<?php
		}
?>
	<?= self::startRow(); ?>
		<?= self::rowButton("../out/out.Statistic.php", "tasks", "folders_and_documents_statistic"); ?>
		<?= self::rowButton("../out/out.Charts.php", "bar-chart", "charts"); ?>
		<?= self::rowButton("../out/out.ObjectCheck.php", "check", "objectcheck"); ?>
		<?= self::rowButton("../out/out.Timeline.php", "time", "timeline"); ?>
		<?php echo $this->callHook('endOfRow', 6); ?>
	<?= self::endRow(); ?>
	<?= self::startRow(); ?>
		<?= self::rowButton("../out/out.Settings.php", "wrench", "settings"); ?>
		<?= self::rowButton("../out/out.ExtensionMgr.php", "cogs", "extension_manager"); ?>
		<?= self::rowButton("../out/out.Info.php", "info-sign", "version_info"); ?>
		<?php echo $this->callHook('endOfRow', 7); ?>
	<?= self::endRow(); ?>
	<?php echo $this->callHook('afterRows'); ?>
	</div>
<?php
		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
