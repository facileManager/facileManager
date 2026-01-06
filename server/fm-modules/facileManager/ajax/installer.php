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
 | Handles installer                                                       |
 +-------------------------------------------------------------------------+
*/

if (isset($_POST) && count($_POST)) {
	/** Set installation variable */
	define('INSTALL', true);
	$GLOBALS['RELPATH'] = rtrim(dirname($_SERVER['PHP_SELF'], 4), '/') . '/';
	
	if (!defined('AJAX')) {
		define('AJAX', true);
	}

	if (!defined('ABSPATH')) {
		/** Define ABSPATH as this files directory */
		define('ABSPATH', dirname(__DIR__, 3) . '/');
	}

	extract($_POST);
	// require_once('../../../fm-init.php');
	require_once(ABSPATH . 'fm-includes/functions.php');
	require_once(ABSPATH . 'fm-modules/facileManager/install.php');

	switch ($task) {
		case 'install_config_test':
			processSetup();
			break;
		case 'install_create_account':
			if (file_exists(ABSPATH . 'config.inc.php') && file_get_contents(ABSPATH . 'config.inc.php')) {
				include(ABSPATH . 'config.inc.php');
				$fmdb = new facileManager\Fmdb($__FM_CONFIG['db']['user'], $__FM_CONFIG['db']['pass'], $__FM_CONFIG['db']['name'], $__FM_CONFIG['db']['host'], 'connect only');
				
				/** Make sure the super-admin account doesn't already exist */
				if (!checkAccountCreation($__FM_CONFIG['db']['name'])) {
					processAccountSetup($__FM_CONFIG['db']['name']);
				}
			}
			break;
	}
	
}
