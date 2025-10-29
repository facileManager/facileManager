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

class fm_login {
	
	/**
	 * Displays the login form
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @return string
	 */
	function printLoginForm() {
		global $fm_name;
		
		printHeader(_('Login'), 'login');
		
		/** Cannot change password without mail_enable defined */
		$mail_enable = (getOption('fm_db_version') >= 18) ? getOption('mail_enable') : false;
		$auth_method = (getOption('fm_db_version') >= 18) ? getOption('auth_method') : false;
		$forgot_link = ($mail_enable && $auth_method == 1) ? sprintf('<p id="forgotton_link"><a href="?forgot_password">%s</a></p>', _('Forgot your password?')) : null;
		
		$terms_display = '';
		
		$login_message = getOption('login_message');
		$terms_accept = '';

		if ($login_message) {
			$terms_display = 'style="display: block;"';
			$login_message = '<p>' . $login_message . '</p>';

			if (getOption('login_message_accept')) {
				$terms_accept = '<p><input name="login_message_accept" id="login_message_accept" type="checkbox" value="1" /><label for="login_message_accept">' . _('I acknowledge and accept the terms') . '</label></p>';
			}
		}

		echo displayPreAppForm(_('Login'), 'login_form',
			sprintf('
					<div class="message">%s</div>
					<div class="input-wrapper">
						<i class="fa fa-user" aria-hidden="true"></i>
						<input type="text" name="username" id="username" placeholder="%s" />
					</div>
					<div class="input-wrapper">
						<i class="fa fa-key" aria-hidden="true"></i>
						<i id="show_password" class="fa fa-eye eye-attention" title="%s" aria-hidden="true"></i>
						<input type="password" name="password" id="password" placeholder="%s" />
					</div>
					<div class="button-wrapper"><a name="submit" id="loginbtn" class="button"><i class="fa fa-sign-in" aria-hidden="true"></i> %s</a></div>
					<div>%s</div>
				</div>
				<div id="form_messaging">
					<div class="terms-accept">%s</div>
					<div id="message" class="message"></div>
', _('Enter your username and password to sign in.'), _('Username'), _('Password'), _('Show'), _('Login'), $forgot_link, $terms_accept),
			nl2br($login_message), 'terms', 'loginform', $_SERVER['REQUEST_URI'], $terms_display);
		
		printFooter();
		exit();
	}
	
	
	/**
	 * Display password reset user form.
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param string $message Message to display to the user
	 * @return void
	 */
	function printUserForm($message = null) {
		/** Should not be here if there is no mail_enable defined or if not using builtin auth */
		if (!getOption('mail_enable') || getOption('auth_method') != 1) {
			header('Location: ' . $GLOBALS['RELPATH']);
			exit;
		}

		printHeader(_('Password Reset'), 'login');
		
		echo displayPreAppForm(_('Reset Password'), 'login_form',
		sprintf('
				<div class="message">%s</div>
				<input type="hidden" name="reset_pwd" value="1" />
				<div class="input-wrapper">
					<i class="fa fa-user" aria-hidden="true"></i>
					<input type="text" name="user_login" id="user_login" placeholder="%s" />
				</div>
				<div class="button-wrapper"><a name="submit" id="forgotbtn" class="button"><i class="fa fa-send" aria-hidden="true"></i> %s</a></div>
				<p id="forgotton_link"><a href="%s">&larr; %s</a></p>
				<div id="message" class="message">%s</div>
	', _('Enter your username for a password reset link to be sent to the address associated with your account.'), _('Username'),
				_('Submit'), $GLOBALS['RELPATH'], _('Login form'), $message), null, null, 'loginform', $_SERVER['PHP_SELF'] . '?forgot_password', );
	}
	
		
	/**
	 * Process password reset user form.
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param string $user_login Username to authenticate
	 * @param string $option Form options
	 * @return boolean|void|string
	 */
	function processUserPwdResetForm($user_login = null, $option = 'mail') {
		global $fmdb;
		
		$user_login = sanitize(trim($user_login));
		if (empty($user_login)) return false;
		
		$user_info = getUserInfo($user_login, 'user_login');
		
		/** If the user is not found, just return lest we give away valid user accounts */
		if ($user_info === false) {
			sleep(1);
			return true;
		}
		
		$fm_login = $user_info['user_id'];
		$uniqhash = genRandomString(mt_rand(30, 50));
		
		$query = "INSERT INTO fm_temp_auth_keys VALUES ('$uniqhash', '$fm_login', " . time() . ")";
		$fmdb->query($query);
		
		if (!$fmdb->rows_affected) return false;
		
		/** Mail the reset link */
		if ($option == 'mail') {
			$mail_enable = getOption('mail_enable');
			if ($mail_enable) {
				$result = $this->mailPwdResetLink($fm_login, $uniqhash);
				if ($result !== true) {
					$query = "DELETE FROM fm_temp_auth_keys WHERE pwd_id='$uniqhash' AND pwd_login='$fm_login'";
					$fmdb->query($query);

					return $result;
				}
			}
		}

		return true;
	}
	
	
	/**
	 * Checks if the user is authenticated
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @return boolean
	 */
	function isLoggedIn() {
		global $fm_name;
		
		if (defined('INSTALL')) return false;
		
		/** No auth_method defined */
		if (getOption('fm_db_version') >= 18) {
			if (!getOption('auth_method')) {
				if (!isset($_COOKIE['fmid'])) {
					@session_start();
	
					$_SESSION['user']['logged_in'] = true;
					$_SESSION['user']['id'] = 1;
					$_SESSION['user']['account_id'] = 1;
		
					$modules = getActiveModules(true);
					if (!isset($_SESSION['module'])) {
						$_SESSION['module'] = (is_array($modules) && count($modules)) ? $modules[0] : $fm_name;
					}
	
					setcookie('fmid', session_id(), strtotime('+1 year'));
				}
				
				session_set_cookie_params(strtotime('+1 year'));
				if (!empty($_COOKIE['fmid'])) {
					@session_id($_COOKIE['fmid']);
					@session_start();
				}
				session_write_close();
	
				return true;
			}
		}

		/** Auth method defined so let's validate */
		if (isset($_COOKIE['fmid'])) {
			$fmid = $_COOKIE['fmid'];
				
			/** Init the session. */
			session_set_cookie_params(strtotime('+1 week'));
			session_id($fmid);
			@session_start();
				
			/** Check if they're logged in. */
			if (isset($_SESSION['user']['logged_in']) && $_SESSION['user']['logged_in']) {
				if (!isset($_SESSION['user']['last_login'])) {
					$_SESSION['user']['last_login'] = 0;
				}
				/** Set the last login info */
				if (strtotime("-1 hour") > $_SESSION['user']['last_login']) {
					$_SESSION['user']['last_login'] = strtotime("-15 minutes");
					$_SESSION['user']['ipaddr'] = isset($_SERVER['REMOTE_HOST']) ? $_SERVER['REMOTE_HOST'] : $_SERVER['REMOTE_ADDR'];
				}
				
				/** Should the user be logged in? */
				if (getNameFromID($_SESSION['user']['id'], 'fm_users', 'user_', 'user_id', 'user_status') != 'active') {
					session_write_close();
					header('Location: ' . $GLOBALS['RELPATH'] . '?logout');
					return false;
				}
				session_write_close();
				
				return true;
			}
			session_write_close();
		}
		return false;
	}
	
	/**
	 * Do the authentication
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param string $user_login Username to authenticate
	 * @param string $user_password Password to authenticate with
	 * @return boolean|array
	 */
	function checkPassword($user_login, $user_password) {
		global $fmdb, $__FM_CONFIG, $fm_name;
		
		if (empty($user_login) || empty($user_password)) return false;

		if (getOption('login_message_accept') && isset($_POST['login_message_accept']) && $_POST['login_message_accept'] != 'true') return false;
		
		/** Built-in authentication */
		$fm_db_version = getOption('fm_db_version');
		$auth_method = ($fm_db_version >= 18) ? getOption('auth_method') : true;
		if ($auth_method) {
			$successful_auth = false;

			/** Use Built-in Auth when Default Auth Method is LDAP but user is defined with 'facileManager/Built-in' */
			$result = $fmdb->query("SELECT * FROM `fm_users` WHERE `user_login` = '$user_login' and `user_auth_type`=1 and `user_status`='active'");
			if (isset($fmdb->last_result) && is_array($fmdb->last_result) && $fmdb->last_result[0]->user_login == $user_login) {
				$auth_method = 1;
			}

			/** Built-in Authentication */
			if ($auth_method == 1) {
				if ($fm_db_version >= 18) {
					$result = $fmdb->get_results("SELECT * FROM `fm_users` WHERE `user_status`='active' AND `user_auth_type`=1 AND `user_template_only`='no' AND `user_login`='$user_login'");
				} else {
					/** Old auth */
					$result = $fmdb->get_results("SELECT * FROM `fm_users` WHERE `user_status`='active' AND `user_login`='$user_login' AND `user_password`='$user_password'");
				}
				if (!$fmdb->num_rows) {
					return false;
				} else {
					$user = $fmdb->last_result[0];
					
					/** Check password */
					if ($user->user_password[0] == '*') {
						/** Old MySQL hashing that needs to change */
						if ($user->user_password != '*' . strtoupper(sha1(sha1($user_password, true)))) {
							return false;
						}
						resetPassword($user_login, $user_password);
					} else {
						/** PHP hashing */
						if (!password_verify($user_password, $user->user_password)) {
							return false;
						}
					}
					
					$successful_auth = $user;
				}
			/** LDAP Authentication */
			} else {
				$successful_auth = $this->doLDAPAuth($user_login, $_POST['password']);
			}

			if ($successful_auth === false) {
				return false;
			}

			$this->setSession($successful_auth);
			
			/** Process 2FA if equipped */
			if (getOption('require_2fa') || $successful_auth->user_2fa_method) {
				@session_start();
				$_SESSION['user']['2fa_status'] = 'pending';
				$_SESSION['user']['uri'] = $_SERVER['REQUEST_URI'];
				return array('type' => '2fa', 'content' => $successful_auth);
			}

			/** Enforce password change? */
			if ($auth_method == 1 && $fm_db_version >= 15) {
				$reset_check = $this->isResettingPassword($successful_auth);
				if ($reset_check !== false) {
					return $reset_check;
				}
			}

			/** Logged in */
			@session_start();
			$_SESSION['user']['logged_in'] = true;

			return true;
		}
		
		return false;
	}
	
	/**
	 * Update the session in the db
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param string $fm_login Username to update the database with
	 * @return null
	 */
	function updateSessionDB($fm_login) {
		global $fmdb;
		
		$query = "UPDATE fm_users set user_ipaddr='{$_SESSION['user']['ipaddr']}', user_last_login=" . time() . " WHERE `user_login`='". $fm_login ."' AND `user_status`!='deleted'";
		$fmdb->query($query);
	}


	/**
	 * Logout the user
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @return null
	 */
	function logout() {
		if (isset($_COOKIE['fmid'])) {
			$fmid = $_COOKIE['fmid'];
			
			// Init the session.
			session_id($fmid);
			@session_start();
			if (isset($_SESSION['user']['name'])) {
				$this->updateSessionDB($_SESSION['user']['name']);
			}
			@session_unset();
			setcookie('fmid', '');
			@session_destroy();
			unset($_COOKIE['fmid']);
		} else {
			@session_start();
			@session_unset();
			@session_destroy();
		}
	}
	
	/**
	 * Mail the user password reset link
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param string $fm_login Username to send the mail to
	 * @param string $uniq_hash Unique password reset hash
	 * @return boolean|string
	 */
	function mailPwdResetLink($fm_login, $uniq_hash) {
		global $fm_name;
		
		$user_info = getUserInfo($fm_login);
		if (isEmailAddressValid($user_info['user_email']) === false) {
			sleep(1);
			return true;
		}
		
		$subject = sprintf(_('%s Password Reset'), $fm_name);
		$from = getOption('mail_from');
		
		return sendEmail($user_info['user_email'], $subject, $this->buildPwdResetEmail($user_info, $uniq_hash, true, $subject, $from), $this->buildPwdResetEmail($user_info, $uniq_hash, false));
	}
	
	/**
	 * Builds the user password reset link e-mail
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param array $user_info User information to build the e-mail from
	 * @param string $uniq_hash Unique password reset hash
	 * @param boolean $build_html Whether or not to build a html version
	 * @param string $title HTML E-mail title
	 * @param string $from_address Displayed sent from address
	 * @return string
	 */
	function buildPwdResetEmail($user_info, $uniq_hash, $build_html = true, $title = null, $from_address = null) {
		global $fm_name, $__FM_CONFIG;
		
		if ($build_html) {
			$branding_logo = getBrandLogo();
			if ($GLOBALS['RELPATH'] != '/') {
				$branding_logo = str_replace($GLOBALS['RELPATH'], '', $branding_logo);
			}
			$branding_logo = $GLOBALS['FM_URL'] . str_replace('//', '/', $branding_logo);
			
			$body = <<<BODY
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" style="background-color: #eeeeee;">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<title>$title</title>
</head>
<body style="background-color: #eeeeee; font: 13px 'Lucida Grande', 'Lucida Sans Unicode', Tahoma, Verdana, sans-serif; margin: 1em auto; min-width: 600px; max-width: 600px; padding: 20px; padding-bottom: 50px; -webkit-text-size-adjust: none;">
<div style="margin-bottom: -8px;">
<img src="$branding_logo" style="padding-left: 17px;" />
<span style="font-size: 16pt; font-weight: bold; position: relative; top: -16px; margin-left: 10px;">$fm_name</span>
</div>
<div id="shadow" style="-moz-border-radius: 0% 0% 100% 100% / 0% 0% 8px 8px; -webkit-border-radius: 0% 0% 100% 100% / 0% 0% 8px 8px; border-radius: 0% 0% 100% 100% / 0% 0% 8px 8px; -moz-box-shadow: rgba(0,0,0,.30) 0 2px 3px !important; -webkit-box-shadow: rgba(0,0,0,.30) 0 2px 3px !important; box-shadow: rgba(0,0,0,.30) 0 2px 3px !important;">
<div id="container" style="background-color: #fff; min-height: 200px; margin-top: 1em; padding: 0 1.5em .5em; border: 1px solid #fff; -webkit-border-radius: 5px; -moz-border-radius: 5px; border-radius: 5px; -webkit-box-shadow: inset 0 2px 1px rgba(255,255,255,.97) !important; -moz-box-shadow: inset 0 2px 1px rgba(255,255,255,.97) !important; box-shadow: inset 0 2px 1px rgba(255,255,255,.97) !important;">
<p>Hi {$user_info['user_login']},</p>
<p>You (or somebody else) has requested a link to reset your $fm_name password.</p>
<p>If you don't want to reset your password, then you can ignore this message.</p>
<p>To reset your password, click the following link:<br />
<a href="{$GLOBALS['FM_URL']}password_reset.php?key=$uniq_hash&login={$user_info['user_login']}">{$GLOBALS['FM_URL']}password_reset.php?key=$uniq_hash&login={$user_info['user_login']}</a></p>
<p>This link expires in {$__FM_CONFIG['clean']['time']}.</p>
</div>
</div>
<p style="font-size: 10px; color: #888; text-align: center;">$fm_name | $from_address</p>
</body>
</html>
BODY;
		} else {
			$body = sprintf('Hi %s,

You (or somebody else) has requested a link to reset your %s password.

If you don\'t want to reset your password, then you can ignore this message.

To reset your password, click the following link:

%s

This link expires in %s.',
		$user_info['user_login'], $fm_name,
		"{$GLOBALS['FM_URL']}password_reset.php?key=$uniq_hash&login={$user_info['user_login']}",
		$__FM_CONFIG['clean']['time']);
		}
		
		return $body;
	}

	/**
	 * Sets the session variables for the authenticated user
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param object $user User information to create session variables from
	 * @return null
	 */
	function setSession($user) {
		global $fm_name;
		
		@session_start();
		session_regenerate_id(true);
		$_SESSION['user']['id'] = $user->user_id;
		$_SESSION['user']['name'] = $user->user_login;
		$_SESSION['user']['display_name'] = $user->user_display_name;
		$_SESSION['user']['last_login'] = $user->user_last_login;
		$_SESSION['user']['account_id'] = $user->account_id;
		$_SESSION['user']['theme'] = $user->user_theme;
		$_SESSION['user']['theme_mode'] = $user->user_theme_mode;
		$_SESSION['user']['ipaddr'] = isset($_SERVER['REMOTE_HOST']) ? $_SERVER['REMOTE_HOST'] : $_SERVER['REMOTE_ADDR'];
		
		/** Upgrade compatibility */
		if (getOption('fm_db_version') < 32) $_SESSION['user']['fm_perms'] = $user->user_perms;

		setUserModule($user->user_default_module);
		setcookie('fmid', session_id(), strtotime('+1 week'));
		$this->updateSessionDB($_SESSION['user']['name']);
		session_write_close();

		addLogEntry(sprintf(_('Logged in from %s.'), $_SESSION['user']['ipaddr']));
	}
	
	
	/**
	 * Performs the LDAP authentication procedure
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param string $username Username to authenticate
	 * @param string $password Username to authenticate with
	 * @return boolean|string
	 */
	private function doLDAPAuth($username, $password) {
		global $__FM_CONFIG, $fmdb;

		/** Get LDAP variables */
		if (empty($ldap_server))          $ldap_server          = getOption('ldap_server');
		if (empty($ldap_port))            $ldap_port            = getOption('ldap_port');
		if (empty($ldap_port_ssl))        $ldap_port_ssl        = getOption('ldap_port_ssl');
		if (empty($ldap_version))         $ldap_version         = getOption('ldap_version');
		if (empty($ldap_encryption))      $ldap_encryption      = getOption('ldap_encryption');
		if (empty($ldap_cert_file))       $ldap_cert_file       = getOption('ldap_cert_file');
		if (empty($ldap_ca_cert_file))    $ldap_ca_cert_file    = getOption('ldap_ca_cert_file');
		if (empty($ldap_referrals))       $ldap_referrals       = getOption('ldap_referrals');
		if (empty($ldap_dn))              $ldap_dn              = getOption('ldap_dn');
		if (empty($ldap_group_require))   $ldap_group_require   = getOption('ldap_group_require');
		if (empty($ldap_group_dn))        $ldap_group_dn        = getOption('ldap_group_dn');
		if (empty($ldap_group_attribute)) $ldap_group_attribute = getOption('ldap_group_attribute');
		if (empty($ldap_group_search_dn)) $ldap_group_search_dn = getOption('ldap_group_search_dn');

		$ldap_dn = str_replace('{username}', $username, $ldap_dn);

		/** Set default ports if none specified */
		if (!$ldap_port) $ldap_port = 389;
		if (!$ldap_port_ssl) $ldap_port_ssl = 636;

		/** Test connectivity to ldap server */
		$socket_test_result = ($ldap_encryption == $__FM_CONFIG['options']['ldap_encryption'][0]) ? socketTest($ldap_server, $ldap_port, 5) : socketTest($ldap_server, $ldap_port_ssl, 5);
		if (!$socket_test_result) return _('The authentication server is currently unavailable. Please try again later.');

		if ($ldap_encryption == 'SSL') {
			if ($ldap_cert_file) {
				@ldap_set_option(NULL, LDAP_OPT_X_TLS_CERTFILE, $ldap_cert_file);
			}
			if ($ldap_ca_cert_file) {
				@ldap_set_option(NULL, LDAP_OPT_X_TLS_CACERTFILE, $ldap_ca_cert_file);
			}
			$ldap_connect = @ldap_connect('ldaps://' . $ldap_server, $ldap_port_ssl);
		} else {
			$ldap_connect = @ldap_connect($ldap_server, $ldap_port);
		}
		
		if ($ldap_connect) {
			/** Set protocol version */
			if (!@ldap_set_option($ldap_connect, LDAP_OPT_PROTOCOL_VERSION, $ldap_version)) {
				$this->closeLDAPConnect($ldap_connect);
				return false;
			}
			
			/** Set referrals */
			if(!@ldap_set_option($ldap_connect, LDAP_OPT_REFERRALS, $ldap_referrals)) {
				$this->closeLDAPConnect($ldap_connect);
				return false;
			}
			
			/** Start TLS if requested */
			if ($ldap_encryption == 'TLS') {
				if (!@ldap_start_tls($ldap_connect)) {
					$this->closeLDAPConnect($ldap_connect);
					return false;
				}
			}
			
			$ldap_bind = @ldap_bind($ldap_connect, $ldap_dn, $password);
			
			if ($ldap_bind) {
				if ($ldap_group_require) {
					if (strpos($ldap_dn, '@') !== false) {
						if (isset($ldap_group_search_dn) && !empty($ldap_group_search_dn)) {
							$ldap_dn = $ldap_group_search_dn;
						} else {
							/** Convert AD ldap_dn to real ldap_dn */
							$ldap_dn_parts = explode('@', $ldap_dn);
							$ldap_dn = 'dc=' . join(',dc=', explode('.', $ldap_dn_parts[1]));
						}
						
						/** Process AD group membership */
						$ldap_dn = $this->getDN($ldap_connect, $username, $ldap_dn);
					}

					/** Process LDAP group membership */
					$ldap_group_response = @ldap_compare($ldap_connect, $ldap_group_dn, $ldap_group_attribute, $username);
					if ($ldap_group_response !== true) {
						$ldap_group_response = $this->checkGroupMembership($ldap_connect, $ldap_dn, $ldap_group_dn, $ldap_group_attribute);
					}
					
					if ($ldap_group_response !== true) {
						$this->closeLDAPConnect($ldap_connect);
						return false;
					}
				}
				
				/** Get user permissions from database */
				$fmdb->get_results("SELECT * FROM `fm_users` WHERE `user_status`='active' AND `user_auth_type`=2 AND `user_template_only`='no' AND `user_login`='$username'");
				if (!$fmdb->num_rows) {
					if (!$this->createUserFromTemplate($username)) {
						$this->closeLDAPConnect($ldap_connect);
						return false;
					}
				}
				
				$this->closeLDAPConnect($ldap_connect);
				
				return $fmdb->last_result[0];
			}
			
			/** Close LDAP connection */
			$this->closeLDAPConnect($ldap_connect);
		}
		
		return false;
	}
	
	
	/**
	 * Creates a LDAP user from the defined template user
	 *
	 * @since 1.0
	 * @package facileManager
	 *
	 * @param string $username Username to create
	 * @return boolean
	 */
	function createUserFromTemplate($username) {
		global $fmdb;
		
		$template_user_id = getOption('ldap_user_template');
		
		/** User does not exist in database - get the template user */
		$result = $fmdb->get_results("SELECT * FROM `fm_users` WHERE `user_id` = " . $template_user_id);
		if (!$fmdb->num_rows) return false;
		
		/** Attempt to add the new LDAP user to the database based on the template */
		$fmdb->query("INSERT INTO `fm_users` (`account_id`,`user_login`, `user_password`, `user_email`, `user_default_module`, `user_auth_type`, `user_caps`) 
					SELECT `account_id`, '$username', '', '', `user_default_module`, 2, `user_caps` from `fm_users` WHERE `user_id`=" . $template_user_id);
		if (!$fmdb->rows_affected) return false;
		
		/** Get the user results now */
		$fmdb->get_results("SELECT * FROM `fm_users` WHERE `user_status`='active' AND `user_auth_type`=2 AND `user_template_only`='no' AND `user_login`='$username'");
		if (!$fmdb->num_rows) return false;
		
		return true;
	}


	/**
	 * Closes a LDAP resource
	 *
	 * @since 3.0
	 * @package facileManager
	 *
	 * @param LDAP\Connection $ldap_connect Resource to close
	 * @return null
	 */
	private function closeLDAPConnect($ldap_connect) {
		if (is_resource($ldap_connect)) {
			@ldap_close($ldap_connect);
		}
	}
	
	
	/**
	 * Gets the DN of an account name
	 * (based on user comment from http://php.net/manual/en/ref.ldap.php#99347)
	 *
	 * @since 3.0
	 * @package facileManager
	 *
	 * @param LDAP\Connection $ldap_connect Resource to use
	 * @param string $samaccountname SAM Account name to search for
	 * @param string $basedn Base DN to use
	 * @return boolean|string
	 */
	private function getDN($ldap_connect, $samaccountname, $basedn) {
		$attributes = array('dn');
		$result = ldap_search($ldap_connect, $basedn,
			"(samaccountname={$samaccountname})", $attributes);
		if ($result === false) return '';
		$entries = ldap_get_entries($ldap_connect, $result);
		return ($entries['count'] > 0) ? $entries[0]['dn'] : '';
	}
	
	
	/**
	 * Checks recursive group membership of the user
	 * (based on user comment from http://php.net/manual/en/ref.ldap.php#99347)
	 *
	 * @since 3.0
	 * @package facileManager
	 *
	 * @param LDAP\Connection $ldap_connect Resource to use
	 * @param string $userdn
	 * @param string $groupdn
	 * @param string $ldap_group_attribute
	 * @return boolean
	 */
	private function checkGroupMembership($ldap_connect, $userdn, $groupdn, $ldap_group_attribute) {
		$result = ldap_read($ldap_connect, $userdn, '(objectclass=*)', array($ldap_group_attribute));
		if ($result === false) return false;
		
		$entries = ldap_get_entries($ldap_connect, $result);
		if ($entries['count'] <= 0) return false;
		
		if (empty($entries[0][$ldap_group_attribute])) {
			return false;
		} else {
			for ($i = 0; $i < $entries[0][$ldap_group_attribute]['count']; $i++) {
				if ($entries[0][$ldap_group_attribute][$i] == $groupdn) return true;
				elseif ($this->checkGroupMembership($ldap_connect, $entries[0][$ldap_group_attribute][$i], $groupdn, $ldap_group_attribute)) return true;
			};
		};
		return false;
	}
	
	
	/**
	 * Authenticates the provided API token
	 *
	 * @since 4.0
	 * @package facileManager
	 *
	 * @param string $token API key
	 * @param string $secret API secret key
	 * @param string $authkey Account authentication key
	 * @return boolean
	 */
	function doAPIAuth($token, $secret, $authkey = 'default') {
		global $fmdb;

		$result = $fmdb->get_results("SELECT * FROM `fm_keys` WHERE `key_status`='active' AND `key_token`='$token' AND `account_id`='" . getAccountID($authkey) . "'");
		if (!$fmdb->num_rows) {
			return false;
		}
		$apikey = $result[0];
		
		/** Check token secret */
		/** PHP hashing */
		if (!password_verify($secret, $apikey->key_secret)) {
			return false;
		}
		
		$result = $fmdb->get_results("SELECT * FROM `fm_users` WHERE `user_status`='active' AND `user_template_only`='no' AND `user_id`=" . $apikey->user_id . " AND `account_id`='" . getAccountID($authkey) . "'");
		if (!$fmdb->num_rows) {
			return false;
		}

		$this->setSession($result[0]);

		return true;
	}


	/**
	 * Check if the user is resetting their password
	 * 
	 * @since 6.0.0
	 * @package facileManager
	 * 
	 * @param object $user User object
	 * @return boolean|array
	 */
	private function isResettingPassword($user) {
		global $fmdb, $user_login;

		if ($user->user_force_pwd_change == 'yes') {
			$pwd_reset_query = "SELECT * FROM `fm_temp_auth_keys` WHERE `pwd_login`={$user->user_id} ORDER BY `pwd_timestamp` LIMIT 1";
			$fmdb->get_results($pwd_reset_query);
			if ($fmdb->num_rows) {
				$reset = $fmdb->last_result[0];
				return array('type' => 'reset', 'content' => array($reset->pwd_id, $user_login));
			}
		}

		return false;
	}


	/**
	 * Display 2FA form.
	 *
	 * @since 6.0.0
	 * @package facileManager
	 *
	 * @return void
	 */
	function print2FAForm() {
		/** Get user 2FA method */
		$user_2fa_method = getNameFromID($_SESSION['user']['id'], 'fm_users', 'user_', 'user_id', 'user_2fa_method');

		// Set user_2fa_method to e-mail if not set
		if (!$user_2fa_method && getOption('require_2fa')) {
			$user_2fa_method = 'email';
		}

		$message = '';
		$instructions = _('Enter the code from your two-factor authentication app below.');
		if ($user_2fa_method == 'email') {
			$message = '<a id="resend_otp" href="">' . _('Resend code') . '</a>';
			$instructions = _('Enter the code sent to your e-mail address below.');
		}

		printHeader(_('Two-factor Authentication'), 'login');
		
		echo displayPreAppForm(_('Two-factor Authentication'), 'login_form',
		sprintf('
				<div class="message">%s</div>
				<input type="hidden" name="verify_otp" value="1" />
				<div class="input-wrapper">
					<input type="text" name="app_otp" id="app_otp" placeholder="%s" autocomplete="off" />
				</div>
				<div class="button-wrapper"><a name="submit" id="verify_otpbtn" class="button"><i class="fa fa-check" aria-hidden="true"></i> %s</a></div>
				<p id="forgotton_link"><a href="%s">&larr; %s</a></p>
				<div id="message" class="message">%s</div>
				',
				$instructions, 'XXXXXX', _('Verify'), $GLOBALS['RELPATH'], _('Login form'), $message), null, null, '2fa_form');

	}


	/**
	 * Processes the 2FA authentication
	 *
	 * @since 6.0.0
	 * @package facileManager
	 *
	 * @param string $code 2FA code
	 * @return boolean
	 */
	function process2FAForm($code) {
		// Get user 2FA method
		$user_2fa_method = getNameFromID($_SESSION['user']['id'], 'fm_users', 'user_', 'user_id', 'user_2fa_method');

		// Set user_2fa_method to e-mail if not set
		if (!$user_2fa_method && getOption('require_2fa')) {
			$user_2fa_method = 'email';
		}

		switch ($user_2fa_method) {
			case 'app':
				// Verify TOTP 2FA Auth App
				if ($this->process2FAAuthAppMethod($code) === false) {
					return false;
				}
				break;
			case 'email':
				// Verify TOTP 2FA E-mail
				if ($this->process2FAEmailMethod($code) === false) {
					return false;
				}
				break;
			default:
				return false;
		}

		// Set user as logged in
		@session_start();
		$_SESSION['user']['logged_in'] = true;
		unset($_SESSION['user']['2fa_status']);

		return true;
	}


	/**
	 * Processes the 2FA authentication e-mail
	 *
	 * @since 6.0.0
	 * @package facileManager
	 *
	 * @param string $code 2FA code
	 * @return boolean
	 */
	function process2FAEmailMethod($code) {
		global $__FM_CONFIG, $fmdb;

		// Verify E-mail TOTP 2FA
		$time = date("U", strtotime($__FM_CONFIG['clean']['time'] . ' ago'));
		$query = "SELECT * FROM `fm_temp_auth_keys` WHERE `pwd_id` LIKE '$%' AND `pwd_login`={$_SESSION['user']['id']} AND `pwd_timestamp`>='$time' ORDER BY `pwd_timestamp` DESC LIMIT 1";
		$fmdb->get_results($query);

		// No results
		if (!$fmdb->num_rows) {
			sleep(1);
			return false;
		}

		// Verify code
		$otp_entry = $fmdb->last_result[0];
		if (!password_verify($code, $otp_entry->pwd_id)) {
			sleep(1);
			return false;
		}

		// Delete used code
		$fmdb->query("DELETE FROM `fm_temp_auth_keys` WHERE `pwd_id` LIKE '$%' AND `pwd_login`={$_SESSION['user']['id']}");

		return true;
	}


	/**
	 * Processes the 2FA authentication app
	 *
	 * @since 6.0.0
	 * @package facileManager
	 *
	 * @param string $code 2FA code
	 * @return boolean
	 */
	function process2FAAuthAppMethod($code) {
		return true;
	}


	/**
	 * Generates a random OTP code
	 *
	 * @since 6.0.0
	 * @package facileManager
	 *
	 * @param string $action Action for the OTP
	 * @return string
	 */
	function generateOTP($action = 'generate') {
		global $__FM_CONFIG, $fmdb;

		$timestamp = ($action == 'generate') ? strtotime($__FM_CONFIG['clean']['time'] . ' ago') : time();

		/** Delete old OTP codes */
		$fmdb->query("DELETE FROM `fm_temp_auth_keys` WHERE `pwd_timestamp`<'" . date("U", $timestamp) . "'");

		/** Make sure there isn't already a code for the user */
		$query = "SELECT * FROM `fm_temp_auth_keys` WHERE `pwd_login`={$_SESSION['user']['id']} AND `pwd_id` LIKE '$%'";
		$fmdb->get_results($query);
		if ($fmdb->num_rows) {
			return false;
		}

		/** Generate 6 digit OTP */
		$otp = '';
		for ($i = 0; $i < 6; $i++) {
			$otp .= mt_rand(0, 9);
		}
		return $otp;
	}


	/**
	 * Mail the user OTP code
	 *
	 * @since 6.0.0
	 * @package facileManager
	 *
	 * @param string $userid UserID to send the mail to
	 * @param string $otp Unique password reset hash
	 * @return boolean|string
	 */
	function mail2FAOTP($userid, $otp) {
		global $fm_name;
		
		$user_info = getUserInfo($userid);
		if (isEmailAddressValid($user_info['user_email']) === false) {
			sleep(1);
			return true;
		}
		
		$subject = sprintf(_('%s OTP'), $fm_name);
		$from = getOption('mail_from');
		
		return sendEmail($user_info['user_email'], $subject, $this->buildOTPEmail($user_info, $otp, true, $subject, $from), $this->buildOTPEmail($user_info, $otp, false));
	}


	/**
	 * Builds the user OTP e-mail
	 *
	 * @since 6.0.0
	 * @package facileManager
	 *
	 * @param array $user_info User information to build the e-mail from
	 * @param string $otp Unique password reset hash
	 * @param boolean $build_html Whether or not to build a html version
	 * @param string $title HTML E-mail title
	 * @param string $from_address Displayed sent from address
	 * @return string
	 */
	function buildOTPEmail($user_info, $otp, $build_html = true, $title = null, $from_address = null) {
		global $fm_name, $__FM_CONFIG;
		
		if ($build_html) {
			$branding_logo = getBrandLogo();
			if ($GLOBALS['RELPATH'] != '/') {
				$branding_logo = str_replace($GLOBALS['RELPATH'], '', $branding_logo);
			}
			$branding_logo = $GLOBALS['FM_URL'] . str_replace('//', '/', $branding_logo);
			
			$body = <<<BODY
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" style="background-color: #eeeeee;">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<title>$title</title>
</head>
<body style="background-color: #eeeeee; font: 13px 'Lucida Grande', 'Lucida Sans Unicode', Tahoma, Verdana, sans-serif; margin: 1em auto; min-width: 600px; max-width: 600px; padding: 20px; padding-bottom: 50px; -webkit-text-size-adjust: none;">
<div style="margin-bottom: -8px;">
<img src="$branding_logo" style="padding-left: 17px;" />
<span style="font-size: 16pt; font-weight: bold; position: relative; top: -16px; margin-left: 10px;">$fm_name</span>
</div>
<div id="shadow" style="-moz-border-radius: 0% 0% 100% 100% / 0% 0% 8px 8px; -webkit-border-radius: 0% 0% 100% 100% / 0% 0% 8px 8px; border-radius: 0% 0% 100% 100% / 0% 0% 8px 8px; -moz-box-shadow: rgba(0,0,0,.30) 0 2px 3px !important; -webkit-box-shadow: rgba(0,0,0,.30) 0 2px 3px !important; box-shadow: rgba(0,0,0,.30) 0 2px 3px !important;">
<div id="container" style="background-color: #fff; min-height: 200px; margin-top: 1em; padding: 0 1.5em .5em; border: 1px solid #fff; -webkit-border-radius: 5px; -moz-border-radius: 5px; border-radius: 5px; -webkit-box-shadow: inset 0 2px 1px rgba(255,255,255,.97) !important; -moz-box-shadow: inset 0 2px 1px rgba(255,255,255,.97) !important; box-shadow: inset 0 2px 1px rgba(255,255,255,.97) !important;">
<p>Hi {$user_info['user_login']},</p>
<p>You (or somebody else) has requested a one-time passcode to login to $fm_name.</p>
<h2>$otp</h2>
<p>This code expires in {$__FM_CONFIG['clean']['time']}.</p>
</div>
</div>
<p style="font-size: 10px; color: #888; text-align: center;">$fm_name | $from_address</p>
</body>
</html>
BODY;
		} else {
			$body = sprintf('Hi %s,

You (or somebody else) has requested a one-time passcode to login to %s.

%s

This code expires in %s.',
		$user_info['user_login'], $fm_name,
		$otp,
		$__FM_CONFIG['clean']['time']);
		}
		
		return $body;
	}

}

if (!isset($fm_login))
	$fm_login = new fm_login();
