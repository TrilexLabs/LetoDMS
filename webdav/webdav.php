<?php

require_once "HTTP/WebDAV/Server.php";

/**
 * LetoDMS access using WebDAV
 *
 * @access  public
 * @author  Uwe Steinmann <steinm@php.net>
 * @version @package-version@
 */
class HTTP_WebDAV_Server_LetoDMS extends HTTP_WebDAV_Server
{
	/**
	 * A reference of the DMS itself
	 *
	 * This is set by ServeRequest
	 *
	 * @access private
	 * @var	object
	 */
	var $dms = null;

	/**
	 * A reference to a logger
	 *
	 * This is set by ServeRequest
	 *
	 * @access private
	 * @var	object
	 */
	var $logger = null;

	/**
	 * Currently logged in user
	 *
	 * @access private
	 * @var	string
	 */
	var $user = "";

	/**
	 * Set to true if original file shall be used instead of document name
	 *
	 * @access private
	 * @var	boolean
	 */
	var $useorgfilename = false;

	/**
	 * Serve a webdav request
	 *
	 * @access public
	 * @param  object $dms reference to DMS
	 */
	function ServeRequest($dms = null, $logger = null) /* {{{ */
	{
		// set root directory, defaults to webserver document root if not set
		if ($dms) {
			$this->dms = $dms;
		} else {
			return false;
		}

		// set logger
		$this->logger = $logger;

		// special treatment for litmus compliance test
		// reply on its identifier header
		// not needed for the test itself but eases debugging
		if( function_exists('apache_request_headers') ) {
			foreach (apache_request_headers() as $key => $value) {
				if (stristr($key, "litmus")) {
					if($this->logger)
						$this->logger->log('Litmus test '.$value, PEAR_LOG_DEBUG);
					header("X-Litmus-reply: ".$value);
				}
			}
		}

		// let the base class do all the work
		parent::ServeRequest();
	} /* }}} */

	/**
	 * Log array of options as passed to most functions
	 *
	 * @access private
	 * @param  string webdav methode that was called
	 * @param  array  options
	 */
	function log_options($methode, $options) { /* {{{ */
		if($this->logger) {
			switch($methode) {
			case 'MOVE':
				$msg = $methode.': '.$options['path'].' -> '.$options['dest'];
				break;
			default:
				$msg = $methode.': '.$options['path'];
			}
			$this->logger->log($msg, PEAR_LOG_INFO);
			foreach($options as $key=>$option) {
				if(is_array($option)) {
					$this->logger->log($methode.': '.$key.'='.var_export($option, true), PEAR_LOG_DEBUG);
				} else {
					$this->logger->log($methode.': '.$key.'='.$option, PEAR_LOG_DEBUG);
				}
			}
		}
	} /* }}} */

	/**
	 * No authentication is needed here
	 *
	 * @access private
	 * @param  string  HTTP Authentication type (Basic, Digest, ...)
	 * @param  string  Username
	 * @param  string  Password
	 * @return bool	true on successful authentication
	 */
	function check_auth($type, $user, $pass) /* {{{ */
	{
		global $settings;

		if($this->logger)
			$this->logger->log('check_auth: type='.$type.', user='.$user.'', PEAR_LOG_INFO);

		$userobj = false;

		/* Authenticate against LDAP server {{{ */
		if (!$userobj && isset($settings->_ldapHost) && strlen($settings->_ldapHost)>0) {
			require_once("../inc/inc.ClassLdapAuthentication.php");
			$authobj = new LetoDMS_LdapAuthentication($this->dms, $settings);
			$userobj = $authobj->authenticate($user, $pass);
		} /* }}} */

		/* Authenticate against LetoDMS database {{{ */
		if(!$userobj) {
			require_once("../inc/inc.ClassDbAuthentication.php");
			$authobj = new LetoDMS_DbAuthentication($this->dms, $settings);
			$userobj = $authobj->authenticate($user, $pass);
		} /* }}} */

		if(!$userobj) {
			if($this->logger)
				$this->logger->log('check_auth: No such user', PEAR_LOG_NOTICE);
			return false;
		}

		if(($userobj->getID() == $settings->_guestID) && (!$settings->_enableGuestLogin)) {
			if($this->logger)
				$this->logger->log('check_auth: Login as guest is not allowed', PEAR_LOG_NOTICE);
			return false;
		}

		if($userobj->isDisabled())
			return false;

		if($userobj->isAdmin() && ($_SERVER['REMOTE_ADDR'] != $settings->_adminIP ) && ( $settings->_adminIP != ""))
			return false;

		$this->user = $userobj;

		return true;
	} /* }}} */


