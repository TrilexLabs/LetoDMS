<?php
define('USE_PHP_SESSION', 0);

include("../inc/inc.Settings.php");
include("../inc/inc.Init.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.Extension.php");

if(USE_PHP_SESSION) {
    session_start();
    $userobj = null;
    if(isset($_SESSION['userid']))
        $userobj = $dms->getUser($_SESSION['userid']);
    elseif($settings->_enableGuestLogin)
        $userobj = $dms->getUser($settings->_guestID);
    else
        exit;
    $dms->setUser($userobj);
} else {
    require_once("../inc/inc.ClassSession.php");
    $session = new LetoDMS_Session($db);
    if (isset($_COOKIE["mydms_session"])) {
        $dms_session = $_COOKIE["mydms_session"];
        if(!$resArr = $session->load($dms_session)) {
            /* Delete Cookie */
            setcookie("mydms_session", $dms_session, time()-3600, $settings->_httpRoot);
            if($settings->_enableGuestLogin)
                $userobj = $dms->getUser($settings->_guestID);
            else
                exit;
        }

        /* Load user data */
        $userobj = $dms->getUser($resArr["userID"]);
        if (!is_object($userobj)) {
            /* Delete Cookie */
            setcookie("mydms_session", $dms_session, time()-3600, $settings->_httpRoot);
            if($settings->_enableGuestLogin)
                $userobj = $dms->getUser($settings->_guestID);
            else
                exit;
        }
        if($userobj->isAdmin()) {
            if($resArr["su"]) {
                $userobj = $dms->getUser($resArr["su"]);
            }
        }
        $dms->setUser($userobj);
    }
}

require "vendor/autoload.php";

function __getLatestVersionData($lc) { /* {{{ */
    $document = $lc->getDocument();
    $data = array(
        'type'=>'document',
        'id'=>(int)$document->getId(),
        'date'=>date('Y-m-d H:i:s', $document->getDate()),
        'name'=>$document->getName(),
        'comment'=>$document->getComment(),
        'keywords'=>$document->getKeywords(),
        'mimetype'=>$lc->getMimeType(),
        'version'=>$lc->getVersion(),
        'size'=>$lc->getFileSize(),
    );
    $cats = $document->getCategories();
    if($cats) {
        $c = array();
        foreach($cats as $cat) {
            $c[] = array('id'=>(int)$cat->getID(), 'name'=>$cat->getName());
        }
        $data['categories'] = $c;
    }
    $attributes = $document->getAttributes();
    if($attributes) {
        $attrvalues = array();
        foreach($attributes as $attrdefid=>$attribute)
            $attrvalues[] = array('id'=>(int)$attrdefid, 'value'=>$attribute->getValue());
        $data['attributes'] = $attrvalues;
    }
    $attributes = $lc->getAttributes();
    if($attributes) {
        $attrvalues = array();
        foreach($attributes as $attrdefid=>$attribute)
            $attrvalues[] = array('id'=>(int)$attrdefid, 'value'=>$attribute->getValue());
        $data['version-attributes'] = $attrvalues;
    }
    return $data;
} /* }}} */

function __getFolderData($folder) { /* {{{ */
    $data = array(
        'type'=>'folder',
        'id'=>(int)$folder->getID(),
        'name'=>$folder->getName(),
        'comment'=>$folder->getComment(),
        'date'=>date('Y-m-d H:i:s', $folder->getDate()),
    );
    $attributes = $folder->getAttributes();
    if($attributes) {
        $attrvalues = array();
        foreach($attributes as $attrdefid=>$attribute)
            $attrvalues[] = array('id'=>(int)$attrdefid, 'value'=>$attribute->getValue());
        $data['attributes'] = $attrvalues;
    }
    return $data;
} /* }}} */

function __getGroupData($u) { /* {{{ */
    $data = array(
        'type'=>'group',
        'id'=>(int)$u->getID(),
        'name'=>$u->getName(),
        'comment'=>$u->getComment(),
    );
    return $data;
} /* }}} */

function __getUserData($u) { /* {{{ */
    $data = array(
        'type'=>'user',
        'id'=>(int)$u->getID(),
        'name'=>$u->getFullName(),
        'comment'=>$u->getComment(),
        'login'=>$u->getLogin(),
        'email'=>$u->getEmail(),
        'language' => $u->getLanguage(),
        'theme' => $u->getTheme(),
        'role' => $u->getRole() == LetoDMS_Core_User::role_admin ? 'admin' : ($u->getRole() == LetoDMS_Core_User::role_guest ? 'guest' : 'user'),
        'hidden'=>$u->isHidden() ? true : false,
        'disabled'=>$u->isDisabled() ? true : false,
        'isguest' => $u->isGuest() ? true : false,
        'isadmin' => $u->isAdmin() ? true : false,
    );
    if($u->getHomeFolder())
        $data['homefolder'] = (int)$u->getHomeFolder();

    $groups = $u->getGroups();
    if($groups) {
        $tmp = [];
        foreach($groups as $group)
            $tmp[] = __getGroupData($group);
        $data['groups'] = $tmp;
    }
    return $data;
} /* }}} */

function doLogin() { /* {{{ */
    global $app, $dms, $userobj, $session, $settings;

    $username = $app->request()->post('user');
    $password = $app->request()->post('pass');

//    $userobj = $dms->getUserByLogin($username);
    $userobj = null;

    /* Authenticate against LDAP server {{{ */
    if (!$userobj && isset($settings->_ldapHost) && strlen($settings->_ldapHost)>0) {
        require_once("../inc/inc.ClassLdapAuthentication.php");
        $authobj = new LetoDMS_LdapAuthentication($dms, $settings);
        $userobj = $authobj->authenticate($username, $password);
    } /* }}} */

    /* Authenticate against LetoDMS database {{{ */
    if(!$userobj) {
        require_once("../inc/inc.ClassDbAuthentication.php");
        $authobj = new LetoDMS_DbAuthentication($dms, $settings);
        $userobj = $authobj->authenticate($username, $password);
    } /* }}} */

    if(!$userobj) {
        if(USE_PHP_SESSION) {
            unset($_SESSION['userid']);
        } else {
            setcookie("mydms_session", $session->getId(), time()-3600, $settings->_httpRoot);
        }
        $app->response()->status(403);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Login failed', 'data'=>''));
    } else {
        if(USE_PHP_SESSION) {
            $_SESSION['userid'] = $userobj->getId();
        } else {
            if(!$id = $session->create(array('userid'=>$userobj->getId(), 'theme'=>$userobj->getTheme(), 'lang'=>$userobj->getLanguage()))) {
                exit;
            }

            // Set the session cookie.
            if($settings->_cookieLifetime)
                $lifetime = time() + intval($settings->_cookieLifetime);
            else
                $lifetime = 0;
            setcookie("mydms_session", $id, $lifetime, $settings->_httpRoot);
            $dms->setUser($userobj);
        }
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'', 'data'=>__getUserData($userobj)));
    }
} /* }}} */

function doLogout() { /* {{{ */
    global $app, $dms, $userobj, $session, $settings;

    if(USE_PHP_SESSION) {
        unset($_SESSION['userid']);
    } else {
        setcookie("mydms_session", $session->getId(), time()-3600, $settings->_httpRoot);
    }
    $userobj = null;
    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>''));
} /* }}} */

