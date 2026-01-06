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
*/

/**
 * facileManager Installer
 *
 * @package facileManager
 * @subpackage Administration
 *
 */

include_once(__DIR__ . '/fm-includes/functions.php');
setConstant('ABSPATH', __DIR__ . '/');

/** Set installation variable */
setConstant('INSTALL', true);
$GLOBALS['RELPATH'] = rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/';

/** Check if authenticated */
$fm_login = new facileManager\Login();

if ($fm_login->isLoggedIn() || (isset($_SESSION) && array_key_exists('user', $_SESSION))) {
	$fm_login->logout();
	header('Location: ' . $GLOBALS['RELPATH']);
	exit;
}

/** Ensure we meet the requirements */
require_once(ABSPATH . 'fm-modules/facileManager/functions.php');
require_once(ABSPATH . 'fm-includes/version.php');
if ($app_compat = checkAppVersions(false)) {
	bailOut($app_compat);
}

require_once(ABSPATH . 'fm-modules/facileManager/install.php');

$step = (isset($_GET['step']) && $_GET['step'] <= 3 && $_GET['step'] >= 0) ? $_GET['step'] : 0;

$branding_logo = $GLOBALS['RELPATH'] . 'fm-modules/' . $fm_name . '/images/fm.png';

switch ($step) {
	case 0:
	case 1:
		if ((!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) || 
				(@include(ABSPATH . 'config.inc.php') && !@is_array($__FM_CONFIG['db']))) {
			printHeader(_('Installation'), 'login');
			echo displaySetup();
		} else {
			header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php?step=2');
			exit;
		}
		break;
	case 2:
		if (!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) {
			header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
			exit;
		}
		require_once(ABSPATH . 'fm-modules/facileManager/install.php');
		
		@include(ABSPATH . 'config.inc.php');
		$fmdb = new facileManager\Fmdb($__FM_CONFIG['db']['user'], $__FM_CONFIG['db']['pass'], $__FM_CONFIG['db']['name'], $__FM_CONFIG['db']['host'], 'connect only');
		
		$mysql_server_version = ($fmdb->use_mysqli) ? $fmdb->dbh->server_info : mysql_get_server_info();
		if (version_compare($mysql_server_version, $required_mysql_version, '<')) {
			bailOut(sprintf('<p style="text-align: center;">' . _('Your MySQL server (%1$s) is running MySQL version %2$s but %3$s %4$s requires at least %5$s.') . '</p>', $__FM_CONFIG['db']['host'], $mysql_server_version, $fm_name, $fm_version, $required_mysql_version));
			break;
		}
		
		printHeader(_('Installation'), 'login');

		/** Check if already installed */
		if (isset($__FM_CONFIG['db']['name'])) {
			$query = "SELECT option_id FROM `{$__FM_CONFIG['db']['name']}`.`fm_options` WHERE `option_name`='fm_db_version'";
			$fmdb->query($query);
		} else {
			header('Location: ' . $GLOBALS['RELPATH']);
			exit;
		}
		
		if ($fmdb->num_rows) {
			/** Check if the default admin account exists */
			if (!checkAccountCreation($__FM_CONFIG['db']['name'])) {
				header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php?step=3');
				exit;
			} else {
				header('Location: ' . $GLOBALS['RELPATH']);
				exit;
			}
		} else {
			fmInstall($__FM_CONFIG['db']['name']);
		}
		break;
	case 3:
		if (!file_exists(ABSPATH . 'config.inc.php') || !file_get_contents(ABSPATH . 'config.inc.php')) {
			header('Location: ' . $GLOBALS['RELPATH'] . 'fm-install.php');
			exit;
		}
		
		include(ABSPATH . 'config.inc.php');
		$fmdb = new facileManager\Fmdb($__FM_CONFIG['db']['user'], $__FM_CONFIG['db']['pass'], $__FM_CONFIG['db']['name'], $__FM_CONFIG['db']['host'], 'connect only');
		
		/** Make sure the super-admin account doesn't already exist */
		if (!checkAccountCreation($__FM_CONFIG['db']['name'])) {
			printHeader(_('Installation'), 'login');
			displayAccountSetup();
		} else {
			header('Location: ' . $GLOBALS['RELPATH']);
			exit;
		}
		
		break;
}

printFooter();