	/**
	 * Get the object id from its path
	 *
	 * @access private
	 * @param  string  path
	 * @return bool/object object with given path or false on error
	 */
	function reverseLookup($path) /* {{{ */
	{
		$path = rawurldecode($path);
		if($this->logger)
			$this->logger->log('reverseLookup: path='.$path.'', PEAR_LOG_DEBUG);

		$root = $this->dms->getRootFolder();
		if($path[0] == '/') {
			$path = substr($path, 1);
		}
		$patharr = explode('/', $path);
		/* The last entry is always the document, though if the path ends
		 * in '/', the document name will be empty.
		 */
		$docname = array_pop($patharr);
		$parentfolder = $root;

		if(!$patharr) {
			if(!$docname) {
				if($this->logger)
					$this->logger->log('reverseLookup: found folder '.$root->getName().' ('.$root->getID().')', PEAR_LOG_DEBUG);
				return $root;
			} else {
				if($this->useorgfilename)
					$document = $this->dms->getDocumentByOriginalFilename($docname, $root);
				else
					$document = $this->dms->getDocumentByName($docname, $root);
				if($document) {
					if($this->logger)
						$this->logger->log('reverseLookup: found document '.$document->getName().' ('.$document->getID().')', PEAR_LOG_DEBUG);
					return $document;
				} else {
					return false;
				}
			}
		}

		foreach($patharr as $pathseg) {
			if($folder = $this->dms->getFolderByName($pathseg, $parentfolder)) {
				$parentfolder = $folder;
			}
		}
		if($folder) {
			if($docname) {
				if($this->useorgfilename)
					$document = $this->dms->getDocumentByOriginalFilename($docname, $folder);
				else
					$document = $this->dms->getDocumentByName($docname, $folder);
				if($document) {
					if($this->logger)
						$this->logger->log('reverseLookup: found document '.$document->getName().' ('.$document->getID().')', PEAR_LOG_DEBUG);
					return $document;
				} else {
					if($this->logger)
						$this->logger->log('reverseLookup: nothing found', PEAR_LOG_DEBUG);
					return false;
				}
			} else {
				if($this->logger)
					$this->logger->log('reverseLookup: found folder '.$folder->getName().' ('.$folder->getID().')', PEAR_LOG_DEBUG);
				return $folder;
			}
		} else {
			if($this->logger)
				$this->logger->log('reverseLookup: nothing found', PEAR_LOG_DEBUG);
			return false;
		}
		if($this->logger)
			$this->logger->log('reverseLookup: nothing found', PEAR_LOG_DEBUG);
		return false;
	} /* }}} */


	/**
	 * PROPFIND method handler
	 *
	 * @param  array  general parameter passing array
	 * @param  array  return array for file properties
	 * @return bool   true on success
	 */
	function PROPFIND(&$options, &$files) /* {{{ */
	{
		$this->log_options('PROFIND', $options);

		// get folder or document from path
		$obj = $this->reverseLookup($options["path"]);

		// sanity check
		if (!$obj) {
			$obj = $this->reverseLookup($options["path"].'/');
			if(!$obj)
				return false;
		}

		// prepare property array
		$files["files"] = array();

		// store information for the requested path itself
		$files["files"][] = $this->fileinfo($obj);

		// information for contained resources requested?
		if (get_class($obj) == $this->dms->getClassname('folder') && !empty($options["depth"])) {

			$subfolders = $obj->getSubFolders();
			$subfolders = LetoDMS_Core_DMS::filterAccess($subfolders, $this->user, M_READ);
			if ($subfolders) {
				// ok, now get all its contents
				foreach($subfolders as $subfolder) {
					$files["files"][] = $this->fileinfo($subfolder);
				}
				// TODO recursion needed if "Depth: infinite"
			}
			$documents = $obj->getDocuments();
			$docs = LetoDMS_Core_DMS::filterAccess($documents, $this->user, M_READ);
			if(!$this->user->isAdmin()) {
				$documents = array();
				foreach($docs as $document) {
					$lc = $document->getLatestContent();
					$status = $lc->getStatus();
					if($status['status'] == S_RELEASED) {
						$documents[] = $document;
					}
				}
			} else {
				$documents = $docs;
			}
			if ($documents) {
				// ok, now get all its contents
				foreach($documents as $document) {
					$files["files"][] = $this->fileinfo($document);
				}
			}
		}

		// ok, all done
		return true;
	} /* }}} */