function setFullName() { /* {{{ */
    global $app, $dms, $userobj;

    if(!$userobj) {
        $app->response()->status(403);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Not logged in', 'data'=>''));
        return;
    }

    $userobj->setFullName($app->request()->put('fullname'));
    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$userobj->getFullName()));
} /* }}} */

function setEmail($id) { /* {{{ */
    global $app, $dms, $userobj;

    if(!$userobj) {
        $app->response()->status(403);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Not logged in', 'data'=>''));
        return;
    }

    $userobj->setEmail($app->request()->put('email'));
    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$userid));
} /* }}} */

function getLockedDocuments() { /* {{{ */
    global $app, $dms, $userobj;

    if(false !== ($documents = $dms->getDocumentsLockedByUser($userobj))) {
        $documents = LetoDMS_Core_DMS::filterAccess($documents, $userobj, M_READ);
        $recs = array();
        foreach($documents as $document) {
            $lc = $document->getLatestContent();
            if($lc) {
                $recs[] = __getLatestVersionData($lc);
            }
        }
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$recs));
    } else {
        $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'', 'data'=>''));
    }
} /* }}} */

function getFolder($id = null) { /* {{{ */
    global $app, $dms, $userobj, $settings;
    $forcebyname = $app->request()->get('forcebyname');

    if ($id === null)
        $folder = $dms->getFolder($settings->_rootFolderID);
    else if(ctype_digit($id) && empty($forcebyname))
        $folder = $dms->getFolder($id);
    else {
        $parentid = $app->request()->get('parentid');
        $folder = $dms->getFolderByName($id, $parentid);
    }
    if($folder) {
        if($folder->getAccessMode($userobj) >= M_READ) {
            $data = __getFolderData($folder);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$data));
        } else {
            $app->response()->status(404);
        }
    } else {
        $app->response()->status(404);
    }
} /* }}} */

function getFolderParent($id) { /* {{{ */
    global $app, $dms, $userobj;
    if($id == 0) {
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'id is 0', 'data'=>''));
        return;
    }
    $root = $dms->getRootFolder();
    if($root->getId() == $id) {
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'id is root folder', 'data'=>''));
        return;
    }
    $folder = $dms->getFolder($id);
    $parent = $folder->getParent();
    if($parent) {
        $rec = __getFolderData($parent);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$rec));
    } else {
        $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'', 'data'=>''));
    }
} /* }}} */

function getFolderPath($id) { /* {{{ */
    global $app, $dms, $userobj;
    if($id == 0) {
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'id is 0', 'data'=>''));
        return;
    }
    $folder = $dms->getFolder($id);

    $path = $folder->getPath();
    $data = array();
    foreach($path as $element) {
        $data[] = array('id'=>$element->getId(), 'name'=>$element->getName());
    }
    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$data));
} /* }}} */

function getFolderAttributes($id) { /* {{{ */
    global $app, $dms, $userobj;
    $folder = $dms->getFolder($id);

    if($folder) {
        if ($folder->getAccessMode($userobj) >= M_READ) {
            $recs = array();
            $attributes = $folder->getAttributes();
            foreach($attributes as $attribute) {
                $recs[] = array(
                    'id'=>(int)$attribute->getId(),
                    'value'=>$attribute->getValue(),
                    'name'=>$attribute->getAttributeDefinition()->getName(),
                );
            }
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$recs));
        } else {
            $app->response()->status(404);
        }
    }
} /* }}} */

function getFolderChildren($id) { /* {{{ */
    global $app, $dms, $userobj;
    if($id == 0) {
        $folder = $dms->getRootFolder();
        $recs = array(__getFolderData($folder));
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$recs));
    } else {
        $folder = $dms->getFolder($id);
        if($folder) {
            if($folder->getAccessMode($userobj) >= M_READ) {
                $recs = array();
                $subfolders = $folder->getSubFolders();
                $subfolders = LetoDMS_Core_DMS::filterAccess($subfolders, $userobj, M_READ);
                foreach($subfolders as $subfolder) {
                    $recs[] = __getFolderData($subfolder);
                }
                $documents = $folder->getDocuments();
                $documents = LetoDMS_Core_DMS::filterAccess($documents, $userobj, M_READ);
                foreach($documents as $document) {
                    $lc = $document->getLatestContent();
                    if($lc) {
                        $recs[] = __getLatestVersionData($lc);
                    }
                }
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$recs));
            } else {
                $app->response()->status(403);
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
            }
        } else {
            $app->response()->status(404);
        }
    }
} /* }}} */

function createFolder($id) { /* {{{ */
    global $app, $dms, $userobj;

    if(!$userobj) {
        $app->response()->status(403);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Not logged in', 'data'=>''));
        return;
    }

    if(!ctype_digit($id) || $id == 0) {
        $app->response()->status(400);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No parent folder given', 'data'=>''));
        return;
    }
    $parent = $dms->getFolder($id);
    if($parent) {
        if($parent->getAccessMode($userobj, 'addFolder') >= M_READWRITE) {
            if($name = $app->request()->post('name')) {
                $comment = $app->request()->post('comment');
                $attributes = $app->request()->post('attributes');
                $newattrs = array();
                if($attributes) {
                    foreach($attributes as $attrname=>$attrvalue) {
                        $attrdef = $dms->getAttributeDefinitionByName($attrname);
                        if($attrdef) {
                            $newattrs[$attrdef->getID()] = $attrvalue;
                        }
                    }
                }
                if($folder = $parent->addSubFolder($name, $comment, $userobj, 0, $newattrs)) {

                    $rec = __getFolderData($folder);
                    $app->response()->status(201);
                    $app->response()->header('Content-Type', 'application/json');
                    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$rec));
                } else {
                    $app->response()->status(500);
                    $app->response()->header('Content-Type', 'application/json');
                    echo json_encode(array('success'=>false, 'message'=>'', 'data'=>''));
                }
            } else {
                $app->response()->status(400);
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>false, 'message'=>'', 'data'=>''));
            }
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access on destination folder', 'data'=>''));
        }
    } else {
        $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'', 'data'=>''));
    }
} /* }}} */

function moveFolder($id, $folderid) { /* {{{ */
    global $app, $dms, $userobj;

    if(!$userobj) {
        $app->response()->status(403);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Not logged in', 'data'=>''));
        return;
    }

    if(!ctype_digit($id) || $id == 0) {
        $app->response()->status(400);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'No source folder given', 'data'=>''));
        return;
    }

    if(!ctype_digit($folderid) || $folderid == 0) {
        $app->response()->status(400);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'No destination folder given', 'data'=>''));
        return;
    }

    $mfolder = $dms->getFolder($id);
    if($mfolder) {
        if ($mfolder->getAccessMode($userobj, 'moveFolder') >= M_READ) {
            if($folder = $dms->getFolder($folderid)) {
                if($folder->getAccessMode($userobj, 'moveFolder') >= M_READWRITE) {
                    if($mfolder->setParent($folder)) {
                        $app->response()->header('Content-Type', 'application/json');
                        echo json_encode(array('success'=>true, 'message'=>'', 'data'=>''));
                    } else {
                        $app->response()->status(500);
                        $app->response()->header('Content-Type', 'application/json');
                        echo json_encode(array('success'=>false, 'message'=>'Error moving folder', 'data'=>''));
                    }
                } else {
                    $app->response()->status(403);
                    $app->response()->header('Content-Type', 'application/json');
                    echo json_encode(array('success'=>false, 'message'=>'No access on destination folder', 'data'=>''));
                }
            } else {
                if($folder === null)
                    $app->response()->status(400);
                else
                    $app->response()->status(500);
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>false, 'message'=>'No destination folder', 'data'=>''));
            }
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($mfolder === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No folder', 'data'=>''));
    }
} /* }}} */

