<?php
/**
 * Implementation of CategoryChooser view
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
 * Class which outputs the html page for CategoryChooser view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_DropFolderChooser extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript');
?>
$('.fileselect').click(function(ev) {
	attr_filename = $(ev.currentTarget).attr('filename');
	fileSelected(attr_filename);
});
$('.folderselect').click(function(ev) {
	attr_foldername = $(ev.currentTarget).attr('foldername');
	folderSelected(attr_foldername);
});
<?php
	} /* }}} */

	public function menuList() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$dropfolderdir = $this->params['dropfolderdir'];
		$showfolders = $this->params['showfolders'];
		$cachedir = $this->params['cachedir'];
		$previewwidth = $this->params['previewWidthMenuList'];
		$timeout = $this->params['timeout'];
		$folderid = isset($_GET['folderid']) ? $_GET['folderid'] : 0;

		$previewer = new LetoDMS_Preview_Previewer($cachedir, $previewwidth, $timeout);

		$c = 0; // count files
		$filecontent = '';
		$dir = rtrim($dropfolderdir, '/').'/'.$user->getLogin();
		/* Check if we are still looking in the configured directory and
		 * not somewhere else, e.g. if the login was '../test'
		 */
		if(dirname($dir) == $dropfolderdir) {
			if(is_dir($dir)) {
				$d = dir($dir);

				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				while (false !== ($entry = $d->read())) {
					if($entry != '..' && $entry != '.') {
						if($showfolders == 0 && !is_dir($dir.'/'.$entry)) {
							$c++;
							$mimetype = finfo_file($finfo, $dir.'/'.$entry);
							$filecontent .= "<li><a".($folderid ? " href=\"../out/out.AddDocument.php?folderid=".$folderid."&dropfolderfileform1=".urldecode($entry)."\" title=\"".getMLText('menu_upload_from_dropfolder')."\"" : "").">";
							if($previewwidth) {
								$previewer->createRawPreview($dir.'/'.$entry, 'dropfolder/', $mimetype);
								if($previewer->hasRawPreview($dir.'/'.$entry, 'dropfolder/')) {
									$filecontent .= "<div style=\"float: left; display:inline; width:40px; max-height:40px;overflow:hidden;\"><img filename=\"".$entry."\" width=\"".$previewwidth."\" src=\"../op/op.DropFolderPreview.php?filename=".$entry."&width=".$previewwidth."\" title=\"".htmlspecialchars($mimetype)."\"></div>";
								}
							}
							$filecontent .= "<div style=\"margin-left:10px; margin-right: 40px; display:inline-block;\">".$entry."<br /><span style=\"font-size: 85%;\">".LetoDMS_Core_File::format_filesize(filesize($dir.'/'.$entry)).", ".date('Y-m-d H:i:s', filectime($dir.'/'.$entry))."</span></div></a></li>\n";
						} elseif($showfolders && is_dir($dir.'/'.$entry)) {
							$filecontent .= "<li><a _href=\"\">".$entry."</a></li>";
						}
					}
				}
			}
		}
		$content = '';
		if($c) {
			$content .= "   <ul id=\"main-menu-dropfolderlist\" class=\"nav pull-right\">\n";
			$content .= "    <li class=\"dropdown add-dropfolderlist-area\">\n";
			$content .= "     <a href=\"#\" class=\"dropdown-toggle\" data-toggle=\"dropdown\" class=\"add-dropfolderlist-area\">".getMLText('menu_dropfolder')." (".$c.") <i class=\"icon-caret-down\"></i></a>\n";
			$content .= "     <ul class=\"dropdown-menu\" role=\"menu\">\n";
			$content .= $filecontent;
			$content .= "     </ul>\n";
			$content .= "    </li>\n";
			$content .= "   </ul>\n";
		}
		echo $content;
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$dropfolderfile = $this->params['dropfolderfile'];
		$form = $this->params['form'];
		$dropfolderdir = $this->params['dropfolderdir'];
		$cachedir = $this->params['cachedir'];
		$previewwidth = $this->params['previewWidthList'];
		$timeout = $this->params['timeout'];
		$showfolders = $this->params['showfolders'];

		$previewer = new LetoDMS_Preview_Previewer($cachedir, $previewwidth, $timeout);

		$dir = $dropfolderdir.'/'.$user->getLogin();
		/* Check if we are still looking in the configured directory and
		 * not somewhere else, e.g. if the login was '../test'
		 */
		if(dirname($dir) == $dropfolderdir) {
			if(is_dir($dir)) {
				$d = dir($dir);
				echo "<table class=\"table table-condensed\">\n";
				echo "<thead>\n";
				echo "<tr><th></th><th>".getMLText('name')."</th><th align=\"right\">".getMLText('file_size')."</th><th>".getMLText('date')."</th></tr>\n";
				echo "</thead>\n";
				echo "<tbody>\n";
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				while (false !== ($entry = $d->read())) {
					if($entry != '..' && $entry != '.') {
						if($showfolders == 0 && !is_dir($dir.'/'.$entry)) {
							$mimetype = finfo_file($finfo, $dir.'/'.$entry);
							$previewer->createRawPreview($dir.'/'.$entry, 'dropfolder/', $mimetype);
							echo "<tr><td style=\"min-width: ".$previewwidth."px;\">";
							if($previewer->hasRawPreview($dir.'/'.$entry, 'dropfolder/')) {
								echo "<img style=\"cursor: pointer;\" class=\"fileselect mimeicon\" filename=\"".$entry."\" width=\"".$previewwidth."\" src=\"../op/op.DropFolderPreview.php?filename=".$entry."&width=".$previewwidth."\" title=\"".htmlspecialchars($mimetype)."\">";
							}
							echo "</td><td><span style=\"cursor: pointer;\" class=\"fileselect\" filename=\"".$entry."\">".$entry."</span></td><td align=\"right\">".LetoDMS_Core_File::format_filesize(filesize($dir.'/'.$entry))."</td><td>".date('Y-m-d H:i:s', filectime($dir.'/'.$entry))."</td></tr>\n";
						} elseif($showfolders && is_dir($dir.'/'.$entry)) {
							echo "<tr>";
							echo "<td></td>";
							echo "<td><span style=\"cursor: pointer;\" class=\"folderselect\" foldername=\"".$entry."\" >".$entry."</span></td><td align=\"right\"></td><td></td>";
							echo "</tr>\n";
						}
					}
				}
				echo "</tbody>\n";
				echo "</table>\n";
				echo '<script src="../out/out.DropFolderChooser.php?action=js&'.$_SERVER['QUERY_STRING'].'"></script>'."\n";
			} else {
				echo "<div class=\"alert alert-danger\">".getMLText('invalid_dropfolder_folder')."</div>";
			}
		}
	} /* }}} */
}
?>
