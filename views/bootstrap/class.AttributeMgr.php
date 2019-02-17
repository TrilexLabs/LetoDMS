<?php
/**
 * Implementation of AttributeMgr view
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
 * Class which outputs the html page for AttributeMgr view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_AttributeMgr extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		$selattrdef = $this->params['selattrdef'];
		header('Content-Type: application/javascript');
?>

$(document).ready( function() {
	$('body').on('submit', '#form', function(ev){
//		if(checkForm()) return;
//		ev.preventDefault();
	});
	$( "#selector" ).change(function() {
		$('div.ajax').trigger('update', {attrdefid: $(this).val()});
	});
});
<?php
		$this->printDeleteFolderButtonJs();
		$this->printDeleteDocumentButtonJs();
		$this->printDeleteAttributeValueButtonJs();
	} /* }}} */

	function info() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$attrdefs = $this->params['attrdefs'];
		$selattrdef = $this->params['selattrdef'];
		$cachedir = $this->params['cachedir'];
		$previewwidth = $this->params['previewWidthList'];
		$enableRecursiveCount = $this->params['enableRecursiveCount'];
		$maxRecursiveCount = $this->params['maxRecursiveCount'];
		$timeout = $this->params['timeout'];

		if($selattrdef) {
			$this->contentHeading(getMLText("attrdef_info"));
			$res = $selattrdef->getStatistics(30);
			if(!empty($res['frequencies']['document']) ||!empty($res['frequencies']['folder']) ||!empty($res['frequencies']['content'])) {
				foreach(array('document', 'folder', 'content') as $type) {
					$content = '';
					if(isset($res['frequencies'][$type]) && $res['frequencies'][$type]) {
						$content .= "<table class=\"table table-condensed\">";
						$content .= "<thead>\n<tr>\n";
						$content .= "<th>".getMLText("attribute_value")."</th>\n";
						$content .= "<th>".getMLText("attribute_count")."</th>\n";
						$content .= "<th></th>\n";
						$content .= "</tr></thead>\n<tbody>\n";
						$separator = $selattrdef->getValueSetSeparator();
						foreach($res['frequencies'][$type] as $entry) {
							$value = $selattrdef->parseValue($entry['value']);
							$content .= "<tr>";
							$content .= "<td>".htmlspecialchars(implode('<span style="color: #aaa;">'.($separator ? ' '.$separator.' ' : ' ; ').'</span>', $value))."</td>";
							$content .= "<td><a href=\"../out/out.Search.php?resultmode=".($type == 'folder' ? 2 : ($type == 'document' ? 1 : 3))."&attributes[".$selattrdef->getID()."]=".urlencode($entry['value'])."\">".urlencode($entry['c'])."</a></td>";
							$content .= "<td>";
							/* various checks, if the value is valid */
							if(!$selattrdef->validate($entry['value'])) {
								$content .= getAttributeValidationText($selattrdef->getValidationError(), $selattrdef->getName(), $entry['value'], $selattrdef->getRegex());
							}
							/* Check if value is in value set */
							/*
							if($selattrdef->getValueSet()) {
								foreach($value as $v) {
									if(!in_array($v, $selattrdef->getValueSetAsArray()))
										$content .= getMLText("attribute_value_not_in_valueset");
								}
							}
							 */
							$content .= "</td>";
							$content .= "<td>";
							$content .= "<div class=\"list-action\">";
							if($user->isAdmin()) {
								$content .= $this->printDeleteAttributeValueButton($selattrdef, implode(';', $value), 'splash_rm_attr_value', true);
							} else {
								$content .= '<span style="padding: 2px; color: #CCC;"><i class="icon-remove"></i></span>';
							}
							$content .= "</div>";
							$content .= "</td>";
							$content .= "</tr>";
						}
						$content .= "</tbody></table>";
					}
					if($content)
						$this->printAccordion(getMLText('attribute_value')." (".getMLText($type).")", $content);
				}
			}

			if($res['folders'] || $res['docs']) {
				print "<table id=\"viewfolder-table\" class=\"table table-condensed\">";
				print "<thead>\n<tr>\n";
				print "<th></th>\n";	
				print "<th>".getMLText("name")."</th>\n";
				print "<th>".getMLText("status")."</th>\n";
				print "<th>".getMLText("action")."</th>\n";
				print "</tr>\n</thead>\n<tbody>\n";
				foreach($res['folders'] as $subFolder) {
					echo $this->folderListRow($subFolder);
				}
				$previewer = new LetoDMS_Preview_Previewer($cachedir, $previewwidth, $timeout);
				foreach($res['docs'] as $document) {
					echo $this->documentListRow($document, $previewer);
				}

				echo "</tbody>\n</table>\n";
			}

			if($res['contents']) {
				print "<table id=\"viewfolder-table\" class=\"table\">";
				print "<thead>\n<tr>\n";
				print "<th></th>\n";	
				print "<th>".getMLText("name")."</th>\n";
				print "<th>".getMLText("status")."</th>\n";
				print "<th>".getMLText("action")."</th>\n";
				print "</tr>\n</thead>\n<tbody>\n";
				$previewer = new LetoDMS_Preview_Previewer($cachedir, $previewwidth, $timeout);
				foreach($res['contents'] as $content) {
					$doc = $content->getDocument();
					echo $this->documentListRow($doc, $previewer);
				}
				print "</tbody></table>";
			}
		}
	} /* }}} */

	function actionmenu() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$selattrdef = $this->params['selattrdef'];

		if($selattrdef && !$selattrdef->isUsed()) {
?>
			<form style="display: inline-block;" method="post" action="../op/op.AttributeMgr.php" >
				<?php echo createHiddenFieldWithKey('removeattrdef'); ?>
				<input type="hidden" name="attrdefid" value="<?php echo $selattrdef->getID()?>">
				<input type="hidden" name="action" value="removeattrdef">
				<button type="submit" class="btn"><i class="icon-remove"></i> <?php echo getMLText("rm_attrdef")?></button>
			</form>
<?php
		}
	} /* }}} */

	function showAttributeForm($attrdef) { /* {{{ */
?>
			<form class="form-horizontal" action="../op/op.AttributeMgr.php" method="post">
<?php
		if($attrdef) {
			echo createHiddenFieldWithKey('editattrdef');
?>
			<input type="hidden" name="action" value="editattrdef">
			<input type="hidden" name="attrdefid" value="<?php echo $attrdef->getID()?>" />
<?php
		} else {
  		echo createHiddenFieldWithKey('addattrdef');
?>
			<input type="hidden" name="action" value="addattrdef">
<?php
		}
		$this->formField(
			getMLText("attrdef_name"),
			array(
				'element'=>'input',
				'type'=>'text',
				'name'=>'name',
				'value'=>($attrdef ? htmlspecialchars($attrdef->getName()) : '')
			)
		);
?>
					<div class="control-group">
						<label class="control-label">
							<?php printMLText("attrdef_objtype");?>:
						</label>
						<div class="controls">
						<select name="objtype"><option value="<?php echo LetoDMS_Core_AttributeDefinition::objtype_all ?>"><?php printMLText('all'); ?></option><option value="<?php echo LetoDMS_Core_AttributeDefinition::objtype_folder ?>" <?php if($attrdef && $attrdef->getObjType() == LetoDMS_Core_AttributeDefinition::objtype_folder) echo "selected"; ?>><?php printMLText('folder'); ?></option><option value="<?php echo LetoDMS_Core_AttributeDefinition::objtype_document ?>" <?php if($attrdef && $attrdef->getObjType() == LetoDMS_Core_AttributeDefinition::objtype_document) echo "selected"; ?>><?php printMLText('document'); ?></option><option value="<?php echo LetoDMS_Core_AttributeDefinition::objtype_documentcontent ?>" <?php if($attrdef && $attrdef->getObjType() == LetoDMS_Core_AttributeDefinition::objtype_documentcontent) echo "selected"; ?>><?php printMLText('documentcontent'); ?></option></select>
						</div>
					</div>


					<div class="control-group">
						<label class="control-label"><?php printMLText("attrdef_type");?>:</label>
						<div class="controls">
							<select name="type"><option value="<?php echo LetoDMS_Core_AttributeDefinition::type_int ?>" <?php if($attrdef && $attrdef->getType() == LetoDMS_Core_AttributeDefinition::type_int) echo "selected"; ?>><?php printMLText('attrdef_type_int'); ?></option><option value="<?php echo LetoDMS_Core_AttributeDefinition::type_float ?>" <?php if($attrdef && $attrdef->getType() == LetoDMS_Core_AttributeDefinition::type_float) echo "selected"; ?>><?php printMLText('attrdef_type_float'); ?></option><option value="<?php echo LetoDMS_Core_AttributeDefinition::type_string ?>" <?php if($attrdef && $attrdef->getType() == LetoDMS_Core_AttributeDefinition::type_string) echo "selected"; ?>><?php printMLText('attrdef_type_string'); ?></option><option value="<?php echo LetoDMS_Core_AttributeDefinition::type_boolean ?>" <?php if($attrdef && $attrdef->getType() == LetoDMS_Core_AttributeDefinition::type_boolean) echo "selected"; ?>><?php printMLText('attrdef_type_boolean'); ?></option><option value="<?php echo LetoDMS_Core_AttributeDefinition::type_date ?>" <?php if($attrdef && $attrdef->getType() == LetoDMS_Core_AttributeDefinition::type_date) echo "selected"; ?>><?php printMLText('attrdef_type_date'); ?></option><option value="<?php echo LetoDMS_Core_AttributeDefinition::type_email ?>" <?php if($attrdef && $attrdef->getType() == LetoDMS_Core_AttributeDefinition::type_email) echo "selected"; ?>><?php printMLText('attrdef_type_email'); ?></option><option value="<?php echo LetoDMS_Core_AttributeDefinition::type_url ?>" <?php if($attrdef && $attrdef->getType() == LetoDMS_Core_AttributeDefinition::type_url) echo "selected"; ?>><?php printMLText('attrdef_type_url'); ?></option></select>
						</div>
					</div>
<?php
		$this->formField(
			getMLText("attrdef_multiple"),
			array(
				'element'=>'input',
				'type'=>'checkbox',
				'name'=>'multiple',
				'value'=>1,
				'checked'=>($attrdef && $attrdef->getMultipleValues())
			)
		);
		$this->formField(
			getMLText("attrdef_minvalues"),
			array(
				'element'=>'input',
				'type'=>'text',
				'name'=>'minvalues',
				'value'=>($attrdef ? $attrdef->getMinValues() : ''),
			)
		);
		$this->formField(
			getMLText("attrdef_maxvalues"),
			array(
				'element'=>'input',
				'type'=>'text',
				'name'=>'maxvalues',
				'value'=>($attrdef ? $attrdef->getMaxValues() : ''),
			)
		);
		$this->formField(
			getMLText("attrdef_valueset"),
			(($attrdef && strlen($attrdef->getValueSet()) > 30)
			? array(
				'element'=>'textarea',
				'name'=>'valueset',
				'rows'=>5,
				'value'=>(($attrdef && $attrdef->getValueSet()) ? $attrdef->getValueSetSeparator().implode("\n".$attrdef->getValueSetSeparator(), $attrdef->getValueSetAsArray()) : ''),
			)
			: array(
				'element'=>'input',
				'type'=>'text',
				'name'=>'valueset',
				'value'=>($attrdef ? $attrdef->getValueSet() : ''),
			))
		);
		$this->formField(
			getMLText("attrdef_regex"),
			array(
				'element'=>'input',
				'type'=>'text',
				'name'=>'regex',
				'value'=>($attrdef ? $attrdef->getRegex() : ''),
			)
		);
		$this->formSubmit('<i class="icon-save"></i> '.getMLText('save'));
?>
			</form>
<?php
} /* }}} */

	function form() { /* {{{ */
		$selattrdef = $this->params['selattrdef'];

		$this->showAttributeForm($selattrdef);
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$attrdefs = $this->params['attrdefs'];
		$selattrdef = $this->params['selattrdef'];

		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/bootbox/bootbox.min.js"></script>'."\n", 'js');

		$this->htmlStartPage(getMLText("admin_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");
		$this->contentHeading(getMLText("attrdef_management"));
?>

<div class="row-fluid">
<div class="span6">
<form class="form-horizontal">
	<select class="chzn-select" id="selector" class="input-xlarge">
		<option value="-1"><?php echo getMLText("choose_attrdef")?></option>
		<option value="0"><?php echo getMLText("new_attrdef")?></option>
<?php
		if($attrdefs) {
			foreach ($attrdefs as $attrdef) {
				switch($attrdef->getObjType()) {
					case LetoDMS_Core_AttributeDefinition::objtype_all:
						$ot = getMLText("all");
						break;
					case LetoDMS_Core_AttributeDefinition::objtype_folder:
						$ot = getMLText("folder");
						break;
					case LetoDMS_Core_AttributeDefinition::objtype_document:
						$ot = getMLText("document");
						break;
					case LetoDMS_Core_AttributeDefinition::objtype_documentcontent:
						$ot = getMLText("documentcontent");
						break;
				}
				switch($attrdef->getType()) {
					case LetoDMS_Core_AttributeDefinition::type_int:
						$t = getMLText("attrdef_type_int");
						break;
					case LetoDMS_Core_AttributeDefinition::type_float:
						$t = getMLText("attrdef_type_float");
						break;
					case LetoDMS_Core_AttributeDefinition::type_string:
						$t = getMLText("attrdef_type_string");
						break;
					case LetoDMS_Core_AttributeDefinition::type_date:
						$t = getMLText("attrdef_type_date");
						break;
					case LetoDMS_Core_AttributeDefinition::type_boolean:
						$t = getMLText("attrdef_type_boolean");
						break;
				}
				print "<option value=\"".$attrdef->getID()."\" ".($selattrdef && $attrdef->getID()==$selattrdef->getID() ? 'selected' : '')." data-subtitle=\"".htmlspecialchars($ot.", ".$t)."\">" . htmlspecialchars($attrdef->getName()/* ." (".$ot.", ".$t.")"*/);
			}
		}
?>
	</select>
</form>
	<div class="ajax" style="margin-bottom: 15px;" data-view="AttributeMgr" data-action="actionmenu" <?php echo ($selattrdef ? "data-query=\"attrdefid=".$selattrdef->getID()."\"" : "") ?>></div>
	<div class="ajax" data-view="AttributeMgr" data-action="info" <?php echo ($selattrdef ? "data-query=\"attrdefid=".$selattrdef->getID()."\"" : "") ?>></div>
</div>

<div class="span6">
	<?php	$this->contentContainerStart(); ?>
		<div class="ajax" data-view="AttributeMgr" data-action="form" <?php echo ($selattrdef ? "data-query=\"attrdefid=".$selattrdef->getID()."\"" : "") ?>></div>
	<?php	$this->contentContainerEnd(); ?>
</div>

</div>

<?php
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