	/**
	 * Get properties for a single file/resource
	 *
	 * @param  string  resource path
	 * @return array   resource properties
	 */
	function fileinfo($obj) /* {{{ */
	{
		// create result array
		$info = array();
		$info["props"] = array();

		// type and size (caller already made sure that path exists)
		if (get_class($obj) == $this->dms->getClassname('folder')) {
			// modification time
			/* folders do not have a modification time */
			$info["props"][] = $this->mkprop("getlastmodified", $obj->getDate());
			$info["props"][] = $this->mkprop("creationdate", $obj->getDate());

			// directory (WebDAV collection)
			$patharr = $obj->getPath();
			array_shift($patharr);
			$path = '';
			foreach($patharr as $pathseg)
//				$path .= '/'.rawurlencode($pathseg->getName());
				$path .= '/'.$pathseg->getName();
			if(!$path) {
				$path = '/';
				$info["props"][] = $this->mkprop("isroot", "true");
			}
//			$info["path"] = htmlspecialchars($path);
			$info["path"] = $path;
			$info["props"][] = $this->mkprop("displayname", $obj->getName());
			$info["props"][] = $this->mkprop("resourcetype", "collection");
			$info["props"][] = $this->mkprop("getcontenttype", "httpd/unix-directory");
		} else {
			// modification time
			$info["props"][] = $this->mkprop("getlastmodified",$obj->getLatestContent()->getDate());
			$info["props"][] = $this->mkprop("creationdate",	$obj->getDate());

			// plain file (WebDAV resource)
			$content = $obj->getLatestContent();
			$fspath = $content->getPath();
			$patharr = $obj->getFolder()->getPath();
			array_shift($patharr);
			$path = '/';
			foreach($patharr as $pathseg)
//				$path .= rawurlencode($pathseg->getName()).'/';
				$path .= $pathseg->getName().'/';
//			$info["path"] = htmlspecialchars($path.rawurlencode($obj->getName()));
			if($this->useorgfilename) {
				$info["path"] = $path.$content->getOriginalFileName();
				$info["props"][] = $this->mkprop("displayname", $content->getOriginalFileName());
			} else {
				$info["path"] = $path.$obj->getName();
				$info["props"][] = $this->mkprop("displayname", $obj->getName());
			}

			$info["props"][] = $this->mkprop("resourcetype", "");
			if (1 /*is_readable($fspath)*/) {
				$info["props"][] = $this->mkprop("getcontenttype", $content->getMimeType());
			} else {
				$info["props"][] = $this->mkprop("getcontenttype", "application/x-non-readable");
			}			   
			$info["props"][] = $this->mkprop("getcontentlength", filesize($this->dms->contentDir.'/'.$fspath));
			if($keywords = $obj->getKeywords())
				$info["props"][] = $this->mkprop("LetoDMS:", "keywords", $keywords);
			$info["props"][] = $this->mkprop("LetoDMS:", "id", $obj->getID());
			$info["props"][] = $this->mkprop("LetoDMS:", "version", $content->getVersion());
			$status = $content->getStatus();
			$info["props"][] = $this->mkprop("LetoDMS:", "status", $status['status']);
			$info["props"][] = $this->mkprop("LetoDMS:", "status-comment", $status['comment']);
			$info["props"][] = $this->mkprop("LetoDMS:", "status-date", $status['date']);
			if($obj->getExpires())
				$info["props"][] = $this->mkprop("LetoDMS:", "expires", date('c', $obj->getExpires()));
		}
		if($comment = $obj->getComment())
			$info["props"][] = $this->mkprop("LetoDMS:", "comment", $comment);
		$info["props"][] = $this->mkprop("LetoDMS:", "owner", $obj->getOwner()->getLogin());

		$attributes = $obj->getAttributes();
		if($attributes) {
			foreach($attributes as $attribute) {
				$attrdef = $attribute->getAttributeDefinition();
				$valueset = $attrdef->getValueSetAsArray();
				if($valueset && $attrdef->getMultipleValues()) {
					$valuesetstr = $attrdef->getValueSet();
					$delimiter = substr($valuesetstr, 0, 1);
					$info["props"][] = $this->mkprop("LetoDMS:", str_replace(' ', '', $attrdef->getName()), $delimiter.implode($delimiter, $attribute->getValueAsArray()));
				} else
					$info["props"][] = $this->mkprop("LetoDMS:", str_replace(' ', '', $attrdef->getName()), $attribute->getValue());
			}
		}

		return $info;
	} /* }}} */

	/**
	 * GET method handler
	 * 
	 * @param  array  parameter passing array
	 * @return bool   true on success
	 */
	function GET(&$options) /* {{{ */
	{
		$this->log_options('GET', $options);

		// get folder or document from path
		$obj = $this->reverseLookup($options["path"]);

		// sanity check
		if (!$obj) return false;

		// is this a collection?
		if (get_class($obj) == $this->dms->getClassname('folder')) {
			return $this->GetDir($obj, $options);
		}

		$content = $obj->getLatestContent();

		// detect resource type
		$options['mimetype'] = $content->getMimeType(); 

		// detect modification time
		// see rfc2518, section 13.7
		// some clients seem to treat this as a reverse rule
		// requiering a Last-Modified header if the getlastmodified header was set
		$options['mtime'] = $content->getDate();

		$fspath = $this->dms->contentDir.'/'.$content->getPath();
		// detect resource size
		$options['size'] = filesize($fspath);

		// no need to check result here, it is handled by the base class
		$options['stream'] = fopen($fspath, "r");

		return true;
	} /* }}} */