function deleteFolder($id) { /* {{{ */
    global $app, $dms, $userobj;

    if(!$userobj) {
        $app->response()->status(403);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Not logged in', 'data'=>''));
        return;
    }

    if(!ctype_digit($id) || $id == 0) {
        $app->response()->status(400);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'id is 0', 'data'=>''));
        return;
    }
    $mfolder = $dms->getFolder($id);
    if($mfolder) {
        if ($mfolder->getAccessMode($userobj, 'removeFolder') >= M_READWRITE) {
            if($mfolder->remove()) {
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>true, 'message'=>'', 'data'=>''));
            } else {
                $app->response()->status(500);
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>false, 'message'=>'Error deleting folder', 'data'=>''));
            }
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($mfolder === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No folder', 'data'=>''));
    }
} /* }}} */

function uploadDocument($id) { /* {{{ */
    global $app, $dms, $userobj;

    if(!$userobj) {
        $app->response()->status(403);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Not logged in', 'data'=>''));
        return;
    }

    if(!ctype_digit($id) || $id == 0) {
        $app->response()->status(400);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'id is 0', 'data'=>''));
        return;
    }
    $mfolder = $dms->getFolder($id);
    if($mfolder) {
        if ($mfolder->getAccessMode($userobj, 'addDocument') >= M_READWRITE) {
            $docname = $app->request()->params('name');
            $keywords = $app->request()->params('keywords');
//            $categories = $app->request()->params('categories') ? $app->request()->params('categories') : [];
//            $attributes = $app->request()->params('attributes') ? $app->request()->params('attributes') : [];
            $origfilename = $app->request()->params('origfilename');
            if (count($_FILES) == 0) {
                $app->response()->status(400);
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>false, 'message'=>'No file detected', 'data'=>''));
                return;
            }
            $file_info = reset($_FILES);
            if ($origfilename == null)
                $origfilename = $file_info['name'];
            if (trim($docname) == '')
                $docname = $origfilename;
            $temp = $file_info['tmp_name'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $userfiletype = finfo_file($finfo, $temp);
            $fileType = ".".pathinfo($origfilename, PATHINFO_EXTENSION);
            finfo_close($finfo);
            $res = $mfolder->addDocument($docname, '', 0, $userobj, $keywords, array(), $temp, $origfilename ? $origfilename : basename($temp), $fileType, $userfiletype, 0);
//            addDocumentCategories($res, $categories);
//            setDocumentAttributes($res, $attributes);

            unlink($temp);
            if($res) {
                $doc = $res[0];
                $rec = array('id'=>(int)$doc->getId(), 'name'=>$doc->getName());
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>true, 'message'=>'Upload succeded', 'data'=>$rec));
            } else {
                $app->response()->status(500);
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>false, 'message'=>'Upload failed', 'data'=>''));
            }
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($mfolder === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No folder', 'data'=>''));
    }
} /* }}} */

/**
 * Old upload method which uses put instead of post
 */
function uploadDocumentPut($id) { /* {{{ */
    global $app, $dms, $userobj;

    if(!$userobj) {
        $app->response()->status(403);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Not logged in', 'data'=>''));
        return;
    }

    if(!ctype_digit($id) || $id == 0) {
        $app->response()->status(400);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'id is 0', 'data'=>''));
        return;
    }
    $mfolder = $dms->getFolder($id);
    if($mfolder) {
        if ($mfolder->getAccessMode($userobj, 'addDocument') >= M_READWRITE) {
            $docname = $app->request()->get('name');
            $origfilename = $app->request()->get('origfilename');
            $content = $app->getInstance()->request()->getBody();
            $temp = tempnam('/tmp', 'lajflk');
            $handle = fopen($temp, "w");
            fwrite($handle, $content);
            fclose($handle);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $userfiletype = finfo_file($finfo, $temp);
            $fileType = ".".pathinfo($origfilename, PATHINFO_EXTENSION);
            finfo_close($finfo);
            $res = $mfolder->addDocument($docname, '', 0, $userobj, '', array(), $temp, $origfilename ? $origfilename : basename($temp), $fileType, $userfiletype, 0);
            unlink($temp);
            if($res) {
                $doc = $res[0];
                $rec = array('id'=>(int)$doc->getId(), 'name'=>$doc->getName());
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>true, 'message'=>'Upload succeded', 'data'=>$rec));
            } else {
                $app->response()->status(500);
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>false, 'message'=>'Upload failed', 'data'=>''));
            }
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($mfolder === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No folder', 'data'=>''));
    }
} /* }}} */

function uploadDocumentFile($documentId) { /* {{{ */
    global $app, $dms, $userobj;

    if(!$userobj) {
        $app->response()->status(403);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Not logged in', 'data'=>''));
        return;
    }

    if(!ctype_digit($documentId) || $documentId == 0) {
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'id is 0', 'data'=>''));
        return;
    }
    $document = $dms->getDocument($documentId);
    if($document) {
        if ($document->getAccessMode($userobj, 'addDocumentFile') >= M_READWRITE) {
            $docname = $app->request()->params('name');
            $keywords = $app->request()->params('keywords');
            $origfilename = $app->request()->params('origfilename');
            $comment = $app->request()->params('comment');
            $version = $app->request()->params('version') == '' ? 0 : $app->request()->params('version');
            $public = $app->request()->params('public') == '' ? 'false' : $app->request()->params('public');
            if (count($_FILES) == 0) {
                $app->response()->status(400);
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>false, 'message'=>'No file detected', 'data'=>''));
                return;
            }
            $file_info = reset($_FILES);
            if ($origfilename == null)
                $origfilename = $file_info['name'];
            if (trim($docname) == '')
                $docname = $origfilename;
            $temp = $file_info['tmp_name'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $userfiletype = finfo_file($finfo, $temp);
            $fileType = ".".pathinfo($origfilename, PATHINFO_EXTENSION);
            finfo_close($finfo);
            $res = $document->addDocumentFile($docname, $comment, $userobj, $temp,
                        $origfilename ? $origfilename : utf8_basename($temp),
                        $fileType, $userfiletype, $version, $public);
            unlink($temp);
            if($res) {
                $app->response()->status(201);
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>true, 'message'=>'Upload succeded', 'data'=>$res));
            } else {
                $app->response()->status(500);
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>false, 'message'=>'Upload failed', 'data'=>''));
            }
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($document === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No such document', 'data'=>''));
    }
} /* }}} */

