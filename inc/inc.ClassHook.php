<?php
/**
 * Implementation of hook response class
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2017 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Parent class for all hook response classes
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2017 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_Hook_Response {
	protected $data;

	protected $error;

	public function __construct($error = false, $data = null) {
		$this->data = $data;
		$this->error = $error;
	}

	public function setData($data) {
		$this->data = $data;
	}

	public function getData() {
		return $this->data;
	}
}

