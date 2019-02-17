<?php
if(isset($_SERVER['LetoDMS_HOME'])) {
	ini_set('include_path', $_SERVER['LetoDMS_HOME'].'/utils'. PATH_SEPARATOR .ini_get('include_path'));
} else {
	ini_set('include_path', dirname($argv[0]). PATH_SEPARATOR .ini_get('include_path'));
}

function usage() { /* {{{ */
	echo "Usage:\n";
	echo "  LetoDMS-adddoc [--config <file>] [-c <comment>] [-k <keywords>] [-s <number>] [-n <name>] [-V <version>] [-s <sequence>] [-t <mimetype>] [-a <attribute=value>] [-h] [-v] -F <folder id> -f <filename>\n";
	echo "\n";
	echo "Description:\n";
	echo "  This program uploads a file into a folder of LetoDMS.\n";
	echo "\n";
	echo "Options:\n";
	echo "  -h, --help: print usage information and exit.\n";
	echo "  -v, --version: print version and exit.\n";
	echo "  --config: set alternative config file.\n";
	echo "  -F <folder id>: id of folder the file is uploaded to\n";
	echo "  -c <comment>: set comment for document\n";
	echo "  -C <comment>: set comment for version\n";
	echo "  -k <keywords>: set keywords for file\n";
	echo "  -K <categories>: set categories for file\n";
	echo "  -s <number>: set sequence for file (used for ordering files within a folder\n";
	echo "  -n <name>: set name of file\n";
	echo "  -V <version>: set version of file (defaults to 1).\n";
	echo "  -u <user>: login name of user\n";
	echo "  -f <filename>: upload this file\n";
	echo "  -s <sequence>: set sequence of file\n";
	echo "  -t <mimetype> set mimetype of file manually. Do not do that unless you know\n";
	echo "      what you do. If not set, the mimetype will be determined automatically.\n";
	echo "  -a <attribute=value>: Set a document attribute; can occur multiple times.\n";
	echo "  -A <attribute=value>: Set a version attribute; can occur multiple times.\n";
} /* }}} */

$version = "0.0.1";
$shortoptions = "F:c:C:k:K:s:V:u:f:n:t:a:A:hv";
$longoptions = array('help', 'version', 'config:');
if(false === ($options = getopt($shortoptions, $longoptions))) {
	usage();
	exit(0);
}

/* Print help and exit */
if(isset($options['h']) || isset($options['help'])) {
	usage();
	exit(0);
}

/* Print version and exit */
if(isset($options['v']) || isset($options['verѕion'])) {
	echo $version."\n";
	exit(0);
}

/* Set alternative config file */
if(isset($options['config'])) {
	define('LetoDMS_CONFIG_FILE', $options['config']);
}

/* Set parent folder */
if(isset($options['F'])) {
	$folderid = (int) $options['F'];
} else {
	echo "Missing folder ID\n";
	usage();
	exit(1);
}

/* Set comment of document */
$comment = '';
if(isset($options['c'])) {
	$comment = $options['c'];
}

/* Set comment of version */
$version_comment = '';
if(isset($options['C'])) {
	$version_comment = $options['C'];
}

/* Set keywords */
$keywords = '';
if(isset($options['k'])) {
	$keywords = $options['k'];
}

$sequence = 0;
if(isset($options['s'])) {
	$sequence = $options['s'];
}

$name = '';
if(isset($options['n'])) {
	$name = $options['n'];
}

$username = '';
if(isset($options['u'])) {
	$username = $options['u'];
}

$filename = '';
if(isset($options['f'])) {
	$filename = $options['f'];
} else {
	usage();
	exit(1);
}

$mimetype = '';
if(isset($options['t'])) {
	$mimetype = $options['t'];
}

$reqversion = 0;
if(isset($options['V'])) {
	$reqversion = $options['V'];
}
if($reqversion<1)
	$reqversion=1;

include("../inc/inc.Settings.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.ClassNotificationService.php");
include("../inc/inc.ClassEmailNotify.php");
include("../inc/inc.ClassController.php");

/* Parse categories {{{ */
$categories = array();
if(isset($options['K'])) {
	$categorynames = explode(',', $options['K']);
	foreach($categorynames as $categoryname) {
		$cat = $dms->getDocumentCategoryByName($categoryname);
		if($cat) {
			$categories[] = $cat;
		} else {
			echo "Category '".$categoryname."' not found\n";
		}
	}
} /* }}} */

/* Parse document attributes. {{{ */
$document_attributes = array();
if (isset($options['a'])) {
	$docattr = array();
	if (is_array($options['a'])) {
		$docattr = $options['a'];
	} else {
		$docattr = array($options['a']);
	}

	foreach ($docattr as $thisAttribute) {
		$attrKey = strstr($thisAttribute, '=', true);
		$attrVal = substr(strstr($thisAttribute, '='), 1);
		if (empty($attrKey) || empty($attrVal)) {
			echo "Document attribute $thisAttribute not understood\n";
			exit(1);
		}
		$attrdef = $dms->getAttributeDefinitionByName($attrKey);
		if (!$attrdef) {
			echo "Document attribute $attrKey unknown\n";
			exit(1);
		}
		$document_attributes[$attrdef->getID()] = $attrVal;
	}
} /* }}} */

