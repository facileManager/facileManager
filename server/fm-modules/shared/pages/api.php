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
 | Processes API requests                                                  |
 +-------------------------------------------------------------------------+
*/

header("Content-Type: application/json");

/** Handle client interactions */
if (!defined('CLIENT')) define('CLIENT', true);

$message = '';

/** Get the request method */
$method = $_SERVER['REQUEST_METHOD'];
$valid_api_request_methods = ['GET', 'POST', 'PATCH', 'DELETE'];
if (!in_array($method, $valid_api_request_methods)) {
	returnAPIStatus(405);
}

if (is_array($_REQUEST)) {
	extract(cleanAndTrimInputs($_REQUEST));
}
if (is_array($api_input)) {
	extract($api_input, EXTR_OVERWRITE);
	if (isset($api)) {
		$api = cleanAndTrimInputs($api);
	}
}

if (!isset($_SERVER['HTTP_X_API_KEY']) || !isset($_SERVER['HTTP_X_API_SECRET']) || !isset($_SERVER['HTTP_AUTHKEY'])) {
	returnAPIStatus(401);
}

$auth['AUTHKEY'] = $_SERVER['HTTP_AUTHKEY'];
$auth['API_KEY'] = cleanAndTrimInputs($_SERVER['HTTP_X_API_KEY']);
$auth['API_SECRET'] = cleanAndTrimInputs($_SERVER['HTTP_X_API_SECRET']);

// /** Ensure we have data to process */
// if (!isset($_POST) || !count($_POST)) {
// 	exit;
// }

require_once('fm-init.php');
include(ABSPATH . 'fm-modules/facileManager/classes/class_accounts.php');

/** Ensure we have a valid account */
$account_verify = $fm_accounts->verify($auth);
if ($account_verify != 'Success') {
	returnAPIStatus(401, $account_verify);
}

/** Authenticate key */
require_once(ABSPATH . 'fm-modules/facileManager/classes/class_logins.php');
$logged_in = @$fm_login->doAPIAuth($auth['API_KEY'], $auth['API_SECRET'], $auth['AUTHKEY']);

if (!$logged_in) {
	returnAPIStatus(401);
}

if (isset($apitest)) {
	returnAPIStatus(200, _('API functionality tests were successful.'));
}

if (!isset($module_name)) {
	returnAPIStatus(400);
}

/** Parse REST API URI */
$uri_path_dir = (substr($GLOBALS['path_parts']['path'], -1) == '/') ? $GLOBALS['path_parts']['path'] : $GLOBALS['path_parts']['dirname'];
$uri_path_dir = strtolower($uri_path_dir);
$_path_parts = array_map('strtolower', explode('/', $uri_path_dir));
$lower_module_name = strtolower($module_name);
$api_root_key = array_search($lower_module_name, $_path_parts);

if (strpos($uri_path_dir . '/', "/api/$lower_module_name/") === false) {
	returnAPIStatus(400);
}

/** Include actions from module */
$module_file = ABSPATH . 'fm-modules' . DIRECTORY_SEPARATOR . $module_name . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'api.inc.php';
if (file_exists($module_file)) {
	include($module_file);
}

/** Output $data */
if (!empty($data)) {
	returnAPIStatus(200, $data);
}

exit;
