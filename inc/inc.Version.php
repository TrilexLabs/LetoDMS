<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005 Markus Westphal
//    Copyright (C) 2007-2008 Malcolm Cowe
//    Copyright (C) 2010-2013 Uwe Steinmann
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

class LetoDMS_Version {

	public $_number = "5.1.9";
	private $_string = "LetoDMS";

	function __construct() {
	}

	function version() {
		return $this->_number;
	}

	function majorVersion() {
		$tmp = explode('.', $this->_number, 3);
		return (int) $tmp[0];
	}

	function minorVersion() {
		$tmp = explode('.', $this->_number, 3);
		return (int) $tmp[1];
	}

	function subminorVersion() {
		$tmp = explode('.', $this->_number, 3);
		return (int) $tmp[2];
	}
	function banner() {
		return $this->_string .", ". $this->_number;
	}
}
?>