/**
 * Display install body.
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function displaySetup($error = null) {
	global $fm_name, $step;
	
	if ($error) {
		$error = sprintf('<strong>' . _('ERROR: %s') . "</strong>\n", $error);
	}
	
	$dbhost = (isset($_POST['dbhost'])) ? $_POST['dbhost'] : 'localhost';
	$dbname = (isset($_POST['dbname'])) ? $_POST['dbname'] : $fm_name;
	$dbuser = (isset($_POST['dbuser'])) ? $_POST['dbuser'] : null;
	$dbpass = (isset($_POST['dbpass'])) ? $_POST['dbpass'] : null;
	$key = (isset($_POST['ssl']['key'])) ? $_POST['ssl']['key'] : null;
	$cert = (isset($_POST['ssl']['cert'])) ? $_POST['ssl']['cert'] : null;
	$ca = (isset($_POST['ssl']['ca'])) ? $_POST['ssl']['ca'] : null;
	$capath = (isset($_POST['ssl']['capath'])) ? $_POST['ssl']['capath'] : null;
	$cipher = (isset($_POST['ssl']['cipher'])) ? $_POST['ssl']['cipher'] : null;
	if (isset($_POST['install_enable_ssl'])) {
		$ssl_checked = 'checked';
		$ssl_show_hide = 'table-row-group';
	} else {
		$ssl_checked = null;
		$ssl_show_hide = 'none';
	}

	$left_content = sprintf('
			<p>%s<br /><br />%s</p>
			<table>
				<tbody>
				<tr>
					<th><label for="dbhost">%s</label></th>
					<td>
						<div class="input-wrapper">
							<i class="fa fa-server" aria-hidden="true"></i>
							<input type="text" size="25" name="dbhost" id="dbhost" value="%s" placeholder="localhost" />
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="dbname">%s</label></th>
					<td>
						<div class="input-wrapper">
							<i class="fa fa-database" aria-hidden="true"></i>
							<input type="text" size="25" name="dbname" id="dbname" value="%s" placeholder="%s" />
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="dbuser">%s</label></th>
					<td>
						<div class="input-wrapper">
							<i class="fa fa-user" aria-hidden="true"></i>
							<input type="text" size="25" name="dbuser" id="dbuser" value="%s" placeholder="%s" />
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="dbpass">%s</label></th>
					<td>
						<div class="input-wrapper">
							<i class="fa fa-key" aria-hidden="true"></i>
							<i id="show_password" class="fa fa-eye eye-attention" title="%s" aria-hidden="true"></i>
							<input type="password" size="25" name="dbpass" id="dbpass" value="%s" placeholder="%s" />
						</div>
					</td>
				</tr>
				<tr>
					<th></th>
					<td><input type="checkbox" name="install_enable_ssl" id="install_enable_ssl" %s /> <label for="install_enable_ssl">%s</label></td>
				</tr>
				</tbody>
				<tbody id="install_ssl_options" style="display: %s">
				<tr>
					<th><label for="key">%s</label></th>
					<td>
						<div class="input-wrapper">
							<i class="fa fa-key" aria-hidden="true"></i>
							<input type="text" size="25" name="ssl[key]" id="key" value="%s" placeholder="/path/to/ssl.key" />
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="cert">%s</label></th>
					<td>
						<div class="input-wrapper">
							<i class="fa fa-certificate" aria-hidden="true"></i>
							<input type="text" size="25" name="ssl[cert]" id="cert" value="%s" placeholder="/path/to/ssl.crt" />
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="ca">%s</label></th>
					<td>
						<div class="input-wrapper">
							<i class="fa fa-certificate" aria-hidden="true"></i>
							<input type="text" size="25" name="ssl[ca]" id="ca" value="%s" placeholder="/path/to/ca.pem" />
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="capath">%s</label></th>
					<td>
						<div class="input-wrapper">
							<i class="fa fa-certificate" aria-hidden="true"></i>
							<input type="text" size="25" name="ssl[capath]" id="capath" value="%s" placeholder="/path/to/trusted/cas" />
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="cipher">%s</label></th>
					<td>
						<div class="input-wrapper">
							<i class="fa fa-key" aria-hidden="true"></i>
							<input type="text" size="25" name="ssl[cipher]" id="cipher" value="%s" />
						</div>
					</td>
				</tr>
				</tbody>
			</table>
			<div id="message" class="failed"></div>
			<div class="button-wrapper"><a name="submit" id="btn_install_config_submit" class="button"><i class="fa fa-sign-in" aria-hidden="true"></i> %s</a></div>
', _('Before the backend database can be installed, your database credentials are needed to generate the <code>config.inc.php</code> file.'), _('Enter the details below or copy <code>config.sample.inc.php</code> to <code>config.inc.php</code>, modify as necessary, and reload this page.'),
	_('Database Host'),
	$dbhost,
	_('Database Name'),
	$dbname,
	$fm_name,
	_('Username'),
	$dbuser,
	_('username'),
	_('Password'),
	$dbpass,
	_('password'),
	_('Show'),
	$ssl_checked,
	_('Enable SSL'), $ssl_show_hide,
	_('SSL Key Path'), $key,
	_('SSL Certificate Path'), $cert,
	_('SSL Certificate CA Path'), $ca,
	_('SSL Trusted CA Path (optional)'), $capath,
	_('SSL Ciphers (optional)'), $cipher,
	_('Submit'));

	return displayPreAppForm(_('Installation'), 'window', $left_content, displayProgressBar($step), 'flex', null, '?step=2');
}

/**
 * Display account setup.
 *
 * @since 1.0
 * @package facileManager
 * @subpackage Installer
 */
