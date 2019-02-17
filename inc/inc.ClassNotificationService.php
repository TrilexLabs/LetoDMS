<?php
/**
 * Implementation of notification service
 *
 * @category   DMS
 * @package    LetoDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2016 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Implementation of notification service
 *
 * @category   DMS
 * @package    LetoDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2016 Uwe Steinmann
 * @version    Release: @package_version@
 */
class LetoDMS_NotificationService {
	/**
	 * List of services for sending notification
	 */
	protected $services;

	public function __construct() {
		$this->services = array();
	}

	public function addService($service) {
		$this->services[] = $service;
	}

	public function toIndividual($sender, $recipient, $subject, $message, $params=array()) {
		foreach($this->services as $service) {
			return $service->toIndividual($sender, $recipient, $subject, $message, $params);
		}
	}

	public function toGroup($sender, $groupRecipient, $subject, $message, $params=array()) {
		foreach($this->services as $service) {
			return $service->toGroup($sender, $groupRecipient, $subject, $message, $params);
		}
	}

	public function toList($sender, $recipients, $subject, $message, $params=array()) {
		foreach($this->services as $service) {
			return $service->toList($sender, $recipients, $subject, $message, $params);
		}
	}

}