	/**
	 * GET method handler for directories
	 *
	 * This is a very simple mod_index lookalike.
	 * See RFC 2518, Section 8.4 on GET/HEAD for collections
	 *
	 * @param  object  folder object
	 * @return void	function has to handle HTTP response itself
	 */
	function GetDir($folder, &$options) /* {{{ */
	{
		// fixed width directory column format
		$format = "%15s  %-19s  %-s\n";

		$subfolders = $folder->getSubFolders();
		$subfolders = LetoDMS_Core_DMS::filterAccess($subfolders, $this->user, M_READ);
		$documents = $folder->getDocuments();
		$docs = LetoDMS_Core_DMS::filterAccess($documents, $this->user, M_READ);
		if(!$this->user->isAdmin()) {
			$documents = array();
			foreach($docs as $document) {
				$lc = $document->getLatestContent();
				$status = $lc->getStatus();
				if($status['status'] == S_RELEASED) {
					$documents[] = $document;
				}
			}
		} else {
			$documents = $docs;
		}

		$objs = array_merge($subfolders, $documents);

		echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><title>Index of ".htmlspecialchars($options['path'])."</title></head>\n";

		echo "<h1>Index of ".htmlspecialchars($options['path'])."</h1>\n";

		echo "<pre>";
		printf($format, "Size", "Last modified", "Filename");
		echo "<hr>";

		$parents = $folder->getPath();
		$_fullpath = '/';
		if(count($parents) > 1) {
			$p = array_slice($parents, -2, 1);
			$p = $p[0];
			array_shift($parents);
			$last = array_pop($parents);
			foreach($parents as $parent)
				$_fullpath .= $parent->getName().'/';
			printf($format, 0, strftime("%Y-%m-%d %H:%M:%S", $p->getDate()), "<a href=\"".$_SERVER['SCRIPT_NAME'].htmlspecialchars($_fullpath)."\">..</a>");
			$_fullpath .= $last->getName().'/';
		}
		foreach ($objs as $obj) {
			$filename = $obj->getName();
			$fullpath = $_fullpath.$filename;
			if(get_class($obj) == $this->dms->getClassname('folder')) {
				$fullpath .= '/';
				$filename .= '/';
				$filesize = 0;
				$mtime = $obj->getDate();
			} else {
				$content = $obj->getLatestContent();

				$mimetype = $content->getMimeType(); 

				$mtime = $content->getDate();

				$fspath = $this->dms->contentDir.'/'.$content->getPath();
				$filesize = filesize($fspath);
				if($this->useorgfilename)
					$filename = $content->getOriginalFileName();;
			}
//			$name	 = htmlspecialchars($filename);
			$name = $filename;
			printf($format, 
				   number_format($filesize),
				   strftime("%Y-%m-%d %H:%M:%S", $mtime), 
				   "<a href=\"".$_SERVER['SCRIPT_NAME'].htmlspecialchars($fullpath)."\">".htmlspecialchars($name, ENT_QUOTES)."</a>");
		}

		echo "</pre>";

		echo "</html>\n";

		exit;
	} /* }}} */

	/**
	 * PUT method handler
	 * 
	 * @param  array  parameter passing array
	 * @return bool   true on success
	 */
	function PUT(&$options) /* {{{ */
	{
		global $settings, $indexconf;

		$this->log_options('PUT', $options);

		$path   = $options["path"];
		$parent = dirname($path);
		$name   = basename($path);

		// get folder from path
		if($parent == '/')
			$parent = '';
		$folder = $this->reverseLookup($parent.'/');

		if (!$folder || get_class($folder) != $this->dms->getClassname('folder')) {
			return "409 Conflict";
		}

		/* Check if user is logged in */
		if(!$this->user) {
			if($this->logger)
				$this->logger->log('PUT: access forbidden', PEAR_LOG_ERR);
			return "403 Forbidden";				 
		}

		$tmpFile = tempnam('/tmp', 'webdav');
		$fp = fopen($tmpFile, 'w');
		while(!feof($options["stream"])) {
			$data = fread($options["stream"], 1000);
			fwrite($fp, $data);
		}
		fclose($fp);

		$finfo = new finfo(FILEINFO_MIME);
		$mimetype = $finfo->file($tmpFile);

		$tmp = explode(';', $mimetype);
		$mimetype = $tmp[0];
		switch($mimetype) {
			case 'application/pdf';
				$fileType = ".pdf";
				break;
			default:
				$lastDotIndex = strrpos($name, ".");
				if($lastDotIndex === false) $fileType = ".";
				else $fileType = substr($name, $lastDotIndex);
		}
		if($this->logger)
			$this->logger->log('PUT: file is of type '.$mimetype, PEAR_LOG_INFO);

		/* First check whether there is already a file with the same name */
		if($this->useorgfilename)
			$document = $this->dms->getDocumentByOriginalFilename($name, $folder);
		else
			$document = $this->dms->getDocumentByName($name, $folder);
		if($document) {
			if($this->logger)
				$this->logger->log('PUT: replacing document id='.$document->getID(), PEAR_LOG_INFO);
			if ($document->getAccessMode($this->user, 'updateDocument') < M_READWRITE) {
				if($this->logger)
					$this->logger->log('PUT: no access on document', PEAR_LOG_ERR);
				unlink($tmpFile);
				return "403 Forbidden";
			} else {
				/* Check if the new version iѕ identical to the current version.
				 * In that case just update the modification date
				 */
				$lc = $document->getLatestContent();
				if($lc->getChecksum() == LetoDMS_Core_File::checksum($tmpFile)) {
					if($this->logger)
						$this->logger->log('PUT: identical to latest version', PEAR_LOG_INFO);
					$lc->setDate();
				} else {
					if($this->user->getID() == $lc->getUser()->getID() &&
						 $name == $lc->getOriginalFileName() &&
						 $fileType == $lc->getFileType() &&
						 $mimetype == $lc->getMimeType() &&
						 $settings->_enableWebdavReplaceDoc) {
						if($this->logger)
							$this->logger->log('PUT: replacing latest version', PEAR_LOG_INFO);
						if(!$document->replaceContent($lc->getVersion(), $this->user, $tmpFile, $name, $fileType, $mimetype)) {
							if($this->logger)
								$this->logger->log('PUT: error replacing latest version', PEAR_LOG_ERR);
							unlink($tmpFile);
							return "403 Forbidden";
						}
					} else {
						if($this->logger)
							$this->logger->log('PUT: adding new version', PEAR_LOG_INFO);
						if(!$document->addContent('', $this->user, $tmpFile, $name, $fileType, $mimetype, array(), array(), 0)) {
							if($this->logger)
								$this->logger->log('PUT: error adding new version', PEAR_LOG_ERR);
							unlink($tmpFile);
							return "409 Conflict";
						}
					}
				}
			}
		} else {
			if($this->logger)
				$this->logger->log('PUT: adding new document', PEAR_LOG_INFO);
			if ($folder->getAccessMode($this->user, 'addDocument') < M_READWRITE) {
				if($this->logger)
					$this->logger->log('PUT: no access on folder', PEAR_LOG_ERR);
				unlink($tmpFile);
				return "403 Forbidden";
			} 
			if($settings->_enableFullSearch) {
				$index = $indexconf['Indexer']::open($settings->_luceneDir);
				$indexconf['Indexer']::init($settings->_stopWordsFile);
			} else {
				$index = null;
				$indexconf = null;
			}

			$controller = Controller::factory('AddDocument');
			$controller->setParam('dms', $this->dms);
			$controller->setParam('user', $this->user);
			$controller->setParam('documentsource', 'webdav');
			$controller->setParam('folder', $folder);
			$controller->setParam('index', $index);
			$controller->setParam('indexconf', $indexconf);
			$controller->setParam('name', $name);
			$controller->setParam('comment', '');
			$controller->setParam('expires', 0);
			$controller->setParam('keywords', '');
			$controller->setParam('categories', array());
			$controller->setParam('owner', $this->user);
			$controller->setParam('userfiletmp', $tmpFile);
			$controller->setParam('userfilename', $name);
			$controller->setParam('filetype', $fileType);
			$controller->setParam('userfiletype', $mimetype);
			$minmax = $folder->getDocumentsMinMax();
			if($settings->_defaultDocPosition == 'start')
				$controller->setParam('sequence', $minmax['min'] - 1);
			else
				$controller->setParam('sequence', $minmax['max'] + 1);
			$controller->setParam('reviewers', array());
			$controller->setParam('approvers', array());
			$controller->setParam('reqversion', 0);
			$controller->setParam('versioncomment', '');
			$controller->setParam('attributes', array());
			$controller->setParam('attributesversion', array());
			$controller->setParam('workflow', null);
			$controller->setParam('notificationgroups', array());
			$controller->setParam('notificationusers', array());
			$controller->setParam('maxsizeforfulltext', $settings->_maxSizeForFullText);
			$controller->setParam('defaultaccessdocs', $settings->_defaultAccessDocs);
			if(!$document = $controller->run()) {
//			if(!$res = $folder->addDocument($name, '', 0, $this->user, '', array(), $tmpFile, $name, $fileType, $mimetype, 0, array(), array(), 0, "")) {
				unlink($tmpFile);
				if($this->logger)
					$this->logger->log('PUT: error adding object: '.$controller->getErrorMsg(), PEAR_LOG_ERR);
				return "409 Conflict ".$controller->getErrorMsg();
			}
		}

		unlink($tmpFile);
		return "201 Created";
	} /* }}} */


