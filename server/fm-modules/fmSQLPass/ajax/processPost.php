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
 | fmSQLPass: Change database user passwords across multiple servers.      |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmsqlpass/                         |
 +-------------------------------------------------------------------------+
 | Processes form posts                                                    |
 +-------------------------------------------------------------------------+
*/

if (!defined('AJAX')) define('AJAX', true);
require_once('../../../fm-init.php');

$fm_sqlpass_groups = new \facileManager\fmSQLPass\Groups();
$fm_module_servers = new \facileManager\fmSQLPass\Servers();

if (!function_exists('returnUnAuth')) {
	include(ABSPATH . 'fm-modules/' . $fm_name . '/ajax/functions.php');
}

$unpriv_message = _('You do not have sufficient privileges.');
if (isset($_POST['page']) && !isset($_POST['item_type'])) {
	$_POST['item_type'] = $_POST['page'];
}

/** Handle password changes */
if (is_array($_POST) && array_key_exists('item_type', $_POST) && $_POST['item_type'] == 'set_mysql_password') {
	if (!currentUserCan('manage_passwords', $_SESSION['module'])) returnUnAuth(true);

	$fm_sqlpass_passwords = new \facileManager\fmSQLPass\Passwords();
	if ($_POST['verbose']) echo buildPopup('header', _('Password Change Results')) . '<pre>';
	echo $fm_sqlpass_passwords->setPassword();
	if ($_POST['verbose']) echo '</pre>' . buildPopup('footer', _('OK'), array('cancel_button' => 'cancel'));

	exit;
/** Handle everything else */
} elseif (is_array($_POST) && count($_POST) && currentUserCan('manage_servers', $_SESSION['module'])) {
	$table = 'sqlpass_' . $_POST['item_type'];
	$item_type = $_POST['item_type'];
	$prefix = substr($item_type, 0, -1) . '_';

	$field = $prefix . 'id';
	$type_map = null;
	$id = isset($_POST['item_id']) ? $_POST['item_id'] : $_POST[$prefix . 'id'];
	$type = isset($_POST['item_sub_type']) ? $_POST['item_sub_type'] : null;

	/* Determine which class we need to deal with */
	switch($_POST['item_type']) {
		case 'groups':
			$post_class = $fm_sqlpass_groups;
			break;
		case 'servers':
			$post_class = $fm_module_servers;
			break;
	}
	var_dump($post_class);

	switch ($_POST['action']) {
		case 'add':
		case 'create':
			$response = $post_class->add($_POST);
			echo ($response !== true) ? $response : 'Success';
			break;
		case 'delete':
			if (isset($id)) {
				exit(parseAjaxOutput($post_class->delete($id, $type)));
			}
			break;
		case 'edit':
		case 'update':
			if (isset($_POST['item_status'])) {
				if (!updateStatus('fm_' . $table, $id, $prefix, $_POST['item_status'], $field)) {
					exit(sprintf(_('This item could not be set to %s.') . "\n", $_POST['item_status']));
				} else {
					$tmp_name = getNameFromID($id, 'fm_' . $table, $prefix, $field, $prefix . 'name');
					addLogEntry(sprintf(_('Set %s (%s) status to %s.'), substr($item_type, 0, -1), $tmp_name, $_POST['item_status']));
					exit('Success');
				}
			} else {
				$response = $post_class->update($_POST);
				echo ($response !== true) ? $response : 'Success';
			}
			break;
	}

	exit;
}

echo $unpriv_message;