function getDocument($id) { /* {{{ */
    global $app, $dms, $userobj;
    $document = $dms->getDocument($id);
    if($document) {
        if ($document->getAccessMode($userobj) >= M_READ) {
            $lc = $document->getLatestContent();
            if($lc) {
                $data = __getLatestVersionData($lc);
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$data));
            } else {
                $app->response()->status(403);
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
            }
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($document === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No document', 'data'=>''));
    }
} /* }}} */

function deleteDocument($id) { /* {{{ */
    global $app, $dms, $userobj;
    $document = $dms->getDocument($id);
    if($document) {
        if ($document->getAccessMode($userobj, 'deleteDocument') >= M_READWRITE) {
            if($document->remove()) {
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>true, 'message'=>'', 'data'=>''));
            } else {
                $app->response()->status(500);
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>false, 'message'=>'Error removing document', 'data'=>''));
            }
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($document === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No document', 'data'=>''));
    }
} /* }}} */

function moveDocument($id, $folderid) { /* {{{ */
    global $app, $dms, $userobj;
    $document = $dms->getDocument($id);
    if($document) {
        if ($document->getAccessMode($userobj, 'moveDocument') >= M_READ) {
            if($folder = $dms->getFolder($folderid)) {
                if($folder->getAccessMode($userobj, 'moveDocument') >= M_READWRITE) {
                    if($document->setFolder($folder)) {
                        $app->response()->header('Content-Type', 'application/json');
                        echo json_encode(array('success'=>true, 'message'=>'', 'data'=>''));
                    } else {
                        $app->response()->status(500);
                        $app->response()->header('Content-Type', 'application/json');
                        echo json_encode(array('success'=>false, 'message'=>'Error moving document', 'data'=>''));
                    }
                } else {
                    $app->response()->status(403);
                    $app->response()->header('Content-Type', 'application/json');
                    echo json_encode(array('success'=>false, 'message'=>'No access on destination folder', 'data'=>''));
                }
            } else {
              if($folder === null)
                  $app->response()->status(400);
              else
                  $app->response()->status(500);
                $app->response()->header('Content-Type', 'application/json');
                echo json_encode(array('success'=>false, 'message'=>'No destination folder', 'data'=>''));
            }
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($document === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No document', 'data'=>''));
    }
} /* }}} */

function getDocumentContent($id) { /* {{{ */
    global $app, $dms, $userobj;
    $document = $dms->getDocument($id);

    if($document) {
        if ($document->getAccessMode($userobj) >= M_READ) {
            $lc = $document->getLatestContent();
            if($lc) {
              if (pathinfo($document->getName(), PATHINFO_EXTENSION) == $lc->getFileType())
                  $filename = $document->getName();
              else
                  $filename = $document->getName().$lc->getFileType();

              $app->response()->header('Content-Type', $lc->getMimeType());
              $app->response()->header("Content-Disposition", "filename=\"" . $filename . "\"");
              $app->response()->header("Content-Length", filesize($dms->contentDir . $lc->getPath()));
              $app->response()->header("Expires", "0");
              $app->response()->header("Cache-Control", "no-cache, must-revalidate");
              $app->response()->header("Pragma", "no-cache");

              sendFile($dms->contentDir . $lc->getPath());
            } else {
              $app->response()->status(403);
              $app->response()->header('Content-Type', 'application/json');
              echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
            }
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($document === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No document', 'data'=>''));
    }

} /* }}} */

function getDocumentVersions($id) { /* {{{ */
    global $app, $dms, $userobj;
    $document = $dms->getDocument($id);

    if($document) {
        if ($document->getAccessMode($userobj) >= M_READ) {
            $recs = array();
            $lcs = $document->getContent();
            foreach($lcs as $lc) {
                $recs[] = array(
                    'version'=>$lc->getVersion(),
                    'date'=>$lc->getDate(),
                    'mimetype'=>$lc->getMimeType(),
                    'size'=>$lc->getFileSize(),
                    'comment'=>$lc->getComment(),
                );
            }
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$recs));
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($document === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No document', 'data'=>''));
    }
} /* }}} */

function getDocumentVersion($id, $version) { /* {{{ */
    global $app, $dms, $userobj;
    $document = $dms->getDocument($id);

    if($document) {
        if ($document->getAccessMode($userobj) >= M_READ) {
            $lc = $document->getContentByVersion($version);
            if($lc) {
              if (pathinfo($document->getName(), PATHINFO_EXTENSION) == $lc->getFileType())
                  $filename = $document->getName();
              else
                  $filename = $document->getName().$lc->getFileType();
              $app->response()->header('Content-Type', $lc->getMimeType());
              $app->response()->header("Content-Disposition", "filename=\"" . $filename . "\"");
              $app->response()->header("Content-Length", filesize($dms->contentDir . $lc->getPath()));
              $app->response()->header("Expires", "0");
              $app->response()->header("Cache-Control", "no-cache, must-revalidate");
              $app->response()->header("Pragma", "no-cache");

              sendFile($dms->contentDir . $lc->getPath());
            } else {
              $app->response()->status(403);
              $app->response()->header('Content-Type', 'application/json');
              echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
            }
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($document === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No document', 'data'=>''));
    }
} /* }}} */

function getDocumentFiles($id) { /* {{{ */
    global $app, $dms, $userobj;
    $document = $dms->getDocument($id);

    if($document) {
        if ($document->getAccessMode($userobj) >= M_READ) {
            $recs = array();
            $files = $document->getDocumentFiles();
            foreach($files as $file) {
                $recs[] = array(
                    'id'=>(int)$file->getId(),
                    'name'=>$file->getName(),
                    'date'=>$file->getDate(),
                    'mimetype'=>$file->getMimeType(),
                    'comment'=>$file->getComment(),
                );
            }
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$recs));
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($document === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No document', 'data'=>''));
    }
} /* }}} */

function getDocumentFile($id, $fileid) { /* {{{ */
    global $app, $dms, $userobj;
    $document = $dms->getDocument($id);

    if($document) {
        if ($document->getAccessMode($userobj) >= M_READ) {
            $file = $document->getDocumentFile($fileid);
            $app->response()->header('Content-Type', $file->getMimeType());
            $app->response()->header("Content-Disposition", "filename=\"" . $document->getName().$file->getFileType() . "\"");
            $app->response()->header("Content-Length", filesize($dms->contentDir . $file->getPath()));
            $app->response()->header("Expires", "0");
            $app->response()->header("Cache-Control", "no-cache, must-revalidate");
            $app->response()->header("Pragma", "no-cache");

            sendFile($dms->contentDir . $file->getPath());
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($document === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No document', 'data'=>''));
    }
} /* }}} */

function getDocumentLinks($id) { /* {{{ */
    global $app, $dms, $userobj;
    $document = $dms->getDocument($id);

    if($document) {
        if ($document->getAccessMode($userobj) >= M_READ) {
            $recs = array();
            $links = $document->getDocumentLinks();
            foreach($links as $link) {
                $recs[] = array(
                    'id'=>(int)$link->getId(),
                    'target'=>$link->getTarget(),
                    'public'=>$link->isPublic(),
                );
            }
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$recs));
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($document === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No document', 'data'=>''));
    }
} /* }}} */

function getDocumentAttributes($id) { /* {{{ */
    global $app, $dms, $userobj;
    $document = $dms->getDocument($id);

    if($document) {
        if ($document->getAccessMode($userobj) >= M_READ) {
            $recs = array();
            $attributes = $document->getAttributes();
            foreach($attributes as $attribute) {
                $recs[] = array(
                    'id'=>(int)$attribute->getId(),
                    'value'=>$attribute->getValue(),
                    'name'=>$attribute->getAttributeDefinition()->getName(),
                );
            }
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$recs));
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($document === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No document', 'data'=>''));
    }
} /* }}} */

function getDocumentPreview($id, $version=0, $width=0) { /* {{{ */
    global $app, $dms, $userobj, $settings;
    require_once "LetoDMS/Preview.php";
    $document = $dms->getDocument($id);

    if($document) {
        if ($document->getAccessMode($userobj) >= M_READ) {
            if($version)
                $object = $document->getContentByVersion($version);
            else
                $object = $document->getLatestContent();
            if(!$object)
                exit;

            if(!empty($width))
                $previewer = new LetoDMS_Preview_Previewer($settings->_cacheDir, $width);
            else
                $previewer = new LetoDMS_Preview_Previewer($settings->_cacheDir);
            if(!$previewer->hasPreview($object))
                $previewer->createPreview($object);
            $app->response()->header('Content-Type', 'image/png');
            $app->response()->header("Content-Disposition", "filename=\"preview-" . $document->getID()."-".$object->getVersion()."-".$width.".png" . "\"");
            $app->response()->header("Content-Length", $previewer->getFilesize($object));
//            $app->response()->header("Expires", "0");
//            $app->response()->header("Cache-Control", "no-cache, must-revalidate");
//            $app->response()->header("Pragma", "no-cache");

            $previewer->getPreview($object);
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($document === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No document', 'data'=>''));
    }
} /* }}} */

function removeDocumentCategory($id, $categoryId) { /* {{{ */
    global $app, $dms, $userobj;
    $document = $dms->getDocument($id);
    $category = $dms->getDocumentCategory($categoryId);

    if($document && $category) {
        if ($document->getAccessMode($userobj, 'removeDocumentCategory') >= M_READWRITE) {
            $ret = $document->removeCategories(array($category));

            $app->response()->header('Content-Type', 'application/json');
            if ($ret)
                echo json_encode(array('success'=>true, 'message'=>'Deleted category successfully.', 'data'=>''));
            else
                echo json_encode(array('success'=>true, 'message'=>'', 'data'=>''));
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($document === null || $category === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No such document', 'data'=>''));
    }
} /* }}} */

function removeDocumentCategories($id) { /* {{{ */
    global $app, $dms, $userobj;
    $document = $dms->getDocument($id);

    if($document) {
        if ($document->getAccessMode($userobj, 'removeDocumentCategory') >= M_READWRITE) {
            $app->response()->header('Content-Type', 'application/json');
            if($document->setCategories(array()))
                echo json_encode(array('success'=>true, 'message'=>'Deleted categories successfully.', 'data'=>''));
            else
                echo json_encode(array('success'=>false, 'message'=>'', 'data'=>''));
        } else {
            $app->response()->status(403);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'No access', 'data'=>''));
        }
    } else {
        if($document === null)
            $app->response()->status(400);
        else
            $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No such document', 'data'=>''));
    }
} /* }}} */

function getAccount() { /* {{{ */
    global $app, $dms, $userobj;
    if($userobj) {
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'', 'data'=>__getUserData($userobj)));
    } else {
        $app->response()->status(403);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Not logged in', 'data'=>''));
    }
} /* }}} */

/**
 * Search for documents in the database
 *
 * If the request parameter 'mode' is set to 'typeahead', it will
 * return a list of words only.
 */
function doSearch() { /* {{{ */
    global $app, $dms, $userobj;

    $querystr = $app->request()->get('query');
    $mode = $app->request()->get('mode');
    if(!$limit = $app->request()->get('limit'))
        $limit = 5;
    $resArr = $dms->search($querystr);
    if($resArr === false) {
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array());
    }
    $entries = array();
    $count = 0;
    if($resArr['folders']) {
        foreach ($resArr['folders'] as $entry) {
            if ($entry->getAccessMode($userobj) >= M_READ) {
                $entries[] = $entry;
                $count++;
            }
            if($count >= $limit)
                break;
        }
    }
    $count = 0;
    if($resArr['docs']) {
        foreach ($resArr['docs'] as $entry) {
            $lc = $entry->getLatestContent();
            if ($entry->getAccessMode($userobj) >= M_READ && $lc) {
                $entries[] = $entry;
                $count++;
            }
            if($count >= $limit)
                break;
        }
    }

    switch($mode) {
        case 'typeahead';
            $recs = array();
            foreach ($entries as $entry) {
            /* Passing anything back but a string does not work, because
             * the process function of bootstrap.typeahead needs an array of
             * strings.
             *
             * As a quick solution to distingish folders from documents, the
             * name will be preceeded by a 'F' or 'D'

                $tmp = array();
                if(get_class($entry) == 'LetoDMS_Core_Document') {
                    $tmp['type'] = 'folder';
                } else {
                    $tmp['type'] = 'document';
                }
                $tmp['id'] = $entry->getID();
                $tmp['name'] = $entry->getName();
                $tmp['comment'] = $entry->getComment();
             */
                if(get_class($entry) == 'LetoDMS_Core_Document') {
                    $recs[] = 'D'.$entry->getName();
                } else {
                    $recs[] = 'F'.$entry->getName();
                }
            }
            if($recs)
//                array_unshift($recs, array('type'=>'', 'id'=>0, 'name'=>$querystr, 'comment'=>''));
                array_unshift($recs, ' '.$querystr);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode($recs);
            //echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$recs));
            break;
        default:
            $recs = array();
            foreach ($entries as $entry) {
                if(get_class($entry) == 'LetoDMS_Core_Document') {
                    $document = $entry;
                    $lc = $document->getLatestContent();
                    if($lc) {
                        $recs[] = __getLatestVersionData($lc);
                    }
                } elseif(get_class($entry) == 'LetoDMS_Core_Folder') {
                    $folder = $entry;
                    $recs[] = __getFolderData($folder);
                }
            }
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$recs));
            break;
    }
} /* }}} */

/**
 * Search for documents/folders with a given attribute=value
 *
 */
function doSearchByAttr() { /* {{{ */
    global $app, $dms, $userobj;

    $attrname = $app->request()->get('name');
    $query = $app->request()->get('value');
    if(!$limit = $app->request()->get('limit'))
        $limit = 50;
    $attrdef = $dms->getAttributeDefinitionByName($attrname);
    $entries = array();
    if($attrdef) {
        $resArr = $attrdef->getObjects($query, $limit);
        if($resArr['folders']) {
            foreach ($resArr['folders'] as $entry) {
                if ($entry->getAccessMode($userobj) >= M_READ) {
                    $entries[] = $entry;
                }
            }
        }
        if($resArr['docs']) {
            foreach ($resArr['docs'] as $entry) {
                if ($entry->getAccessMode($userobj) >= M_READ) {
                    $entries[] = $entry;
                }
            }
        }
    }
    $recs = array();
    foreach ($entries as $entry) {
        if(get_class($entry) == 'LetoDMS_Core_Document') {
            $document = $entry;
            $lc = $document->getLatestContent();
            if($lc) {
                $recs[] = __getLatestVersionData($lc);
            }
        } elseif(get_class($entry) == 'LetoDMS_Core_Folder') {
            $folder = $entry;
            $recs[] = __getFolderData($folder);
        }
    }
    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$recs));
} /* }}} */

function checkIfAdmin() { /* {{{ */
    global $app, $dms, $userobj;

    if(!$userobj) {
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Not logged in', 'data'=>''));
        return;
    }
    if(!$userobj->isAdmin()) {
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'You must be logged in with an administrator account to access this resource', 'data'=>''));
        return;
    }

    return true;
} /* }}} */

function getUsers() { /* {{{ */
    global $app, $dms, $userobj;

    checkIfAdmin();

    $users = $dms->getAllUsers();
    $data = [];
    foreach($users as $u)
    $data[] = __getUserData($u);

    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$data));
} /* }}} */