	/**
	 * MKCOL method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function MKCOL($options) /* {{{ */
	{		   
		$this->log_options('MKCOL', $options);

		$path   = $options["path"];
		$parent = dirname($path);
		$name   = basename($path);

		// get folder from path
		if($parent == '/')
			$parent = '';
		$folder = $this->reverseLookup($parent.'/');

		/* Check if parent folder exists at all */
		if (!$folder) {
			return "409 Conflict";
		}

		/* Check if parent of new folder is a folder */
		if (get_class($folder) != $this->dms->getClassname('folder')) {
			if($this->logger)
				$this->logger->log('MKCOL: access forbidden', PEAR_LOG_ERR);
			return "403 Forbidden";
		}

		/* Check if parent folder already has folder with the same name */
		if ($this->dms->getFolderByName($name, $folder) ) {
			return "405 Method not allowed";
		}

		if (!empty($this->_SERVER["CONTENT_LENGTH"])) { // no body parsing yet
			return "415 Unsupported media type";
		}

		/* Check if user is logged in */
		if(!$this->user) {
			if($this->logger)
				$this->logger->log('MKCOL: access forbidden', PEAR_LOG_ERR);
			return "403 Forbidden";				 
		}

		if ($folder->getAccessMode($this->user, 'addFolder') < M_READWRITE) {
			if($this->logger)
				$this->logger->log('MKCOL: access forbidden', PEAR_LOG_ERR);
			return "403 Forbidden";				 
		}

		$controller = Controller::factory('AddSubFolder');
		$controller->setParam('dms', $this->dms);
		$controller->setParam('user', $this->user);
		$controller->setParam('folder', $folder);
		$controller->setParam('name', $name);
		$controller->setParam('comment', '');
		$controller->setParam('sequence', 0);
		$controller->setParam('attributes', array());
		$controller->setParam('notificationgroups', array());
		$controller->setParam('notificationusers', array());
		if(!$subFolder = $controller->run()) {
//		if (!$folder->addSubFolder($name, '', $this->user, 0)) {
			return "409 Conflict ".$controller->getErrorMsg();				 
		}

		return ("201 Created");
	} /* }}} */


