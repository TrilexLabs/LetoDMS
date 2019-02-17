<?php
/**
 * Implementation of OpensearchDesc view
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
 * Class which outputs the html page for OpensearchDesc view
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2018 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_View_OpensearchDesc extends LetoDMS_Bootstrap_Style {

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$sitename = $this->params['sitename'];
		$settings = $this->params['settings'];

		ob_get_clean();
		header("Content-Disposition: attachment; filename=\"opensearch_desc.xml\"");
		header('Content-Type: application/opensearchdescription+xml');
?>
<?xml version="1.0" encoding="UTF-8"?>
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/" xmlns:moz="http://www.mozilla.org/2006/browser/search/">
	<ShortName><?= $sitename ?></ShortName>
	<Description><?= $sitename ?></Description>
	<Image height="16" width="16" type="image/x-icon"><?= "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot ?>styles/<?= $this->theme ?>/favicon.ico</Image>
	<Url type="text/html" method="get" template="<?= "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.Search.php?query={searchTerms}" ?>" />
	<moz:SearchForm><?= "http".((isset($_SERVER['HTTPS']) && (strcmp($_SERVER['HTTPS'],'off')!=0)) ? "s" : "")."://".$_SERVER['HTTP_HOST'].$settings->_httpRoot."out/out.Search.php" ?></moz:SearchForm>
</OpenSearchDescription>
<?php
	} /* }}} */

}
