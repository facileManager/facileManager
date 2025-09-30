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
 * These functions are needed to load facileManager
 *
 * @internal This file must be parsable by PHP4.
 *
 * @package facileManager
 */

require_once(ABSPATH . 'fm-modules/facileManager/functions.php');

/**
 * Check for the required PHP version, and the MySQL extension or a database drop-in.
 *
 * Dies if requirements are not met.
 *
 * @access private
 * @since 1.0
 */
function checkAppVersions($single_check = true) {
	global $fm_name;
	
	require(ABSPATH . 'fm-includes/version.php');

	$requirement_check = '';
	$error = false;
	
	/** PHP Version */
	if (version_compare(PHP_VERSION, $required_php_version, '<')) {
		$message = sprintf(_('Your server is running PHP version %1$s but %2$s %3$s requires at least %4$s.'), PHP_VERSION, $fm_name, $fm_version, $required_php_version);
		if ($single_check) {
			bailOut($message);
		} else {
			list($retval, $tmp_content) = displayProgress("PHP >= $required_php_version", false, 'display', $message);
			$requirement_check .= $tmp_content;
			$error = true;
		}
	} else {
		if (!$single_check) {
			list($retval, $tmp_content) = displayProgress("PHP >= $required_php_version", true, 'display');
			$requirement_check .= $tmp_content;
		}
	}

	/** PHP Extensions */
	$required_php_extensions = array('curl', 'posix', 'filter', 'json', 'mysqli');
	if (!useMySQLi()) {
		$required_php_extensions[] = 'mysql';
	}
	foreach ($required_php_extensions as $extension) {
		if (!extension_loaded($extension)) {
			$message = sprintf(_('Your PHP installation appears to be missing the %1s extension which is required by %2s.'), $extension, $fm_name);
			if ($single_check) {
				bailOut($message);
			} else {
				list($retval, $tmp_content) = displayProgress(_(sprintf('PHP %1s Extension', $extension)), false, 'display', $message);
				$requirement_check .= $tmp_content;
				$error = true;
			}
		} else {
			if (!$single_check) {
				list($retval, $tmp_content) = displayProgress(_(sprintf('PHP %1s Extension', $extension)), true, 'display');
				$requirement_check .= $tmp_content;
			}
		}
	}
	
	/** Apache mod_rewrite module */
	if (function_exists('apache_get_modules')) {
		if (!in_array('mod_rewrite', apache_get_modules())) {
			$message = sprintf(_('Your Apache installation appears to be missing the mod_rewrite module which is required by %1s.'), $fm_name);
			if ($single_check) {
				bailOut($message);
			} else {
				list($retval, $tmp_content) = displayProgress(_('Apache mod_rewrite Loaded'), false, 'display', $message);
				$requirement_check .= $tmp_content;
				$error = true;
			}
		} else {
			if (!$single_check) {
				list($retval, $tmp_content) = displayProgress(_('Apache mod_rewrite Loaded'), true, 'display');
				$requirement_check .= $tmp_content;
			}
		}
	}
	
	/** .htaccess file */
	if (!defined('FM_NO_HTACCESS')) {
		if (!file_exists(ABSPATH . '.htaccess')) {
			if (is_writeable(ABSPATH)) {
				file_put_contents(ABSPATH . '.htaccess', '<IfModule mod_headers.c>
	<FilesMatch "\.(js|css|txt)$">
		Header set Cache-Control "max-age=7200"
	</FilesMatch>
	<FilesMatch "\.(jpe?g|png|gif|ico)$">
		Header set Cache-Control "max-age=2592000"
	</FilesMatch>
</IfModule>

<IfModule mod_rewrite.c>
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.php [L]
</IfModule>

');
			} else {
				if ($single_check) {
					bailOut(sprintf(_('The missing %1s.htaccess which is required by %2s could not be created. Please create it with the following contents:'), ABSPATH, $fm_name) . 
				'<textarea rows="8">&lt;IfModule mod_headers.c&gt;
	&lt;FilesMatch "\.(js|css|txt)$"&gt;
		Header set Cache-Control "max-age=7200"
	&lt;/FilesMatch&gt;
	&lt;FilesMatch "\.(jpe?g|png|gif|ico)$"&gt;
		Header set Cache-Control "max-age=2592000"
	&lt;/FilesMatch&gt;
&lt;/IfModule&gt;

&lt;IfModule mod_rewrite.c&gt;
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.php [L]
&lt;/IfModule&gt;
</textarea>');
				} else {
					list($retval, $tmp_content) = displayProgress(_('.htaccess File Present'), false, 'display', sprintf(_('The %1s.htaccess file from the tar file is missing and is required by %2s.'), ABSPATH, $fm_name));
					$requirement_check .= $tmp_content;
					$error = true;
				}
			}
		} else {
			if (!$single_check) {
				list($retval, $tmp_content) = displayProgress(_('.htaccess File Present'), true, 'display');
				$requirement_check .= $tmp_content;
			}
		}
	}
	
	/** Test rewrites */
	if (!defined('INSTALL') && !defined('FM_NO_REWRITE_TEST')) {
		if (@dns_get_record($_SERVER['SERVER_NAME'], DNS_A + DNS_AAAA)) {
			$test_output = getPostData($GLOBALS['FM_URL'] . 'admin-accounts.php?verify', array('module_type' => 'CLIENT'));
			$test_output = isSerialized($test_output) ? unserialize($test_output) : $test_output;
			if (strpos($test_output, 'Account is not found.') === false) {
				$message = sprintf(_('The required logic found in the .htaccess file appears to not work with your web server configuration which is required by %1s. '
						. 'AllowOverride None in your configuration may be blocking the use of .htaccess or %s is not resolvable.'),
						$fm_name, $_SERVER['SERVER_NAME']);
				if ($single_check) {
					bailOut($message);
				} else {
					list($retval, $tmp_content) = displayProgress(_('Test Rewrites'), false, 'display', $message);
					$requirement_check .= $tmp_content;
					$error = true;
				}
			} else {
				if (!$single_check) {
					list($retval, $tmp_content) = displayProgress(_('Test Rewrites'), true, 'display');
					$requirement_check .= $tmp_content;
				}
			}
		}
	}
	
	if ($error) {
		$requirement_check = sprintf('<div class="flex flex-column"><table class="form-table">%s</table></div>', $requirement_check);
	} else $requirement_check = null;
	
	return $requirement_check;
}