	/**
	 * DELETE method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function DELETE($options) /* {{{ */
	{
		global $settings, $indexconf;

		$this->log_options('DELETE', $options);

		// get folder or document from path
		$obj = $this->reverseLookup($options["path"]);
		/* Make a second try if it is a directory with the leading '/' */
		if(!$obj)
			$obj = $this->reverseLookup($options["path"].'/');

		// sanity check
		if (!$obj) return "404 Not found";

		// check for access rights
		if($obj->getAccessMode($this->user, get_class($obj) == $this->dms->getClassname('folder') ? 'removeFolder' : 'removeDocument') < M_ALL) {
			if($this->logger)
				$this->logger->log('DELETE: access forbidden', PEAR_LOG_ERR);
			return "403 Forbidden";				 
		}

		if($settings->_enableFullSearch) {
			$index = $indexconf['Indexer']::open($settings->_luceneDir);
			$indexconf['Indexer']::init($settings->_stopWordsFile);
		} else {
			$index = null;
			$indexconf = null;
		}

		if (get_class($obj) == $this->dms->getClassname('folder')) {
			if($obj->hasDocuments() || $obj->hasSubFolders()) {
				if($this->logger)
					$this->logger->log('DELETE: cannot delete, folder has children', PEAR_LOG_ERR);
				return "409 Conflict";
			}
			$controller = Controller::factory('RemoveFolder');
			$controller->setParam('dms', $this->dms);
			$controller->setParam('user', $this->user);
			$controller->setParam('folder', $obj);
			$controller->setParam('index', $index);
			$controller->setParam('indexconf', $indexconf);
			if(!$controller->run()) {
//			if(!$obj->remove()) {
				return "409 Conflict ".$controller->getErrorMsg();
			}
		} else {
			$controller = Controller::factory('RemoveDocument');
			$controller->setParam('dms', $this->dms);
			$controller->setParam('user', $this->user);
			$controller->setParam('document', $obj);
			$controller->setParam('index', $index);
			$controller->setParam('indexconf', $indexconf);
			if(!$controller->run()) {
//			if(!$obj->remove()) {
				return "409 Conflict ".$controller->getErrorMsg();
			}
		}

		return "204 No Content";
	} /* }}} */


	/**
	 * MOVE method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function MOVE($options) /* {{{ */
	{
		$this->log_options('MOVE', $options);

		// no copying to different WebDAV Servers yet
		if (isset($options["dest_url"])) {
			return "502 bad gateway";
		}

		// get folder or document to move
		$objsource = $this->reverseLookup($options["path"]);
		/* Make a second try if it is directory with the leading '/' */
		if(!$objsource)
			$objsource = $this->reverseLookup($options["path"].'/');
		if (!$objsource)
			return "404 Not found";

		// get dest folder or document
		$objdest = $this->reverseLookup($options["dest"]);

		$newdocname = '';
		if(!$objdest) {
			/* check if at least the dest directory exists */
			$dirname = dirname($options['dest']);
			if($dirname != '/')
				$dirname .= '/';
			$newdocname = basename($options['dest']);
			$objdest = $this->reverseLookup($dirname);
			if(!$objdest)
				return "412 precondition failed";
		}

		/* Moving a document requires write access on the source and
		 * destination object
		 */
		if (($objsource->getAccessMode($this->user) < M_READWRITE) || ($objdest->getAccessMode($this->user) < M_READWRITE)) {
			if($this->logger)
				$this->logger->log('MOVE: access forbidden', PEAR_LOG_ERR);
			return "403 Forbidden";				 
		}

		if(get_class($objdest) == $this->dms->getClassname('document')) {
			/* If destination object is a document it must be overwritten */
			if (!$options["overwrite"]) {
				return "412 precondition failed";
			}
			if(get_class($objsource) == $this->dms->getClassname('folder')) {
				return "400 Bad request";
			}

			/* get the latest content of the source object */
			$content = $objsource->getLatestContent();
			$fspath = $this->dms->contentDir.'/'.$content->getPath();

			/* save the content as a new version in the destination document */
			if(!$objdest->addContent('', $this->user, $fspath, $content->getOriginalFileName(), $content->getFileType(), $content->getMimeType, array(), array(), 0)) {
				unlink($tmpFile);
				return "409 Conflict";
			}

			/* change the name of the destination object */
			$objdest->setName($objsource->getName());

			/* delete the source object */
			$objsource->remove();

			return "204 No Content";
		} elseif(get_class($objdest) == $this->dms->getClassname('folder')) {
			/* Set the new Folder of the source object */
			if(get_class($objsource) == $this->dms->getClassname('document'))
				$objsource->setFolder($objdest);
			elseif(get_class($objsource) == $this->dms->getClassname('folder'))
				$objsource->setParent($objdest);
			else
				return "500 Internal server error";
			if($newdocname)
				$objsource->setName($newdocname);
			return "204 No Content";
		}
	} /* }}} */

