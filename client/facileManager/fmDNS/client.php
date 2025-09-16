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
 | fmDNS: Easily manage one or more ISC BIND servers                       |
 +-------------------------------------------------------------------------+
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
*/

/**
 * fmDNS Client Utility
 *
 * @package fmDNS
 * @subpackage Client
 *
 */

/** Client version */
$data['server_client_version'] = '7.2.0';

error_reporting(0);

$module_name = basename(dirname(__FILE__));

/** Include shared client functions */
$fm_client_functions = dirname(__FILE__, 2) . '/functions.php';
if (file_exists($fm_client_functions)) {
	include_once($fm_client_functions);
} else {
	echo "The facileManager core client scripts are required, but not found.\n";
	exit(1);
}

/** Check for options */
if (!$invoke_api) {
	$zones = (in_array('-z', $argv) || in_array('zones', $argv)) ? true : false;
	$dump_cache = in_array('dump-cache', $argv) ? true : false;
	$clear_cache = in_array('clear-cache', $argv) ? true : false;
	$dump_zone = in_array('dump-zone', $argv) ? true : false;
	$enable_url = in_array('enable', $argv) && in_array('url', $argv) ? true : false;
} else {
	$zones = $dump_cache = $clear_cache = $dump_zone = $enable_url = false;
	$api_supported_rr = array('A', 'AAAA', 'CNAME', 'DNAME', 'MX', 'NS', 'PTR', 'TXT');
	$api_params = array(
		'common' => array('action', 'id', 'type', 'name', 'value', 'ttl', 'comment', 'status', 'reload', 'soa-only', 'setPTR'),
		'CNAME'  => array('append'),
		'DNAME'  => array('append'),
		'MX'  => array('priority', 'append'),
		'update' => array('newname', 'newvalue')
	);
}

/** Get long options */
for ($i=0; $i < count($argv); $i++) {
	if (strpos($argv[$i], 'id=') !== false) {
		$data['domain_id'] = substr($argv[$i], 3);
	}
	if ($argv[$i] == '-D') {
		$domain = $argv[$i+1];
		$i++;
	}
	if ($argv[$i] == '-f') {
		$zonefile = $argv[$i+1];
		$i++;
	}

	/** Get API parameters */
	if ($invoke_api) {
		foreach (array_unique(call_user_func_array('array_merge', array_values($api_params))) as $param) {
			if (strpos($argv[$i], $param . '=') === 0) {
				$prefix = ($param == 'id') ? 'domain_' : 'record_';
				if (in_array($param, array('action', 'reload', 'soa-only', 'setPTR'))) $prefix = null;
				$data['api'][$prefix . $param] = substr($argv[$i], strlen($param) + 1);

				validateAPIParam($param, $data['api'][$prefix . $param]);
			}
		}
	}
}

if (isset($data['domain_id'])) $data['api']['domain_id'] = $data['domain_id'];

if ($invoke_api) {
	$validation_error = false;

	if ((!isset($data['api']['action']) && isset($data['api']['reload']) && $data['api']['reload'] == 'yes')
		|| (!isset($data['api']['action']) && isset($data['api']['soa-only']) && $data['api']['soa-only'] == 'yes')) {
		if (!array_key_exists('domain_id', $data['api'])) {
			echo fM("id is a required parameter.\n");
			$validation_error = true;
		}
		$data['api']['action'] = 'RELOAD';
	} else {
		/** Verify type is supported */
		if (!isset($data['api']['record_type'])) {
			echo fM("type is a required parameter.\n");
			exit(1);
		} else {
			$data['api']['record_type'] = strtoupper($data['api']['record_type']);
			if (!in_array($data['api']['record_type'], $api_supported_rr)) {
				echo fM(sprintf("%s is not a supported RR type.\nSupported types: %s\n", $data['api']['record_type'], join(', ', $api_supported_rr)));
				exit(1);
			}
		}

		/** Remove optional parameters */
		for ($x = 1; $x <= 6; $x++) {
			array_pop($api_params['common']);
		}
		if ($data['api']['action'] == 'delete') {
			array_pop($api_params['CNAME']);
			unset($api_params['common'][array_search('value', $api_params['common'])]);
		}

		/** Check if all required parameters are given for API calls */
		foreach (@array_merge($api_params['common'], (array) $api_params[$data['api']['record_type']]) as $key) {
			if (!array_key_exists($key, $data['api']) && !array_key_exists('record_' . $key, $data['api']) && !array_key_exists('domain_' . $key, $data['api'])) {
				echo fM($key . " is a required parameter.\n");
				$validation_error = true;
			}
		}
	}

	if ($validation_error) {
		exit(1);
	}
}

/** Check if running supported version */
$data['server_version'] = detectAppVersion();

/** Enable URL hosting */
if ($enable_url) {
	enableURL();
}

/** Build the zone files */
if ($zones) {
	$data['action'] = 'zones';
}

/** Dump the cache */
if ($dump_cache) {
	manageCache('dumpdb -cache', 'Dumping cache');
}

/** Clear the cache */
if ($clear_cache) {
	manageCache('flush', 'Clearing cache');
}

/** Dump the zone data */
if ($dump_zone) {
	if (!isset($domain) || !isset($zonefile)) {
		printHelp();
	}
	dumpZone($domain, $zonefile);
}

/** Build the configs provided by $url */
$retval = ($invoke_api) ? callAPI($url, $data, strtoupper($data['api']['action'])) : buildConf($url, $data);

if (!$retval) exit(1);