function createUser() { /* {{{ */
    global $app, $dms, $userobj;

    checkIfAdmin();

    $userName = $app->request()->post('user');
    $password = $app->request()->post('pass');
    $fullname = $app->request()->post('name');
    $email = $app->request()->post('email');
    $language = $app->request()->post('language');
    $theme = $app->request()->post('theme');
    $comment = $app->request()->post('comment');
    $role = $app->request()->post('role');
    $roleid = $role == 'admin' ? LetoDMS_Core_User::role_admin : ($role == 'guest' ? LetoDMS_Core_User::role_guest : LetoDMS_Core_User::role_user);

    $newAccount = $dms->addUser($userName, $password, $fullname, $email, $language, $theme, $comment, $roleid);
    if ($newAccount === false) {
        $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Account could not be created, maybe it already exists', 'data'=>''));
        return;
    }

    $result = __getUserData($newAccount);
    $app->response()->status(201);
    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$result));
    return;
} /* }}} */

function deleteUser($id) { /* {{{ */
    global $app, $dms, $userobj;
    checkIfAdmin();

    $app->response()->header('Content-Type', 'application/json');
    if($user = $dms->getUser($id)) {
        if($result = $user->remove($userobj, $userobj)) {
            echo json_encode(array('success'=>$result, 'message'=>'', 'data'=>''));
        } else {
            $app->response()->status(500);
            echo json_encode(array('success'=>$result, 'message'=>'Could not delete user', 'data'=>''));
        }
    } else {
        $app->response()->status(404);
        echo json_encode(array('success'=>false, 'message'=>'No such user', 'data'=>''));
    }
} /* }}} */

