<?php
/**
 * Implementation of AddFile view
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
 * Class which outputs the html page for AddFile view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_AddFile extends LetoDMS_Bootstrap_Style {

	function js() { /* {{{ */
		$enablelargefileupload = $this->params['enablelargefileupload'];
		$partitionsize = $this->params['partitionsize'];
		$maxuploadsize = $this->params['maxuploadsize'];
		header('Content-Type: application/javascript');
		if($enablelargefileupload)
			$this->printFineUploaderJs('../op/op.UploadChunks.php', $partitionsize, $maxuploadsize);

		$this->printFileChooserJs();
?>

$(document).ready( function() {
	/* The fineuploader validation is actually checking all fields that can contain
	 * a file to be uploaded. First checks if an alternative input field is set,
	 * second loops through the list of scheduled uploads, checking if at least one
	 * file will be submitted.
	 */
	jQuery.validator.addMethod("fineuploader", function(value, element, params) {
		uploader = params[0];
		arr = uploader.getUploads();
		for(var i in arr) {
			if(arr[i].status == 'submitted')
				return true;
		}
		return false;
	}, "<?php printMLText("js_no_file");?>");
	$("#form1").validate({
		debug: false,
		ignore: ":hidden:not(.do_validate)",
		invalidHandler: function(e, validator) {
			noty({
				text:  (validator.numberOfInvalids() == 1) ? "<?php printMLText("js_form_error");?>".replace('#', validator.numberOfInvalids()) : "<?php printMLText("js_form_errors");?>".replace('#', validator.numberOfInvalids()),
				type: 'error',
				dismissQueue: true,
				layout: 'topRight',
				theme: 'defaultTheme',
				timeout: 1500,
			});
		},
		highlight: function(e, errorClass, validClass) {
			$(e).parent().parent().removeClass(validClass).addClass(errorClass);
		},
		unhighlight: function(e, errorClass, validClass) {
			$(e).parent().parent().removeClass(errorClass).addClass(validClass);
		},
<?php
		if($enablelargefileupload) {
?>
		submitHandler: function(form) {
			userfileuploader.uploadStoredFiles();
		},
<?php
		}
?>
		rules: {
<?php
		if($enablelargefileupload) {
?>
			fineuploaderuuids: {
				fineuploader: [ userfileuploader ]
			}
<?php
		} else {
?>
			'userfile[]': {
				required: true
			}
<?php
		}
?>
		},
		messages: {
			name: "<?php printMLText("js_no_name");?>",
			comment: "<?php printMLText("js_no_comment");?>",
			'userfile[]': "<?php printMLText("js_no_file");?>"
		},
		errorPlacement: function( error, element ) {
			if ( element.is( ":file" ) ) {
				error.appendTo( element.parent().parent().parent());
			} else {
				error.appendTo( element.parent());
			}
		}
	});
});
<?php
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$document = $this->params['document'];
		$strictformcheck = $this->params['strictformcheck'];
		$enablelargefileupload = $this->params['enablelargefileupload'];

		$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/validate/jquery.validate.js"></script>'."\n", 'js');
		if($enablelargefileupload) {
			$this->htmlAddHeader('<script type="text/javascript" src="../styles/'.$this->theme.'/fine-uploader/jquery.fine-uploader.min.js"></script>'."\n", 'js');
			$this->htmlAddHeader($this->getFineUploaderTemplate(), 'js');
		}

		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document", $document);

		$this->contentHeading(getMLText("linked_files"));
?>
<div class="alert alert-warning">
<?php echo getMLText("max_upload_size").": ".ini_get( "upload_max_filesize"); ?>
<?php
	if(0 && $enablelargefileupload) {
  	printf('<p>'.getMLText('link_alt_updatedocument').'</p>', "out.AddFile2.php?documentid=".$document->getId());
	}
?>
</div>
<?php
		$this->contentContainerStart();
?>

<form class="form-horizontal" action="../op/op.AddFile.php" enctype="multipart/form-data" method="post" name="form1" id="form1">
<input type="hidden" name="documentid" value="<?php print $document->getId(); ?>">
<?php
		$this->formField(
			getMLText("local_file"),
			($enablelargefileupload ? $this->getFineUploaderHtml() : $this->getFileChooserHtml('userfile[]', false))
		);
		$options = array();
		$options[] = array("", getMLText('document'));
		$versions = $document->getContent();
		foreach($versions as $version) {
			$options[] = array($version->getVersion(), getMLText('version')." ".$version->getVersion());
		}
		$this->formField(
			getMLText("version"),
			array(
				'element'=>'select',
				'id'=>'version',
				'name'=>'version',
				'options'=>$options
			)
		);
		$this->formField(
			getMLText("name"),
			array(
				'element'=>'input',
				'type'=>'text',
				'id'=>'name',
				'name'=>'name',
			)
		);
		$this->formField(
			getMLText("comment"),
			array(
				'element'=>'textarea',
				'id'=>'comment',
				'name'=>'comment',
				'rows'=>4,
				'cols'=>80,
				'required'=>$strictformcheck
			)
		);
		if ($document->getAccessMode($user) >= M_READWRITE) {
			$this->formField(
				getMLText("document_link_public"),
				array(
					'element'=>'input',
					'type'=>'checkbox',
					'id'=>'public',
					'name'=>'public',
					'value'=>'true',
					'checked'=>true,
				)
			);
		}
		$this->formSubmit(getMLText('add'));
?>
</form>
<?php
		$this->contentContainerEnd();
		$this->contentEnd();
		$this->htmlEndPage();

	} /* }}} */
}
?>
