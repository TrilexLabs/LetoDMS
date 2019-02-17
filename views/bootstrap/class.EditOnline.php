<?php
/**
 * Implementation of EditOnline view
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
 * Class which outputs the html page for EditOnline view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_EditOnline extends LetoDMS_Bootstrap_Style {
	var $dms;
	var $folder_count;
	var $document_count;
	var $file_count;
	var $storage_size;

	function js() { /* {{{ */
		$document = $this->params['document'];
		header('Content-Type: application/javascript; charset=UTF-8');
?>
$(document).ready(function()	{
	$('#markdown').markItUp(mySettings);

	$('#update').click(function(event) {
		event.preventDefault();
		$.post("../op/op.EditOnline.php", $('#form1').serialize(), function(response) {
			noty({
				text: response.message,
				type: response.success === true ? 'success' : 'error',
				dismissQueue: true,
				layout: 'topRight',
				theme: 'defaultTheme',
				timeout: 1500,
			});
			$('div.ajax').trigger('update', {documentid: <?php echo $document->getId(); ?>});
		}, "json");
		return false;
	});
});
<?php
	} /* }}} */

	function preview() { /* {{{ */
		$dms = $this->params['dms'];
		$document = $this->params['document'];
		$version = $this->params['version'];
?>
		<ul class="nav nav-tabs" id="preview-tab">
		  <li class="active"><a data-target="#preview_markdown" data-toggle="tab"><?php printMLText('preview_markdown'); ?></a></li>
		  <li><a data-target="#preview_plain" data-toggle="tab"><?php printMLText('preview_plain'); ?></a></li>
		</ul>
		<div class="tab-content">
		  <div class="tab-pane active" id="preview_markdown">
<?php
		require_once('parsedown/Parsedown.php');
		$Parsedown = new Parsedown();
		echo $Parsedown->text(file_get_contents($dms->contentDir . $version->getPath()));
?>
			</div>
		  <div class="tab-pane" id="preview_plain">
<?php
		echo "<pre>".htmlspecialchars(file_get_contents($dms->contentDir . $version->getPath()))."</pre>";
?>
			</div>
		</div>
<?php
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$document = $this->params['document'];
		$version = $this->params['version'];
		$cachedir = $this->params['cachedir'];
		$previewwidthlist = $this->params['previewWidthList'];
		$previewwidthdetail = $this->params['previewWidthDetail'];

		$set = 'markdown'; //default or markdown
		$skin = 'simple'; // simple or markitup
		$this->htmlAddHeader('<link href="../styles/'.$this->theme.'/markitup/skins/'.$skin.'/style.css" rel="stylesheet">'."\n", 'css');
		$this->htmlAddHeader('<link href="../styles/'.$this->theme.'/markitup/sets/'.$set.'/style.css" rel="stylesheet">'."\n", 'css');
		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/markitup/jquery.markitup.js"></script>'."\n", 'js');
		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/markitup/sets/'.$set.'/set.js"></script>'."\n", 'js');

		$this->htmlStartPage(getMLText("edit_online"));
		$this->globalNavigation();
		$this->contentStart();
		$folder = $document->getFolder();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document", $document);
?>

<?php
echo "<div class=\"row-fluid\">\n";

echo "<div class=\"span6\">\n";
$this->contentHeading(getMLText("content"));
?>
<form action="../op/op.EditOnline.php" id="form1" method="post">
<input type="hidden" name="documentid" value="<?php echo $document->getId(); ?>" />
<textarea id="markdown" name="data" width="100%" rows="20">
<?php
		echo htmlspecialchars(file_get_contents($dms->contentDir . $version->getPath()));
?>
</textarea>
<button id="update" type="submit" class="btn btn-primary"><i class="icon-save"></i> <?php printMLText("save"); ?></button>
</form>
<?php
		echo "</div>\n";

		echo "<div class=\"span6\">\n";
		$this->contentHeading(getMLText("preview"));
		echo "<div class=\"ajax\" data-view=\"EditOnline\" data-action=\"preview\" data-query=\"documentid=".$document->getId()."\"></div>";
		echo "</div>\n";

		echo "</div>\n";

		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