/**
 * Updates the password of an existing Account, the password must be PUT as a md5 string
 *
 * @param      <type>  $id     The user name or numerical identifier
 */
function changeUserPassword($id) { /* {{{ */
    global $app, $dms, $userobj;

    checkIfAdmin();

    if ($app->request()->put('password') == null)
    {
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'You must supply a new password', 'data'=>''));
        return;
    }

    $newPassword = $app->request()->put('password');

    if(ctype_digit($id))
        $account = $dms->getUser($id);
    else {
        $account = $dms->getUserByLogin($id);
    }

    /**
     * User not found
     */
    if (!$account) {
        $app->response()->status(404);
        return;
    }

    $operation = $account->setPwd($newPassword);

    if (!$operation){
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'', 'data'=>'Could not change password.'));
        return;
    }

    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>''));

    return;
} /* }}} */

function getUserById($id) { /* {{{ */
    global $app, $dms, $userobj;
    checkIfAdmin();
    if(ctype_digit($id))
        $account = $dms->getUser($id);
    else {
        $account = $dms->getUserByLogin($id);
    }
    if($account) {
        $data = __getUserData($account);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$data));
    } else {
        $app->response()->status(404);
    }
} /* }}} */

function setDisabledUser($id) { /* {{{ */
    global $app, $dms, $userobj;
    checkIfAdmin();
    if ($app->request()->put('disable') == null) {
        $app->response()->status(400);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'You must supply a disabled state', 'data'=>''));
        return;
    }

    $isDisabled = false;
    $status = $app->request()->put('disable');
    if ($status == 'true' || $status == '1') {
        $isDisabled = true;
    }

    if(ctype_digit($id))
        $account = $dms->getUser($id);
    else {
        $account = $dms->getUserByLogin($id);
    }

    if($account) {
        $account->setDisabled($isDisabled);
        $data = __getUserData($account);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$data));
    } else {
        $app->response()->status(404);
    }
} /* }}} */

function createGroup() { /* {{{ */
    global $app, $dms, $userobj;
    checkIfAdmin();
    $groupName = $app->request()->post('name');
    $comment = $app->request()->post('comment');

    $newGroup = $dms->addGroup($groupName, $comment);
    if ($newGroup === false) {
        $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Group could not be created, maybe it already exists', 'data'=>''));
        return;
    }

    $result = array('id'=>(int)$newGroup->getID());
    $app->response()->status(201);
    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$result));
    return;
} /* }}} */

function getGroup($id) { /* {{{ */
    global $app, $dms, $userobj;
    checkIfAdmin();
    if(ctype_digit($id))
        $group = $dms->getGroup($id);
    else {
        $group = $dms->getGroupByName($id);
    }
    if($group) {
        $data = __getGroupData($group);
        $data['users'] = array();
        foreach ($group->getUsers() as $user) {
            $data['users'][] =  array('id' => (int)$user->getID(), 'login' => $user->getLogin());
        }
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$data));
    } else {
        $app->response()->status(404);
    }
} /* }}} */

function changeGroupMembership($id, $operationType) { /* {{{ */
    global $app, $dms, $userobj;
    checkIfAdmin();

    if(ctype_digit($id))
        $group = $dms->getGroup($id);
    else {
        $group = $dms->getGroupByName($id);
    }

    if ($app->request()->put('userid') == null)
    {
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Please PUT the userid', 'data'=>''));
        return;
    }
    $userId = $app->request()->put('userid');
    if(ctype_digit($userId))
        $user = $dms->getUser($userId);
    else {
        $user = $dms->getUserByLogin($userId);
    }

    if (!($group && $user)) {
        $app->response()->status(404);
    }

    $operationResult = false;

    if ($operationType == 'add')
    {
        $operationResult = $group->addUser($user);
    }
    if ($operationType == 'remove')
    {
        $operationResult = $group->removeUser($user);
    }

    if ($operationResult === false)
    {
        $app->response()->header('Content-Type', 'application/json');
        $message = 'Could not add user to the group.';
        if ($operationType == 'remove')
        {
            $message = 'Could not remove user from group.';
        }
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Something went wrong. ' . $message, 'data'=>''));
        return;
    }

    $data = __getGroupData($group);
    $data['users'] = array();
    foreach ($group->getUsers() as $userObj) {
        $data['users'][] =  array('id' => (int)$userObj->getID(), 'login' => $userObj->getLogin());
    }
    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$data));
} /* }}} */

