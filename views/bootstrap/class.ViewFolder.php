<?php
/**
 * Implementation of ViewFolder view
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
 * Class which outputs the html page for ViewFolder view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_ViewFolder extends LetoDMS_Bootstrap_Style {

	function getAccessModeText($defMode) { /* {{{ */
		switch($defMode) {
			case M_NONE:
				return getMLText("access_mode_none");
				break;
			case M_READ:
				return getMLText("access_mode_read");
				break;
			case M_READWRITE:
				return getMLText("access_mode_readwrite");
				break;
			case M_ALL:
				return getMLText("access_mode_all");
				break;
		}
	} /* }}} */

	function printAccessList($obj) { /* {{{ */
		$accessList = $obj->getAccessList();
		if (count($accessList["users"]) == 0 && count($accessList["groups"]) == 0)
			return;

		$content = '';
		for ($i = 0; $i < count($accessList["groups"]); $i++)
		{
			$group = $accessList["groups"][$i]->getGroup();
			$accesstext = $this->getAccessModeText($accessList["groups"][$i]->getMode());
			$content .= $accesstext.": ".htmlspecialchars($group->getName());
			if ($i+1 < count($accessList["groups"]) || count($accessList["users"]) > 0)
				$content .= "<br />";
		}
		for ($i = 0; $i < count($accessList["users"]); $i++)
		{
			$user = $accessList["users"][$i]->getUser();
			$accesstext = $this->getAccessModeText($accessList["users"][$i]->getMode());
			$content .= $accesstext.": ".htmlspecialchars($user->getFullName());
			if ($i+1 < count($accessList["users"]))
				$content .= "<br />";
		}

		if(count($accessList["groups"]) + count($accessList["users"]) > 3) {
			$this->printPopupBox(getMLText('list_access_rights'), $content);
		} else {
			echo $content;
		}
	} /* }}} */

	function js() { /* {{{ */
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$orderby = $this->params['orderby'];
		$expandFolderTree = $this->params['expandFolderTree'];
		$enableDropUpload = $this->params['enableDropUpload'];
		$maxItemsPerPage = $this->params['maxItemsPerPage'];

		header('Content-Type: application/javascript; charset=UTF-8');
		parent::jsTranslations(array('cancel', 'splash_move_document', 'confirm_move_document', 'move_document', 'splash_move_folder', 'confirm_move_folder', 'move_folder'));
?>
function folderSelected(id, name) {
	window.location = '../out/out.ViewFolder.php?folderid=' + id;
}
<?php if($maxItemsPerPage) { ?>
function loadMoreObjects(element, limit) {
	if(!$(element).is(":visible"))
		return;
	element.text('<?= getMLText('more_objects_loading') ?>');
	element.prop("disabled",true);
	var folder = element.data('folder')
	var offset = element.data('offset')
//	var limit = element.data('limit')
	url = LetoDMS_webroot+"out/out.ViewFolder.php?action=entries&folderid="+folder+"&offset="+offset+"&limit="+limit<?= $orderby ? '+"&orderby='.$orderby.'"' : "" ?>;
	$.ajax({
		type: 'GET',
		url: url,
		dataType: 'json',
		success: function(data){
			$('#viewfolder-table').append(data.html);
			if(data.count <= 0) {
				element.hide();
			} else {
				var str = '<?= getMLText('x_more_objects') ?>';
				element.text(str.replace('[number]', data.count));
				element.data('offset', offset+limit);
				element.prop("disabled",false);
			}
		}
	});
}
$(window).scroll(function() {
	if($(window).scrollTop() + $(window).height() == $(document).height()) {
		loadMoreObjects($('#loadmore'), $('#loadmore').data('limit'));
	}
});
$('#loadmore').click(function(e) {
	loadMoreObjects($(this), $(this).data('all'));
});
<?php } ?>
<?php
		$this->printNewTreeNavigationJs($folder->getID(), M_READ, 0, '', $expandFolderTree == 2, $orderby);

		if ($enableDropUpload && $folder->getAccessMode($user) >= M_READWRITE) {
			echo "LetoDMSUpload.setUrl('../op/op.Ajax.php');";
			echo "LetoDMSUpload.setAbortBtnLabel('".getMLText("cancel")."');";
			echo "LetoDMSUpload.setEditBtnLabel('".getMLText("edit_document_props")."');";
			echo "LetoDMSUpload.setMaxFileSize(".LetoDMS_Core_File::parse_filesize(ini_get("upload_max_filesize")).");";
			echo "LetoDMSUpload.setMaxFileSizeMsg('".getMLText("uploading_maxsize")."');";
		}

		$this->printDeleteFolderButtonJs();
		$this->printDeleteDocumentButtonJs();
	} /* }}} */

	function entries() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$orderby = $this->params['orderby'];
		$cachedir = $this->params['cachedir'];
		$previewwidth = $this->params['previewWidthList'];
		$previewconverters = $this->params['previewConverters'];
		$timeout = $this->params['timeout'];
		$offset = $this->params['offset'];
		$limit = $this->params['limit'];

		header('Content-Type: application/json');

		$previewer = new LetoDMS_Preview_Previewer($cachedir, $previewwidth, $timeout);
		$previewer->setConverters($previewconverters);

		$subFolders = $this->callHook('folderGetSubFolders', $folder, $orderby);
		if($subFolders === null)
			$subFolders = $folder->getSubFolders($orderby);
		$subFolders = LetoDMS_Core_DMS::filterAccess($subFolders, $user, M_READ);
		$documents = $this->callHook('folderGetDocuments', $folder, $orderby);
		if($documents === null)
			$documents = $folder->getDocuments($orderby);
		$documents = LetoDMS_Core_DMS::filterAccess($documents, $user, M_READ);

		$content = '';
		if ((count($subFolders) > 0)||(count($documents) > 0)){
			$i = 0; // counts all entries
			$j = 0; // counts only returned entries
			foreach($subFolders as $subFolder) {
				if($i >= $offset && $j < $limit) {
					$txt = $this->callHook('folderListItem', $subFolder, 'viewfolder');
					if(is_string($txt))
						$content .= $txt;
					else {
						$content .= $this->folderListRow($subFolder);
					}
					$j++;
				}
				$i++;
			}

			if($subFolders && $documents) {
				if(($j && $j < $limit) || ($offset + $limit == $i)) {
					$txt = $this->callHook('folderListSeparator', $folder);
					if(is_string($txt))
						$content .= $txt;
				}
			}

			foreach($documents as $document) {
				if($i >= $offset && $j < $limit) {
					$document->verifyLastestContentExpriry();
					$txt = $this->callHook('documentListItem', $document, $previewer, false, 'viewfolder');
					if(is_string($txt))
						$content .= $txt;
					else {
						$content .= $this->documentListRow($document, $previewer);
					}
					$j++;
				}
				$i++;
			}

			echo json_encode(array('error'=>0, 'count'=>$i-($offset+$limit), 'html'=>$content));
		}

	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$orderby = $this->params['orderby'];
		$enableFolderTree = $this->params['enableFolderTree'];
		$enableClipboard = $this->params['enableclipboard'];
		$enableDropUpload = $this->params['enableDropUpload'];
		$expandFolderTree = $this->params['expandFolderTree'];
		$showtree = $this->params['showtree'];
		$cachedir = $this->params['cachedir'];
		$workflowmode = $this->params['workflowmode'];
		$enableRecursiveCount = $this->params['enableRecursiveCount'];
		$maxRecursiveCount = $this->params['maxRecursiveCount'];
		$maxItemsPerPage = $this->params['maxItemsPerPage'];
		$incItemsPerPage = $this->params['incItemsPerPage'];
		$previewwidth = $this->params['previewWidthList'];
		$previewconverters = $this->params['previewConverters'];
		$timeout = $this->params['timeout'];

		$folderid = $folder->getId();

		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/bootbox/bootbox.min.js"></script>'."\n", 'js');

		echo $this->callHook('startPage');
		$this->htmlStartPage(getMLText("folder_title", array("foldername" => htmlspecialchars($folder->getName()))));

		$this->globalNavigation($folder);
		$this->contentStart();
		$txt = $this->callHook('folderMenu', $folder);
		if(is_string($txt))
			echo $txt;
		else {
			$this->pageNavigation($this->getFolderPathHTML($folder), "view_folder", $folder);
		}

		$previewer = new LetoDMS_Preview_Previewer($cachedir, $previewwidth, $timeout);
		$previewer->setConverters($previewconverters);

		echo $this->callHook('preContent');

		echo "<div class=\"row-fluid\">\n";

		// dynamic columns - left column removed if no content and right column then fills span12.
		if (!($enableFolderTree || $enableClipboard)) {
			$LeftColumnSpan = 0;
			$RightColumnSpan = 12;
		} else {
			$LeftColumnSpan = 4;
			$RightColumnSpan = 8;
		}
		if ($LeftColumnSpan > 0) {
			echo "<div class=\"span".$LeftColumnSpan."\">\n";
			if ($enableFolderTree) {
				if ($showtree==1){
					$this->contentHeading("<a href=\"../out/out.ViewFolder.php?folderid=". $folderid."&showtree=0\"><i class=\"icon-minus-sign\"></i></a>", true);
					$this->contentContainerStart();
					/*
					 * access expandFolderTree with $this->params because it can
					 * be changed by preContent hook.
					 */
					$this->printNewTreeNavigationHtml($folderid, M_READ, 0, '', $this->params['expandFolderTree'] == 2, $orderby);
					$this->contentContainerEnd();
				} else {
					$this->contentHeading("<a href=\"../out/out.ViewFolder.php?folderid=". $folderid."&showtree=1\"><i class=\"icon-plus-sign\"></i></a>", true);
				}
			}

			echo $this->callHook('leftContent');

			if ($enableClipboard) $this->printClipboard($this->params['session']->getClipboard(), $previewer);

			echo "</div>\n";
		}
		echo "<div class=\"span".$RightColumnSpan."\">\n";

		if ($enableDropUpload && $folder->getAccessMode($user) >= M_READWRITE) {
			echo "<div class=\"row-fluid\">";
			echo "<div class=\"span8\">";
		}
		$txt = $this->callHook('folderInfo', $folder);
		if(is_string($txt))
			echo $txt;
		else {

			$owner = $folder->getOwner();
			$this->contentHeading(getMLText("folder_infos"));
			$this->contentContainerStart();
			echo "<table class=\"table-condensed\">\n";
			if($user->isAdmin()) {
				echo "<tr>";
				echo "<td>".getMLText("id").":</td>\n";
				echo "<td>".htmlspecialchars($folder->getID())."</td>\n";
				echo "</tr>";
			}
			echo "<tr>";
			echo "<td>".getMLText("owner").":</td>\n";
			echo "<td><a href=\"mailto:".htmlspecialchars($owner->getEmail())."\">".htmlspecialchars($owner->getFullName())."</a></td>\n";
			echo "</tr>";
			echo "<tr>";
			echo "<td>".getMLText("creation_date").":</td>";
			echo "<td>".getLongReadableDate($folder->getDate())."</td>";
			echo "</tr>";
			if($folder->getComment()) {
				echo "<tr>";
				echo "<td>".getMLText("comment").":</td>\n";
				echo "<td>".htmlspecialchars($folder->getComment())."</td>\n";
				echo "</tr>";
			}

			if($user->isAdmin()) {
				echo "<tr>";
				echo "<td>".getMLText('default_access').":</td>";
				echo "<td>".$this->getAccessModeText($folder->getDefaultAccess())."</td>";
				echo "</tr>";
				if($folder->inheritsAccess()) {
					echo "<tr>";
					echo "<td>".getMLText("access_mode").":</td>\n";
					echo "<td>";
					echo getMLText("inherited")."<br />";
					$this->printAccessList($folder);
					echo "</tr>";
				} else {
					echo "<tr>";
					echo "<td>".getMLText('access_mode').":</td>";
					echo "<td>";
					$this->printAccessList($folder);
					echo "</td>";
					echo "</tr>";
				}
			}
			$attributes = $folder->getAttributes();
			if($attributes) {
				foreach($attributes as $attribute) {
					$arr = $this->callHook('showFolderAttribute', $folder, $attribute);
					if(is_array($arr)) {
						echo $txt;
						echo "<tr>";
						echo "<td>".$arr[0].":</td>";
						echo "<td>".$arr[1].":</td>";
						echo "</tr>";
					} else {
						$attrdef = $attribute->getAttributeDefinition();
			?>
					<tr>
					<td><?php echo htmlspecialchars($attrdef->getName()); ?>:</td>
					<td><?php echo htmlspecialchars(implode(', ', $attribute->getValueAsArray())); ?></td>
					</tr>
<?php
					}
				}
			}
			echo "</table>\n";
			$this->contentContainerEnd();
		}
		if ($enableDropUpload && $folder->getAccessMode($user) >= M_READWRITE) {
			echo "</div>";
			echo "<div class=\"span4\">";
			$this->contentHeading(getMLText("dropupload"), true);
//			$this->addFooterJS("LetoDMSUpload.setUrl('../op/op.Ajax.php');");
//			$this->addFooterJS("LetoDMSUpload.setAbortBtnLabel('".getMLText("cancel")."');");
//			$this->addFooterJS("LetoDMSUpload.setEditBtnLabel('".getMLText("edit_document_props")."');");
//			$this->addFooterJS("LetoDMSUpload.setMaxFileSize(".LetoDMS_Core_File::parse_filesize(ini_get("upload_max_filesize")).");");
//			$this->addFooterJS("LetoDMSUpload.setMaxFileSizeMsg('".getMLText("uploading_maxsize")."');");
?>
<div id="dragandrophandler" class="well alert" data-target="<?php echo $folder->getID(); ?>" data-formtoken="<?php echo createFormKey('adddocument'); ?>"><?php printMLText('drop_files_here'); ?></div>
<?php
			echo "</div>";
			echo "</div>";
		}

		$txt = $this->callHook('listHeader', $folder);
		if(is_string($txt))
			echo $txt;
		else
			$this->contentHeading(getMLText("folder_contents"));

		$subFolders = $this->callHook('folderGetSubFolders', $folder, $orderby);
		if($subFolders === null)
			$subFolders = $folder->getSubFolders($orderby);
		$subFolders = LetoDMS_Core_DMS::filterAccess($subFolders, $user, M_READ);
		$documents = $this->callHook('folderGetDocuments', $folder, $orderby);
		if($documents === null)
			$documents = $folder->getDocuments($orderby);
		$documents = LetoDMS_Core_DMS::filterAccess($documents, $user, M_READ);

		$txt = $this->callHook('folderListPreContent', $folder, $subFolders, $documents);
		if(is_string($txt))
			echo $txt;
		$i = 0;
		if ((count($subFolders) > 0)||(count($documents) > 0)){
			$txt = $this->callHook('folderListHeader', $folder, $orderby);
			if(is_string($txt))
				echo $txt;
			else {
				print "<table id=\"viewfolder-table\" class=\"table table-condensed table-hover\">";
				print "<thead>\n<tr>\n";
				print "<th></th>\n";	
				print "<th><a href=\"../out/out.ViewFolder.php?folderid=". $folderid .($orderby=="n"?"&orderby=s":"&orderby=n")."\">".getMLText("name")."</a></th>\n";
	//			print "<th>".getMLText("owner")."</th>\n";
				print "<th>".getMLText("status")."</th>\n";
	//			print "<th>".getMLText("version")."</th>\n";
				print "<th>".getMLText("action")."</th>\n";
				print "</tr>\n</thead>\n<tbody>\n";
			}

			foreach($subFolders as $subFolder) {
				if(!$maxItemsPerPage || $i < $maxItemsPerPage) {
					$txt = $this->callHook('folderListItem', $subFolder, 'viewfolder');
					if(is_string($txt))
						echo $txt;
					else {
						echo $this->folderListRow($subFolder);
					}
				}
				$i++;
			}

			if($subFolders && $documents) {
				if(!$maxItemsPerPage || $maxItemsPerPage > count($subFolders)) {
					$txt = $this->callHook('folderListSeparator', $folder);
					if(is_string($txt))
						echo $txt;
				}
			}

			foreach($documents as $document) {
				if(!$maxItemsPerPage || $i < $maxItemsPerPage) {
					$document->verifyLastestContentExpriry();
					$txt = $this->callHook('documentListItem', $document, $previewer, false, 'viewfolder');
					if(is_string($txt))
						echo $txt;
					else {
						echo $this->documentListRow($document, $previewer);
					}
				}
				$i++;
			}

			$txt = $this->callHook('folderListFooter', $folder);
			if(is_string($txt))
				echo $txt;
			else
				echo "</tbody>\n</table>\n";

			if($maxItemsPerPage && $i > $maxItemsPerPage)
				echo "<button id=\"loadmore\" style=\"width: 100%; margin-bottom: 20px;\" class=\"btn btn-default\" data-folder=\"".$folder->getId()."\"data-offset=\"".$maxItemsPerPage."\" data-limit=\"".$incItemsPerPage."\" data-all=\"".($i-$maxItemsPerPage)."\">".getMLText('x_more_objects', array('number'=>($i-$maxItemsPerPage)))."</button>";
		}
		else printMLText("empty_folder_list");

		$txt = $this->callHook('folderListPostContent', $folder, $subFolders, $documents);
		if(is_string($txt))
			echo $txt;

		echo "</div>\n"; // End of right column div
		echo "</div>\n"; // End of div around left and right column

		echo $this->callHook('postContent');

		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}

?>