	/**
	 * COPY method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function COPY($options, $del=false) /* {{{ */
	{
		global $settings, $indexconf;

		if(!$del)
			$this->log_options('COPY', $options);

		// TODO Property updates still broken (Litmus should detect this?)

		if (!empty($this->_SERVER["CONTENT_LENGTH"])) { // no body parsing yet
			return "415 Unsupported media type";
		}

		// no copying to different WebDAV Servers yet
		if (isset($options["dest_url"])) {
			return "502 bad gateway";
		}

		// get folder or document to move
		$objsource = $this->reverseLookup($options["path"]);
		/* Make a second try if it is directory with the leading '/' */
		if(!$objsource)
			$objsource = $this->reverseLookup($options["path"].'/');
		if (!$objsource)
			return "404 Not found";

		if (get_class($objsource) == $this->dms->getClassname('folder') && ($options["depth"] != "infinity")) {
			// RFC 2518 Section 9.2, last paragraph
			return "400 Bad request";
		}

		// get dest folder or document
		$objdest = $this->reverseLookup($options["dest"]);

		$newdocname = '';
		if(!$objdest) {
			/* check if at least the dest directory exists */
			$dirname = dirname($options['dest']);
			if($dirname != '/')
				$dirname .= '/';
			$newdocname = basename($options['dest']);
			$objdest = $this->reverseLookup($dirname);
			if(!$objdest)
				return "412 precondition failed";
		}

		/* Copying a document requires read access on the source and write
		 * access on the destination object
		 */
		if (($objsource->getAccessMode($this->user) < M_READ) || ($objdest->getAccessMode($this->user) < M_READWRITE)) {
			if($this->logger)
				$this->logger->log('COPY: access forbidden', PEAR_LOG_ERR);
			return "403 Forbidden";				 
		}

		/* If destination object is a document it must be overwritten */
		if(get_class($objdest) == $this->dms->getClassname('document')) {
			if (!$options["overwrite"]) {
				return "412 precondition failed";
			}
			/* Copying a folder into a document makes no sense */
			if(get_class($objsource) == $this->dms->getClassname('folder')) {
				return "400 Bad request";
			}

			/* get the latest content of the source object */
			$content = $objsource->getLatestContent();
			$fspath = $this->dms->contentDir.'/'.$content->getPath();

			/* save the content as a new version in the destination document */
			if(!$objdest->addContent('', $this->user, $fspath, $content->getOriginalFileName(), $content->getFileType(), $content->getMimeType, array(), array(), 0)) {
				unlink($tmpFile);
				return "409 Conflict";
			}

			$objdest->setName($objsource->getName());

			return "204 No Content";
		} elseif(get_class($objdest) == $this->dms->getClassname('folder')) {
			if($this->logger)
				$this->logger->log('COPY: copy \''.$objdest->getName().'\' to folder '.$objdest->getName().'', PEAR_LOG_INFO);

			/* Currently no support for copying folders */
			if(get_class($objsource) == $this->dms->getClassname('folder')) {
				if($this->logger)
					$this->logger->log('COPY: source is a folder '.$objsource->getName().'', PEAR_LOG_INFO);

				return "400 Bad request";
			}

			if(!$newdocname)
				$newdocname = $objsource->getName();

			/* get the latest content of the source object */
			$content = $objsource->getLatestContent();
			$fspath = $this->dms->contentDir.'/'.$content->getPath();

			if($settings->_enableFullSearch) {
				$index = $indexconf['Indexer']::open($settings->_luceneDir);
				$indexconf['Indexer']::init($settings->_stopWordsFile);
			} else {
				$index = null;
				$indexconf = null;
			}

			$controller = Controller::factory('AddDocument');
			$controller->setParam('dms', $this->dms);
			$controller->setParam('user', $this->user);
			$controller->setParam('documentsource', 'webdav');
			$controller->setParam('folder', $objdest);
			$controller->setParam('index', $index);
			$controller->setParam('indexconf', $indexconf);
			$controller->setParam('name', $newdocname);
			$controller->setParam('comment', '');
			$controller->setParam('expires', 0);
			$controller->setParam('keywords', '');
			$controller->setParam('categories', array());
			$controller->setParam('owner', $this->user);
			$controller->setParam('userfiletmp', $fspath);
			$controller->setParam('userfilename', $content->getOriginalFileName());
			$controller->setParam('filetype', $content->getFileType());
			$controller->setParam('userfiletype', $content->getMimeType());
			$minmax = $objdest->getDocumentsMinMax();
			if($settings->_defaultDocPosition == 'start')
				$controller->setParam('sequence', $minmax['min'] - 1);
			else
				$controller->setParam('sequence', $minmax['max'] + 1);
			$controller->setParam('reviewers', array());
			$controller->setParam('approvers', array());
			$controller->setParam('reqversion', 0);
			$controller->setParam('versioncomment', '');
			$controller->setParam('attributes', array());
			$controller->setParam('attributesversion', array());
			$controller->setParam('workflow', null);
			$controller->setParam('notificationgroups', array());
			$controller->setParam('notificationusers', array());
			$controller->setParam('maxsizeforfulltext', $settings->_maxSizeForFullText);
			$controller->setParam('defaultaccessdocs', $settings->_defaultAccessDocs);
			if(!$document = $controller->run()) {
//			if(!$newdoc = $objdest->addDocument($newdocname, '', 0, $this->user, '', array(), $fspath, $content->getOriginalFileName(), $content->getFileType(), $content->getMimeType(), 0, array(), array(), 0, "")) {
				if($this->logger)
					$this->logger->log('COPY: error copying object', PEAR_LOG_ERR);
				return "409 Conflict";
			}
			return "201 Created";
		}
	} /* }}} */

