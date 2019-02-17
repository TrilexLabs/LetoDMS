<?php
//    LetoDMS. Document Management System
//    Copyright (C) 2013 Uwe Steinmann
//
//    This program is free software; you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation; either version 2 of the License, or
//    (at your option) any later version.
//
//    This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with this program; if not, write to the Free Software
//    Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

class LetoDMS_Controller_Common {
	/**
	 * @var array $params list of parameters
	 * @access protected
	 */
	protected $params;

	/**
	 * @var integer $error error number of last run
	 * @access protected
	 */
	protected $error;

	/**
	 * @var string $errormsg error message of last run
	 * @access protected
	 */
	protected $errormsg;

	function __construct($params) {
		$this->params = $params;
		$this->error = 0;
		$this->errormsg = '';
	}

	/**
	 * Call methods with name in $get['action']
	 *
	 * @params array $get $_GET or $_POST variables
	 * @return mixed return value of called method
	 */
	function __invoke($get=array()) {
		if(isset($get['action']) && $get['action']) {
			if(method_exists($this, $get['action'])) {
				return $this->{$get['action']}();
			} else {
				echo "Missing action '".$get['action']."'";
				return false;
			}
		} else
			return $this->run();
	}

	function setParams($params) {
		$this->params = $params;
	}

	function setParam($name, $value) {
		$this->params[$name] = $value;
	}

	/**
	 * Return value of a parameter with the given name
	 *
	 * This function may return null if the parameter does not exist or
	 * has a value of null. If in doubt call hasParam() to check if the
	 * parameter exists.
	 *
	 * @param string $name name of parameter
	 * @return mixed value of parameter or null if parameter does not exist
	 */
	function getParam($name) {
		return isset($this->params[$name]) ? $this->params[$name] : null;
	}

	/**
	 * Check if the controller has a parameter with the given name
	 *
	 * @param string $name name of parameter
	 * @return boolean true if parameter exists otherwise false
	 */
	function hasParam($name) {
		return isset($this->params[$name]) ? true : false;
	}

	/**
	 * Remove a parameter with the given name
	 *
	 * @param string $name name of parameter
	 */
	function unsetParam($name) {
		if(isset($this->params[$name]))
			unset($this->params[$name]);
	}

	function run() {
	}

	/**
	 * Get error number of last run
	 *
	 * @return integer error number
	 */
	public function getErrorNo() { /* {{{ */
		return $this->error;
	} /* }}} */

	/**
	 * Get error message of last run
	 *
	 * @return string error message
	 */
	public function getErrorMsg() { /* {{{ */
		return $this->errormsg;
	} /* }}} */

	/**
	 * Set error message
	 *
	 * @param string $msg error message
	 */
	public function setErrorMsg($msg) { /* {{{ */
		$this->errormsg = $msg;
	} /* }}} */

	/**
	 * Call a controller hook
	 *
	 * If a hook returns false, then no other hook will be called, because the
	 * method returns right away. If hook returns null, then this is treated like
	 * it was never called and the default action is executed. Any other value
	 * returned by the hook will be returned by this method.
	 *
	 * @param $hook string name of hook
	 * @return mixed false if one of the hooks fails,
	 *               true if all hooks succedded,
	 *               null if no hook was called
	 */
	function callHook($hook) { /* {{{ */
		$tmp = explode('_', get_class($this));
		if(isset($GLOBALS['LetoDMS_HOOKS']['controller'][lcfirst($tmp[2])])) {
			$r = null;
			foreach($GLOBALS['LetoDMS_HOOKS']['controller'][lcfirst($tmp[2])] as $hookObj) {
				if (method_exists($hookObj, $hook)) {
					switch(func_num_args()) {
						case 3:
							$result = $hookObj->$hook($this, func_get_arg(1), func_get_arg(2));
							break;
						case 2:
							$result = $hookObj->$hook($this, func_get_arg(1));
							break;
						case 1:
						default:
							$result = $hookObj->$hook($this);
					}
					if($result === false) {
						return $result;
					}
					if($result !== null) {
						$r = $result;
					}
				}
			}
			return $r;
		}
		return null;
	} /* }}} */

	/**
	 * Check if a hook is registered
	 *
	 * @param $hook string name of hook
	 * @return mixed false if one of the hooks fails,
	 *               true if all hooks succedded,
	 *               null if no hook was called
	 */
	function hasHook($hook) { /* {{{ */
		$tmp = explode('_', get_class($this));
		if(isset($GLOBALS['LetoDMS_HOOKS']['controller'][lcfirst($tmp[2])])) {
			foreach($GLOBALS['LetoDMS_HOOKS']['controller'][lcfirst($tmp[2])] as $hookObj) {
				if (method_exists($hookObj, $hook)) {
					return true;
				}
			}
		}
		return false;
	} /* }}} */

}