function addUserToGroup($id) { /* {{{ */
    changeGroupMembership($id, 'add');
} /* }}} */

function removeUserFromGroup($id) { /* {{{ */
    changeGroupMembership($id, 'remove');
} /* }}} */

function setFolderInheritsAccess($id) { /* {{{ */
    global $app, $dms, $userobj;
    checkIfAdmin();
    if ($app->request()->put('enable') == null)
    {
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'You must supply an "enable" value', 'data'=>''));
        return;
    }

    $inherit = false;
    $status = $app->request()->put('enable');
    if ($status == 'true' || $status == '1')
    {
        $inherit = true;
    }

    if(ctype_digit($id))
        $folder = $dms->getFolder($id);
    else {
        $folder = $dms->getFolderByName($id);
    }

    if($folder) {
        $folder->setInheritAccess($inherit);
        $folderId = $folder->getId();
        $folder = null;
        // reread from db
        $folder = $dms->getFolder($folderId);
        $success = ($folder->inheritsAccess() == $inherit);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>$success, 'message'=>'', 'data'=>$data));
    } else {
        $app->response()->status(404);
    }
} /* }}} */

function addUserAccessToFolder($id) { /* {{{ */
    changeFolderAccess($id, 'add', 'user');
} /* }}} */

function addGroupAccessToFolder($id) { /* {{{ */
    changeFolderAccess($id, 'add', 'group');
} /* }}} */

function removeUserAccessFromFolder($id) { /* {{{ */
    changeFolderAccess($id, 'remove', 'user');
} /* }}} */

function removeGroupAccessFromFolder($id) { /* {{{ */
    changeFolderAccess($id, 'remove', 'group');
} /* }}} */

function changeFolderAccess($id, $operationType, $userOrGroup) { /* {{{ */
    global $app, $dms, $userobj;
    checkIfAdmin();

    if(ctype_digit($id))
        $folder = $dms->getfolder($id);
    else {
        $folder = $dms->getfolderByName($id);
    }
    if (!$folder) {
        $app->response()->status(404);
        return;
    }

    $userOrGroupIdInput = $app->request()->put('id');
    if ($operationType == 'add')
    {
        if ($app->request()->put('id') == null)
        {
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'Please PUT the user or group Id', 'data'=>''));
            return;
        }

        if ($app->request()->put('mode') == null)
        {
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'Please PUT the access mode', 'data'=>''));
            return;
        }

        $modeInput = $app->request()->put('mode');

        $mode = M_NONE;
        if ($modeInput == 'read')
        {
            $mode = M_READ;
        }
        if ($modeInput == 'readwrite')
        {
            $mode = M_READWRITE;
        }
        if ($modeInput == 'all')
        {
            $mode = M_ALL;
        }
    }


    $userOrGroupId = $userOrGroupIdInput;
    if(!ctype_digit($userOrGroupIdInput) && $userOrGroup == 'user')
    {
        $userOrGroupObj = $dms->getUserByLogin($userOrGroupIdInput);
    }
    if(!ctype_digit($userOrGroupIdInput) && $userOrGroup == 'group')
    {
        $userOrGroupObj = $dms->getGroupByName($userOrGroupIdInput);
    }
    if(ctype_digit($userOrGroupIdInput) && $userOrGroup == 'user')
    {
        $userOrGroupObj = $dms->getUser($userOrGroupIdInput);
    }
    if(ctype_digit($userOrGroupIdInput) && $userOrGroup == 'group')
    {
        $userOrGroupObj = $dms->getGroup($userOrGroupIdInput);
    }
    if (!$userOrGroupObj) {
        $app->response()->status(404);
        return;
    }
    $userOrGroupId = $userOrGroupObj->getId();

    $operationResult = false;

    if ($operationType == 'add' && $userOrGroup == 'user')
    {
        $operationResult = $folder->addAccess($mode, $userOrGroupId, true);
    }
    if ($operationType == 'remove' && $userOrGroup == 'user')
    {
        $operationResult = $folder->removeAccess($userOrGroupId, true);
    }

    if ($operationType == 'add' && $userOrGroup == 'group')
    {
        $operationResult = $folder->addAccess($mode, $userOrGroupId, false);
    }
    if ($operationType == 'remove' && $userOrGroup == 'group')
    {
        $operationResult = $folder->removeAccess($userOrGroupId, false);
    }

    if ($operationResult === false)
    {
        $app->response()->header('Content-Type', 'application/json');
        $message = 'Could not add user/group access to this folder.';
        if ($operationType == 'remove')
        {
            $message = 'Could not remove user/group access from this folder.';
        }
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Something went wrong. ' . $message, 'data'=>''));
        return;
    }

    $data = array();
    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$data));
} /* }}} */

function getCategories() { /* {{{ */
    global $app, $dms, $userobj;

    if(false === ($categories = $dms->getDocumentCategories())) {
        $app->response()->status(500);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Could not get categories', 'data'=>null));
        return;
    }
    $data = [];
    foreach($categories as $category)
        $data[] = ['id' => (int)$category->getId(), 'name' => $category->getName()];

    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$data));
} /* }}} */

function getCategory($id) { /* {{{ */
    global $app, $dms, $userobj;

    if(!ctype_digit($id)) {
        $app->response()->status(400);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'No such category', 'data'=>''));
        return;
    }

    $category = $dms->getDocumentCategory($id);
    if($category) {
        $data = array();
        $data['id'] = (int)$category->getId();
        $data['name'] = $category->getName();
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$data));
    } else {
        $app->response()->status(404);
    }
} /* }}} */

function createCategory() { /* {{{ */
    global $app, $dms, $userobj;
    checkIfAdmin();

    $category = $app->request()->params("category");
    if ($category == null) {
        $app->response()->status(400);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Need a category.', 'data'=>''));
        return;
    }

    $catobj = $dms->getDocumentCategoryByName($category);
    if($catobj) {
        $app->response()->status(409);
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'Category already exists', 'data'=>''));
    } else {
        if($data = $dms->addDocumentCategory($category)) {
            $app->response()->status(201);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>true, 'message'=>'', 'data'=>array('id'=>(int)$data->getID())));
        } else {
            $app->response()->status(500);
            $app->response()->header('Content-Type', 'application/json');
            echo json_encode(array('success'=>false, 'message'=>'Could not add category', 'data'=>''));
        }
    }
} /* }}} */

function deleteCategory($id) { /* {{{ */
    global $app, $dms, $userobj;
    checkIfAdmin();

    $app->response()->header('Content-Type', 'application/json');
    if($category = $dms->getDocumentCategory($id)) {
        if($result = $category->remove()) {
            echo json_encode(array('success'=>$result, 'message'=>'', 'data'=>''));
        } else {
            $app->response()->status(500);
            echo json_encode(array('success'=>$result, 'message'=>'Could not delete category', 'data'=>''));
        }
    } else {
        $app->response()->status(404);
        echo json_encode(array('success'=>false, 'message'=>'No such category', 'data'=>''));
    }
} /* }}} */