	/**
	 * PROPPATCH method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function PROPPATCH(&$options) /* {{{ */
	{
		$this->log_options('PROPPATCH', $options);

		// get folder or document from path
		$obj = $this->reverseLookup($options["path"]);

		// sanity check
		if (!$obj) {
			$obj = $this->reverseLookup($options["path"].'/');
			if(!$obj)
				return false;
		}

		if ($obj->getAccessMode($this->user) < M_READWRITE) {
			return false;				 
		}

		foreach ($options["props"] as $key => $prop) {
			if ($prop["ns"] == "DAV:") {
				$options["props"][$key]['status'] = "403 Forbidden";
			} else {
				$this->logger->log('PROPPATCH: set '.$prop["ns"].''.$prop["val"].' to '.$prop["val"], PEAR_LOG_INFO);
				if($prop["ns"] == "LetoDMS:") {
					if(in_array($prop['name'], array('id', 'version', 'status', 'status-comment', 'status-date'))) {
						$options["props"][$key]['status'] = "403 Forbidden";
					} else {
						if (isset($prop["val"]))
							$val = $prop["val"];
						else
							$val = '';
						switch($prop["name"]) {
						case "comment":
							$obj->setComment($val);
							break;
						default:
							if($attrdef = $this->dms->getAttributeDefinitionByName($prop["name"])) {
								$valueset = $attrdef->getValueSetAsArray();
								switch($attrdef->getType()) {
								case LetoDMS_Core_AttributeDefinition::type_string:
									$obj->setAttributeValue($attrdef, $val);
									break;
								case LetoDMS_Core_AttributeDefinition::type_int:
									$obj->setAttributeValue($attrdef, (int) $val);
									break;
								case LetoDMS_Core_AttributeDefinition::type_float:
									$obj->setAttributeValue($attrdef, (float) $val);
									break;
								case LetoDMS_Core_AttributeDefinition::type_boolean:
									$obj->setAttributeValue($attrdef, $val == 1 ? true : false);
									break;
								}
							}
						}
					}
				}
			}
		}

		return "";
	} /* }}} */


	/**
	 * LOCK method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function LOCK(&$options) /* {{{ */
	{
		$this->log_options('LOCK', $options);

		// get object to lock
		$obj = $this->reverseLookup($options["path"]);

		if(!$obj)
			return "200 OK";

		// TODO recursive locks on directories not supported yet
		if (get_class($obj) == $this->dms->getClassname('folder') && !empty($options["depth"])) {
			return "409 Conflict";
		}

		if ($obj->getAccessMode($this->user) < M_READWRITE) {
			if($this->logger)
				$this->logger->log('LOCK: access forbidden', PEAR_LOG_ERR);
			return "403 Forbidden";				 
		}

		$options["timeout"] = 0;//time()+300; // 5min. hardcoded

		if(!$obj->setLocked($this->user)) {
			return "409 Conflict";
		}

		$options['owner'] = $this->user->getLogin();
		$options['scope'] = "exclusive";
		$options['type'] = "write";

		return "200 OK";
	} /* }}} */

	/**
	 * UNLOCK method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function UNLOCK(&$options) /* {{{ */
	{
		$this->log_options('UNLOCK', $options);

		// get object to unlock
		$obj = $this->reverseLookup($options["path"]);

		if(!$obj)
			return "204 No Content";

		// TODO recursive locks on directories not supported yet
		if (get_class($obj) == $this->dms->getClassname('folder') && !empty($options["depth"])) {
			return "409 Conflict";
		}

		if ($obj->getAccessMode($this->user) < M_READWRITE) {
			if($this->logger)
				$this->logger->log('UNLOCK: access forbidden', PEAR_LOG_ERR);
			return "403 Forbidden";				 
		}

		if(!$obj->setLocked(false)) {
			return "409 Conflict";
		}

		return "204 No Content";
	} /* }}} */

	/**
	 * checkLock() helper
	 *
	 * @param  string resource path to check for locks
	 * @return bool   true on success
	 */
	function checkLock($path) /* {{{ */
	{
		if($this->logger)
			$this->logger->log('checkLock: path='.$path.'', PEAR_LOG_INFO);

		// get object to check for lock
		$obj = $this->reverseLookup($path);

		// check for folder returns no object
		if(!$obj) {
			if($this->logger)
				$this->logger->log('checkLock: object not found', PEAR_LOG_INFO);
			return false;
		}

		// Folders cannot be locked
		if(get_class($obj) == $this->dms->getClassname('folder')) {
			if($this->logger)
				$this->logger->log('checkLock: object is a folder', PEAR_LOG_INFO);
			return false;
		}

		if($obj->isLocked() && $this->user->getLogin() != $obj->getLockingUser()->getLogin()) {
			$lockuser = $obj->getLockingUser();
			if($this->logger)
				$this->logger->log('checkLock: object is locked by '.$lockuser->getLogin(), PEAR_LOG_INFO);
			return array(
				"type"    => "write",
				"scope"   => "exclusive",
				"depth"   => 0,
				"owner"   => $lockuser->getLogin(),
				"token"   => 'kk', // must return something to prevent php warning in Server.php:1865
				"created" => '',
				"modified" => '',
				"expires" => ''
				);
		} else {
			if($this->logger)
				$this->logger->log('checkLock: object is not locked', PEAR_LOG_INFO);
			return false;
		}
	} /* }}} */

}


/*
 * vim: ts=2 sw=2 noexpandtab
 */
?>