function displayAccountSetup($error = null) {
	global $__FM_CONFIG, $step;

	if ($error) {
		$error = sprintf('<strong>' . _('ERROR: %s') . "</strong>\n", $error);
	}
	
	$left_content = sprintf('
	<p>' . _('Create your super-admin account') . '</p>
	<table class="form-table">
		<tr>
			<th><label for="user_login">' . _('Username') . '</label></th>
			<td>
				<div class="input-wrapper">
					<i class="fa fa-user" aria-hidden="true"></i>
					<input type="text" size="25" name="user_login" id="user_login" placeholder="username" onkeyup="javascript:checkPasswd(\'user_password\', \'createaccount\', \'%1$s\');" />
				</div>
			</td>
		</tr>
		<tr>
			<th><label for="user_email">' . _('E-mail') . '</label></th>
			<td>
				<div class="input-wrapper">
					<i class="fa fa-envelope" aria-hidden="true"></i>
					<input type="email" size="25" name="user_email" id="user_email" placeholder="e-mail address" onkeyup="javascript:checkPasswd(\'user_password\', \'createaccount\', \'%1$s\');" />
				</div>
			</td>
		</tr>
		<tr>
			<th><label for="user_password">' . _('Password') . '</label></th>
			<td>
				<div class="input-wrapper">
					<i class="fa fa-key" aria-hidden="true"></i>
					<i id="show_password" class="fa fa-eye eye-attention" title="%s" aria-hidden="true"></i>
					<input type="password" size="25" name="user_password" id="user_password" placeholder="password" onkeyup="javascript:checkPasswd(\'user_password\', \'createaccount\', \'%1$s\');" autocomplete="off" />
				</div>
			</td>
		</tr>
		<tr>
			<th><label for="cpassword">' . _('Confirm Password') . '</label></th>
			<td>
				<div class="input-wrapper">
					<i class="fa fa-key" aria-hidden="true"></i>
					<input type="password" size="25" name="cpassword" id="cpassword" placeholder="password again" onkeyup="javascript:checkPasswd(\'cpassword\', \'createaccount\', \'%1$s\');" />
				</div>
			</td>
		</tr>
		<tr>
			<th>' . _('Password Validity') . '</th>
			<td><div id="passwd_check">' . _('No Password') . '</div></td>
		</tr>
		<tr class="pwdhint">
			<th width="33&#37;" scope="row">' . _('Hint') . '</th>
			<td width="67&#37;">%2$s</td>
		</tr>
	</table>
	<div id="message" class="failed"></div>
	<div class="button-wrapper"><a name="submit" id="btn_install_create_account" class="button"><i class="fa fa-user" aria-hidden="true"></i> %3$s</a></div>
', $GLOBALS['PWD_STRENGTH'], $__FM_CONFIG['password_hint'][$GLOBALS['PWD_STRENGTH']][1], _('Create Account'));

	echo displayPreAppForm(_('Installation'), 'window', $left_content, displayProgressBar($step), 'flex', null, '?step=3');
}
