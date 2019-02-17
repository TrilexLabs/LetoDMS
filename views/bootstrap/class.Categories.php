<?php
/**
 * Implementation of Categories view
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
 * Include class to preview documents
 */
require_once("LetoDMS/Preview.php");

/**
 * Class which outputs the html page for Categories view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_Categories extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		$selcat = $this->params['selcategory'];
		header('Content-Type: application/javascript');
?>
$(document).ready( function() {
	$( "#selector" ).change(function() {
		$('div.ajax').trigger('update', {categoryid: $(this).val()});
	});
});
<?php
		$this->printDeleteFolderButtonJs();
		$this->printDeleteDocumentButtonJs();
	} /* }}} */

	function info() { /* {{{ */
		$dms = $this->params['dms'];
		$selcat = $this->params['selcategory'];
		$cachedir = $this->params['cachedir'];
		$previewwidth = $this->params['previewWidthList'];
		$timeout = $this->params['timeout'];

		if($selcat) {
			$this->contentHeading(getMLText("category_info"));
			$c = $selcat->countDocumentsByCategory();
			echo "<table class=\"table table-condensed\">\n";
			echo "<tr><td>".getMLText('document_count')."</td><td>".($c)."</td></tr>\n";
			echo "</table>";

			$documents = $selcat->getDocumentsByCategory(10);
			if($documents) {
				print "<table id=\"viewfolder-table\" class=\"table\">";
				print "<thead>\n<tr>\n";
				print "<th></th>\n";	
				print "<th>".getMLText("name")."</th>\n";
				print "<th>".getMLText("status")."</th>\n";
				print "<th>".getMLText("action")."</th>\n";
				print "</tr>\n</thead>\n<tbody>\n";
				$previewer = new LetoDMS_Preview_Previewer($cachedir, $previewwidth, $timeout);
				foreach($documents as $doc) {
					echo $this->documentListRow($doc, $previewer);
				}
				print "</tbody></table>";
			}
		}
	} /* }}} */

	function actionmenu() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$selcat = $this->params['selcategory'];

		if($selcat && !$selcat->isUsed()) {
?>
						<form style="display: inline-block;" method="post" action="../op/op.Categories.php" >
						<?php echo createHiddenFieldWithKey('removecategory'); ?>
						<input type="hidden" name="categoryid" value="<?php echo $selcat->getID()?>">
						<input type="hidden" name="action" value="removecategory">
						<button class="btn" type="submit"><i class="icon-remove"></i> <?php echo getMLText("rm_document_category")?></button>
						</form>
<?php
		}
	} /* }}} */

	function showCategoryForm($category) { /* {{{ */
?>
				<form class="form-horizontal" style="margin-bottom: 0px;" action="../op/op.Categories.php" method="post">
				<?php if(!$category) { ?>
					<?php echo createHiddenFieldWithKey('addcategory'); ?>
					<input type="hidden" name="action" value="addcategory">
				<?php } else { ?>
					<?php echo createHiddenFieldWithKey('editcategory'); ?>
					<input type="hidden" name="action" value="editcategory">
					<input type="hidden" name="categoryid" value="<?php echo $category->getID()?>">
				<?php } ?>
<?php
			$this->formField(
				getMLText("name"),
				array(
					'element'=>'input',
					'type'=>'text',
					'name'=>'name',
					'value'=>($category ? htmlspecialchars($category->getName()) : '')
				)
			);
			$this->formSubmit("<i class=\"icon-save\"></i> ".getMLText('save'));
?>
				</form>

<?php
	} /* }}} */

	function form() { /* {{{ */
		$selcat = $this->params['selcategory'];

		$this->showCategoryForm($selcat);
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$categories = $this->params['categories'];
		$selcat = $this->params['selcategory'];

		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/bootbox/bootbox.min.js"></script>'."\n", 'js');

		$this->htmlStartPage(getMLText("admin_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");

		$this->contentHeading(getMLText("global_document_categories"));
?>
<div class="row-fluid">
	<div class="span6">
<form class="form-horizontal">
			<select class="chzn-select" id="selector" class="input-xlarge">
				<option value="-1"><?php echo getMLText("choose_category")?>
				<option value="0"><?php echo getMLText("new_document_category")?>
<?php
				foreach ($categories as $category) {
					print "<option value=\"".$category->getID()."\" ".($selcat && $category->getID()==$selcat->getID() ? 'selected' : '').">" . htmlspecialchars($category->getName());
				}
?>
			</select>
</form>
	<div class="ajax" style="margin-bottom: 15px;" data-view="Categories" data-action="actionmenu" <?php echo ($selcat ? "data-query=\"categoryid=".$selcat->getID()."\"" : "") ?>></div>
		<div class="ajax" data-view="Categories" data-action="info" <?php echo ($selcat ? "data-query=\"categoryid=".$selcat->getID()."\"" : "") ?>></div>
	</div>

	<div class="span6">
		<?php	$this->contentContainerStart(); ?>
			<div class="ajax" data-view="Categories" data-action="form" <?php echo ($selcat ? "data-query=\"categoryid=".$selcat->getID()."\"" : "") ?>></div>
		<?php	$this->contentContainerEnd(); ?>
	</div>
</div>
	
<?php
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