/**
 * Updates the name of an existing category
 *
 * @param      <type>  $id     The user name or numerical identifier
 */
function changeCategoryName($id) { /* {{{ */
    global $app, $dms, $userobj;

    checkIfAdmin();

    if ($app->request()->put('name') == null)
    {
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'You must supply a new name', 'data'=>''));
        return;
    }

    $newname = $app->request()->put('name');

    $category = null;
    if(ctype_digit($id))
        $category = $dms->getDocumentCategory($id);

    /**
     * Category not found
     */
    if (!$category) {
        $app->response()->status(404);
        return;
    }

    if (!$category->setName($newname)) {
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'', 'data'=>'Could not change name.'));
        return;
    }

    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>''));

    return;
} /* }}} */

function getAttributeDefinitions() { /* {{{ */
    global $app, $dms, $userobj;

    $attrdefs = $dms->getAllAttributeDefinitions();
    $data = [];
    foreach($attrdefs as $attrdef)
        $data[] = ['id' => (int)$attrdef->getId(), 'name' => $attrdef->getName(), 'type'=>(int)$attrdef->getType(), 'objtype'=>(int)$attrdef->getObjType(), 'min'=>(int)$attrdef->getMinValues(), 'max'=>(int)$attrdef->getMaxValues(), 'multiple'=>$attrdef->getMultipleValues()?true:false, 'valueset'=>$attrdef->getValueSetAsArray()];

    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>$data));
} /* }}} */

/**
 * Updates the name of an existing attribute definition
 *
 * @param      <type>  $id     The user name or numerical identifier
 */
function changeAttributeDefinitionName($id) { /* {{{ */
    global $app, $dms, $userobj;

    checkIfAdmin();

    if ($app->request()->put('name') == null)
    {
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'You must supply a new name', 'data'=>''));
        return;
    }

    $newname = $app->request()->put('name');

    $attrdef = null;
    if(ctype_digit($id))
        $attrdef = $dms->getAttributeDefinition($id);

    /**
     * Category not found
     */
    if (!$attrdef) {
        $app->response()->status(404);
        return;
    }

    if (!$attrdef->setName($newname)) {
        $app->response()->header('Content-Type', 'application/json');
        echo json_encode(array('success'=>false, 'message'=>'', 'data'=>'Could not change name.'));
        return;
    }

    $app->response()->header('Content-Type', 'application/json');
    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>''));

    return;
} /* }}} */

function clearFolderAccessList($id) { /* {{{ */
    global $app, $dms, $userobj;
    checkIfAdmin();

    if(ctype_digit($id))
        $folder = $dms->getFolder($id);
    else {
        $folder = $dms->getFolderByName($id);
    }
    if (!$folder) {
        $app->response()->status(404);
        return;
    }
    $app->response()->header('Content-Type', 'application/json');
    if (!$folder->clearAccessList()) {
        echo json_encode(array('success'=>false, 'message'=>'Something went wrong. Could not clear access list for this folder.', 'data'=>''));
    }
    echo json_encode(array('success'=>true, 'message'=>'', 'data'=>''));
} /* }}} */

function echoData() { /* {{{ */
    global $app;

    echo $app->request->getBody();
} /* }}} */

//$app = new Slim(array('mode'=>'development', '_session.handler'=>null));
$app = new \Slim\Slim(array('mode'=>'production', '_session.handler'=>null));

$app->configureMode('production', function () use ($app) {
    $app->config(array(
        'log.enable' => false,
        'debug' => false
    ));
});

$app->configureMode('development', function () use ($app) {
    $app->config(array(
        'log.enable' => true,
        'debug' => true
    ));
});

// use post for create operation
// use get for retrieval operation
// use put for update operation
// use delete for delete operation
$app->post('/login', 'doLogin');
$app->get('/logout', 'doLogout');
$app->get('/account', 'getAccount');
$app->get('/search', 'doSearch');
$app->get('/searchbyattr', 'doSearchByAttr');
$app->get('/folder/', 'getFolder');
$app->get('/folder/:id', 'getFolder');
$app->post('/folder/:id/move/:folderid', 'moveFolder');
$app->delete('/folder/:id', 'deleteFolder');
$app->get('/folder/:id/children', 'getFolderChildren');
$app->get('/folder/:id/parent', 'getFolderParent');
$app->get('/folder/:id/path', 'getFolderPath');
$app->get('/folder/:id/attributes', 'getFolderAttributes');
$app->post('/folder/:id/createfolder', 'createFolder');
$app->put('/folder/:id/document', 'uploadDocumentPut');
$app->post('/folder/:id/document', 'uploadDocument');
$app->get('/document/:id', 'getDocument');
$app->post('/document/:id/attachment', 'uploadDocumentFile');
$app->delete('/document/:id', 'deleteDocument');
$app->post('/document/:id/move/:folderid', 'moveDocument');
$app->get('/document/:id/content', 'getDocumentContent');
$app->get('/document/:id/versions', 'getDocumentVersions');
$app->get('/document/:id/version/:version', 'getDocumentVersion');
$app->get('/document/:id/files', 'getDocumentFiles');
$app->get('/document/:id/file/:fileid', 'getDocumentFile');
$app->get('/document/:id/links', 'getDocumentLinks');
$app->get('/document/:id/attributes', 'getDocumentAttributes');
$app->get('/document/:id/preview/:version/:width', 'getDocumentPreview');
$app->delete('/document/:id/categories', 'removeDocumentCategories');
$app->delete('/document/:id/category/:categoryId', 'removeDocumentCategory');
$app->put('/account/fullname', 'setFullName');
$app->put('/account/email', 'setEmail');
$app->get('/account/documents/locked', 'getLockedDocuments');
$app->get('/users', 'getUsers');
$app->delete('/users/:id', 'deleteUser');
$app->post('/users', 'createUser');
$app->get('/users/:id', 'getUserById');
$app->put('/users/:id/disable', 'setDisabledUser');
$app->put('/users/:id/password', 'changeUserPassword');
$app->post('/groups', 'createGroup');
$app->get('/groups/:id', 'getGroup');
$app->put('/groups/:id/addUser', 'addUserToGroup');
$app->put('/groups/:id/removeUser', 'removeUserFromGroup');
$app->put('/folder/:id/setInherit', 'setFolderInheritsAccess');
$app->put('/folder/:id/access/group/add', 'addGroupAccessToFolder'); //
$app->put('/folder/:id/access/user/add', 'addUserAccessToFolder'); //
$app->put('/folder/:id/access/group/remove', 'removeGroupAccessFromFolder');
$app->put('/folder/:id/access/user/remove', 'removeUserAccessFromFolder');
$app->put('/folder/:id/access/clear', 'clearFolderAccessList');
$app->get('/categories', 'getCategories');
$app->get('/categories/:id', 'getCategory');
$app->delete('/categories/:id', 'deleteCategory');
$app->post('/categories', 'createCategory');
$app->put('/categories/:id/name', 'changeCategoryName');
$app->get('/attributedefinitions', 'getAttributeDefinitions');
$app->put('/attributedefinitions/:id/name', 'changeAttributeDefinitionName');
$app->any('/echo', 'echoData');
$app->run();

?>
