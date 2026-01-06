<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) The facileManager Team                                    |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | facileManager: Easy System Administration                               |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/                                           |
 +-------------------------------------------------------------------------+
 | Processes module admin tools                                            |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

if (!isset($fm_module_tools)) {
	$class = '\\facileManager\\shared\\Tools';
	if (file_exists(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/Tools.php')) {
		$class = '\\facileManager\\' . $_SESSION['module'] . '\\Tools';
	}
	$fm_module_tools = new $class();
	unset($class);
}

$response = null;
if (is_array($_POST) && count($_POST) && currentUserCan('run_tools')) {
	if (isset($_POST['task']) && !empty($_POST['task'])) {
		switch($_POST['task']) {
			case 'connect-test':
				$response = buildPopup('header', _('Connectivity Test Results'));
				$response .= $fm_module_tools->connectTests();
				break;
		}
	}
} else {
	echo buildPopup('header', _('Error'));
	printf("<p>%s</p>\n", _('You are not authorized to run these tools.'));
}