/* Parse version attributes. {{{ */
$version_attributes = array();
if (isset($options['A'])) {
	$verattr = array();
	if (is_array($options['A'])) {
		$verattr = $options['A'];
	} else {
		$verattr = array($options['A']);
	}

	foreach ($verattr as $thisAttribute) {
		$attrKey = strstr($thisAttribute, '=', true);
		$attrVal = substr(strstr($thisAttribute, '='), 1);
		if (empty($attrKey) || empty($attrVal)) {
			echo "Version attribute $thisAttribute not understood\n";
			exit(1);
		}
		$attrdef = $dms->getAttributeDefinitionByName($attrKey);
		if (!$attrdef) {
			echo "Version attribute $attrKey unknown\n";
			exit(1);
		}
		$version_attributes[$attrdef->getID()] = $attrVal;
	}
} /* }}} */

/* Create a global user object {{{ */
if($username) {
	if(!($user = $dms->getUserByLogin($username))) {
		echo "No such user '".$username."'.";
		exit;
	}
} else
	$user = $dms->getUser(1);

$dms->setUser($user);
/* }}} */

/* Create a global notifier object {{{ */
$notifier = new LetoDMS_NotificationService();

if(isset($GLOBALS['LetoDMS_HOOKS']['notification'])) {
	foreach($GLOBALS['LetoDMS_HOOKS']['notification'] as $notificationObj) {
		if(method_exists($notificationObj, 'preAddService')) {
			$notificationObj->preAddService($dms, $notifier);
		}
	}
}

if($settings->_enableEmail) {
	$notifier->addService(new LetoDMS_EmailNotify($dms, $settings->_smtpSendFrom, $settings->_smtpServer, $settings->_smtpPort, $settings->_smtpUser, $settings->_smtpPassword));
}

if(isset($GLOBALS['LetoDMS_HOOKS']['notification'])) {
	foreach($GLOBALS['LetoDMS_HOOKS']['notification'] as $notificationObj) {
		if(method_exists($notificationObj, 'postAddService')) {
			$notificationObj->postAddService($dms, $notifier);
		}
	}
}
/* }}} */

/* Check if file is readable {{{ */
if(is_readable($filename)) {
	if(filesize($filename)) {
		$finfo = new finfo(FILEINFO_MIME_TYPE);
		if(!$mimetype) {
			$mimetype = $finfo->file($filename);
		}
		$filetype = "." . pathinfo($filename, PATHINFO_EXTENSION);
	} else {
		echo "File has zero size\n";
		exit(1);
	}
} else {
	echo "File is not readable\n";
	exit(1);
}
/* }}} */

$folder = $dms->getFolder($folderid);

if (!is_object($folder)) {
	echo "Could not find specified folder\n";
	exit(1);
}

if ($folder->getAccessMode($user) < M_READWRITE) {
	echo "Not sufficient access rights\n";
	exit(1);
}

if (!is_numeric($sequence)) {
	echo "Sequence must be numeric\n";
	exit(1);
}

$expires = false;

if(!$name)
	$name = basename($filename);
$filetmp = $filename;

$reviewers = array();
$approvers = array();

if($settings->_enableFullSearch) {
	$index = $indexconf['Indexer']::open($settings->_luceneDir);
	$indexconf['Indexer']::init($settings->_stopWordsFile);
} else {
	$index = null;
	$indexconf = null;
}

$controller = Controller::factory('AddDocument', array('dms'=>$dms, 'user'=>$user));
$controller->setParam('documentsource', 'script');
$controller->setParam('folder', $folder);
$controller->setParam('index', $index);
$controller->setParam('indexconf', $indexconf);
$controller->setParam('name', $name);
$controller->setParam('comment', $comment);
$controller->setParam('expires', $expires);
$controller->setParam('keywords', $keywords);
$controller->setParam('categories', $categories);
$controller->setParam('owner', $user);
$controller->setParam('userfiletmp', $filetmp);
$controller->setParam('userfilename', basename($filename));
$controller->setParam('filetype', $filetype);
$controller->setParam('userfiletype', $mimetype);
$minmax = $folder->getDocumentsMinMax();
if($settings->_defaultDocPosition == 'start')
	$controller->setParam('sequence', $minmax['min'] - 1);
else
	$controller->setParam('sequence', $minmax['max'] + 1);
$controller->setParam('reviewers', $reviewers);
$controller->setParam('approvers', $approvers);
$controller->setParam('reqversion', $reqversion);
$controller->setParam('versioncomment', $version_comment);
$controller->setParam('attributes', $document_attributes);
$controller->setParam('attributesversion', $version_attributes);
$controller->setParam('workflow', null);
$controller->setParam('notificationgroups', array());
$controller->setParam('notificationusers', array());
$controller->setParam('maxsizeforfulltext', $settings->_maxSizeForFullText);
$controller->setParam('defaultaccessdocs', $settings->_defaultAccessDocs);

if(!$document = $controller->run()) {
	echo "Could not add document to folder\n";
	exit(1);
}
?>
