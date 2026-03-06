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
 | http://www.facilemanager.com/modules/fmdns/                             |
 +-------------------------------------------------------------------------+
 | Processes API requests                                                  |
 +-------------------------------------------------------------------------+
*/

if (!isset($domain_id)) $domain_id = 0;
if (!isset($record_id)) $record_id = 0;
$record_type = 'ALL';
$valid_api_main_request_types = ['views' => ['GET'], 'zones' => $valid_api_request_methods];
$zone_reload_allowed = $zone_reload_requested = false;

/** Parse REST API URI */

/** Get main request */
if (isset($_path_parts[$api_root_key + 1])) {
    $api_main_request_type = sanitize($_path_parts[$api_root_key + 1]);
}
/** Ensure we get a request type */
if (!isset($api_main_request_type) && $api_main_request_type) {
    returnAPIStatus(400);
}
/** Ensure request type is valid */
if (!array_key_exists($api_main_request_type, $valid_api_main_request_types)) {
    returnAPIStatus(400);
}
/** Ensure request method is supported by request type */
if (!in_array($method, $valid_api_main_request_types[$api_main_request_type])) {
    returnAPIStatus(400);
}

if ($api_main_request_type == 'views') {
    /** Should the user be here? */
    if (!currentUserCan('manage_servers', $_SESSION['module'])) {
        returnAPIStatus(1000);
    }

    $api_views_key = $api_root_key + 1;

    /** Get the view_id */
    if (isset($_path_parts[$api_views_key + 1]) && $_path_parts[$api_views_key + 1]) {
        $view_id = intval($_path_parts[$api_views_key + 1]);
    }
}







$api_domain_key = array_search('zones', $_path_parts);
/** Get the domain_id */
if (isset($_path_parts[$api_domain_key + 1])) {
    $domain_id = intval($_path_parts[$api_domain_key + 1]);
}
if (isset($_path_parts[$api_domain_key + 2]) && $_path_parts[$api_domain_key + 2] == 'records') {
    $api_record_key = $api_domain_key + 2;
}
/** Get the record_id or record_type */
if (isset($api_record_key) && isset($_path_parts[$api_record_key + 1]) && $_path_parts[$api_record_key + 1]) {
    $_path_parts[$api_record_key + 1] = sanitize($_path_parts[$api_record_key + 1]);
    if (intval($_path_parts[$api_record_key + 1]) == $_path_parts[$api_record_key + 1]) {
        $record_id = $_path_parts[$api_record_key + 1];
    } else {
        $record_type = strtoupper($_path_parts[$api_record_key + 1]);
    }
}

unset($_path_parts);

if ($method === 'GET') {
    /** Get zone records */
    if (isset($api_record_key)) {
        /** Ensure user has access to zone */
        if (currentUserCan(array('access_specific_zones', 'view_all'), $_SESSION['module'], array(0, $domain_id))) {
            $parent_domain_ids = getZoneParentID($domain_id);
            $record_sql = "AND domain_id IN (" . join(',', $parent_domain_ids) . ")";

            if ($record_id) {
                /** Get the record from id */
                $record_sql .= " AND record_id=$record_id";
            } elseif ($record_type != 'ALL') {
                $record_sql .= " AND record_type='$record_type'";
            }

            $result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', array('record_type', 'record_name', 'record_value'), 'record_', $record_sql);
            if ($result) {
                $data = getAPIRecordInformation($fmdb->last_result, 'record');
            } else {
                returnAPIStatus(404);
            }
        } else {
            returnAPIStatus(403);
        }
        unset($api_record_key);
    } elseif (isset($api_domain_key) && $api_domain_key !== false) {
        /** Get zone information */
        if (isset($view_id)) {
            $_GET['domain_view'] = [$view_id];
        }
        $map = 'forward';
        include(dirname(__FILE__) . '/zones.php');
        unset($_GET['domain_view']);
        if ($result) {
            $data = getAPIRecordInformation($fmdb->last_result, 'zone');
        } else {
            returnAPIStatus(404, __('No zones found.'));
        }
        unset($api_domain_key);

    } elseif ($api_main_request_type == 'views') {
        /** Get view information */
        $view_sql = (isset($view_id)) ? "AND view_id=$view_id" : '';
        $result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'views', array('view_order_id', 'view_name'), 'view_', $view_sql);
        if ($result) {
            $data = getAPIRecordInformation($fmdb->last_result, 'view');
        } else {
            returnAPIStatus(404, __('No views found.'));
        }

    }
    return;
}

if (!$domain_id && isset($_POST['domain_id'])) {
    $domain_id = intval($_POST['domain_id']);
}

/** Is zone reload requested? */
if ((isset($api_input['api']) && isset($api_input['api']['reload']) && $api_input['api']['reload'] == 'yes')
    || (isset($api_input['reload']) && $api_input['reload'] == 'yes' )) {
    $zone_reload_requested = true;
}

/** Should the user be here? */
if (!currentUserCan('manage_records', $_SESSION['module'])) {
    returnAPIStatus(1000);
}
if (isset($domain_id) && !zoneAccessIsAllowed(array($domain_id))) {
    returnAPIStatus(1001);
}
if (in_array($record_data['record_type'], $__FM_CONFIG['records']['require_zone_rights']) && !currentUserCan('manage_zones', $_SESSION['module'])) {
    returnAPIStatus(1002);
}
if ($zone_reload_requested) {
    if (!currentUserCan('reload_zones', $_SESSION['module']) && zoneAccessIsAllowed(array($domain_id))) {
        returnAPIStatus(1003);
    }
}

include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_records.php');
$record_addl_sql = " AND domain_id=$domain_id";

switch ($method) {
	case 'POST':
        if (isset($api_input['api'])) {
            $exclude = array('action', 'domain_id', 'reload');
            foreach ($api_input['api'] as $key => $val) {
                if (!in_array($key, $exclude)) $record_data[$key] = $val;
            }
        } elseif (isset($api_input)) {
            $record_data = cleanAndTrimInputs($api_input);
        }
        /** Automatically manage PTR? */
        if (isset($record_data['setPTR']) && $record_data['setPTR'] == 'yes') {
            $record_data['PTR'] = 'yes';
            unset($record_data['setPTR']);
        }
        
        /** Remove double quotes */
        if (isset($record_data['record_value'])) $record_data['record_value'] = str_replace('"', '', $record_data['record_value']);
        
        /** Validate the submission */
        $record_id = 0;
        $_arr = [
            'domain_id' => $domain_id,
            'create' => [
                $record_id => []
            ]
        ];
        if (isset($record_data)) {
            /** Ensure record_status is set */
            if (!isset($record_data['record_status']) && !isset($errors['record_status'])) {
                $record_data['record_status'] = 'active';
            }

            $_arr['create'][$record_id] = array_merge($_arr['create'][$record_id], $record_data);
        }
        [$_content, $errors] = $fm_dns_records->validateRecordUpdates($_arr, 'array');
        unset($_arr, $_content);

        /** Ensure a record_type is set */
        if (!isset($record_data['record_type']) && !isset($errors['record_type'])) {
            $errors['record_type'] = sprintf(__('%s must be one of the following: %s'), 'record_type', implode(', ', enumMYSQLSelect('fm_' . $__FM_CONFIG[$module_name]['prefix'] . 'records', 'record_type', 'sort')));
        }

        if (count($errors)) {
            returnAPIStatus(400, null, $errors);
        } elseif (!$_POST['dryrun']) {
            $code = 201;
            $retval = $fm_dns_records->add($domain_id, $record_data['record_type'], $record_data);
            if (is_bool($retval)) {
                if ($retval === false) {
                    $code = 2000;
                } else {
                    $zone_reload_allowed = true;
                }

                /** Are we auto-creating a PTR record? */
                if (!autoManagePTR($domain_id, $record_data['record_type'], $record_data)) {
                    $code = 202;
                }
            } else {
                /** Record already exists */
                $code = 1004;
            }
        } else {
            /** Dry-run */
            $code = 3000;
        }

        break;
	case 'PATCH':
        if (isset($api_input['api'])) {
            $exclude = array('action', 'domain_id', 'reload');
            foreach ($api_input['api'] as $key => $val) {
                if (!in_array($key, $exclude)) $record_data[$key] = $val;
            }
        } elseif (isset($api_input)) {
            $record_data = cleanAndTrimInputs($api_input);
        }
        /** Automatically manage PTR? */
        if (isset($record_data['setPTR']) && $record_data['setPTR'] == 'yes') {
            $record_data['PTR'] = 'yes';
            unset($record_data['setPTR']);
        }
        
        /** Remove double quotes */
        if (isset($record_data['record_value'])) $record_data['record_value'] = str_replace('"', '', $record_data['record_value']);
        
        if ($record_id) {
            /** Get the record from id */
            basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $record_id, 'record_', 'record_id', $record_addl_sql);
        } else {
            if (isset($record_data['record_value'])) {
                $record_addl_sql .= " AND record_value='{$record_data['record_value']}'";
            }
            if (isset($record_data['record_type'])) {
                $record_addl_sql .= " AND record_type='{$record_data['record_type']}'";
            }
            
            basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $record_data['record_name'], 'record_', 'record_name', $record_addl_sql);
        }
        if ($fmdb->num_rows == 1) {
            if (isset($record_data['record_newname'])) {
                $record_data['record_name'] = $record_data['record_newname'];
                unset($record_data['record_newname']);
            }
            if (isset($record_data['record_newvalue'])) {
                $record_data['record_value'] = $record_data['record_newvalue'];
                unset($record_data['record_newvalue']);
            }

            /** Validate the submission */
            $record_id = $fmdb->last_result[0]->record_id;
            $record_type = $fmdb->last_result[0]->record_type;
            $_arr = [
                'domain_id' => $domain_id,
                'record_type' => $fmdb->last_result[0]->record_type,
                'update' => [
                    $record_id => []
                ]
            ];
            if (isset($record_data)) {
                $_arr['update'][$record_id] = array_merge($_arr['update'][$record_id], $record_data);
            }
            [$_content, $errors] = $fm_dns_records->validateRecordUpdates($_arr, 'array');
            unset($_arr, $_content);

            if (count($errors)) {
                returnAPIStatus(400, null, $errors);
            } elseif (!$_POST['dryrun']) {
                $code = 200;
                $retval = true;
                if ($record_id) {
                    $retval = $fm_dns_records->update($domain_id, $record_id, $record_type, $record_data);
                }
                if ($retval === false) {
                    $code = 2000;
                } else {
                    $zone_reload_allowed = true;
                }
                
                /** Get current record information */
                if (isset($record_data['PTR'])) {
                    basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $record_id, 'record_', 'record_id');
                    if ($fmdb->num_rows) $old_record = $fmdb->last_result[0];
                    
                    /** Are we auto-creating a PTR record? */
                    if (!autoManagePTR($domain_id, $record_type, $record_data, 'update', $old_record)) {
                        $code = 202;
                    }
                }
            } else {
                /** Dry-run */
                $code = 3000;
            }
        } elseif (isset($record_data['soa-only'])) {
            /** Update the SOA serial number */
            $fm_dns_records->processSOAUpdates($domain_id, 'NONE', 'update');
            $zone_reload_allowed = true;
            $code = 200;
			addLogEntry(sprintf(__("Incremented SOA serial number for zone '%s'."), displayFriendlyDomainName(getNameFromID($domain_id, 'fm_'. $__FM_CONFIG[$_SESSION['module']]['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'))));
        } elseif (!$record_data && $zone_reload_requested == true) {
            $zone_reload_allowed = true;
            $code = 200;
        } else {
            $code = 1005;
        }
		break;
	case 'DELETE':
        /** Make sure we have a proper path */
        if ($record_id || is_array($api)) {
            /** Lookup record_id */
            if ($record_id) {
                basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $record_id, 'record_', 'record_id', $record_addl_sql);
            } else {
                $record_addl_sql .= (isset($api['record_value'])) ? ' AND record_value="' . $api['record_value'] . '"' : null;
                basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $api['record_name'], 'record_', 'record_name', $record_addl_sql . ' AND record_type="' . $api['record_type'] . '"');
            }
            /** Get record_id from array values */
            if ($fmdb->num_rows) {
                $num_rows = $fmdb->num_rows;
                $last_result = $fmdb->last_result;
                if ($record_id) {
                    $t_record_id = $record_id;
                }
                if (!$dryrun) {
                    $code = 204;
                    for ($i=0; $i<$num_rows; $i++) {
                        if (!$record_id) {
                            $record_id = $last_result[$i]->record_id;
                        }
                        $record_arr = ['record_name' => $last_result[$i]->record_name, 'record_value' => $last_result[$i]->record_value, 'record_status' => 'deleted'];
                        $retval = $fm_dns_records->update($domain_id, $record_id, $last_result[$i]->record_type, $record_arr);
                        if ($retval === false) {
                            $code = 2000;
                        } else {
                            $zone_reload_allowed = true;
                
                            /** Are we deleting a linked PTR record? */
                            if ($last_result[0]->record_ptr_id && in_array($last_result[0]->record_type, array('A', 'AAAA')) && $record_arr['record_status'] == 'deleted') {
                                $record_arr['PTR'] = $domain_id;
                                $record_arr['record_ptr_id'] = $last_result[0]->record_ptr_id;
                                if (!autoManagePTR($domain_id, $last_result[0]->record_type, $record_arr, 'update', $last_result[0])) {
                                    $code = 205;
                                }
                            }
                        }
                        $record_id = $t_record_id;
                    }
                } else {
                    /** Dry-run */
                    $code = 3000;
                }
                unset($t_record_id);
            } else {
                $code = 1005;
            }
        } else {
            $code = 400;
        }
        break;
	default:
        $code = 400;
}

/** Reload zone if specificed */
if (in_array($code, [200, 201, 202, 204]) && $zone_reload_allowed && $zone_reload_requested) {
    apiReloadZone($domain_id);
}

/** Return status */
if (isset($code)) {
    returnAPIStatus($code);
}

exit;



/**
 * Reloads zone from API
 *
 * @since 7.2.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param integer $domain_id ID of domain to reload
 * @return string
 */
function apiReloadZone($domain_id) {
    global $__FM_CONFIG, $fmdb, $fm_dns_zones;

    if (!$domain_id) {
        returnAPIStatus(400, __('No domain id specified'));
    }

    $response = __('This zone cannot be reloaded.');

    if (getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_reload') == 'yes') {
        if (!class_exists('fm_dns_zones')) {
            include(ABSPATH . 'fm-modules/' . $_SESSION['module'] . '/classes/class_zones.php');
        }
        $response = $fm_dns_zones->buildZoneConfig($domain_id);
    }
    
    return $response;
}


/**
 * Gets record information for API
 *
 * @since 7.2.0
 * @package facileManager
 * @subpackage fmDNS
 *
 * @param array $result Database query result array
 * @param string $type Type of information to provide (zone, record)
 * @return array
 */
function getAPIRecordInformation($result, $type) {
    global $__FM_CONFIG;

    $data = array();

    if ($type == 'record') {
        foreach ($result as $arr) {
            $t_data = [
                'id' => $arr->record_id,
                'type' => $arr->record_type,
                'name' => $arr->record_name,
                'value' => $arr->record_value,
                'ttl' => $arr->record_ttl
            ];
            if ($arr->record_type == 'CUSTOM') {
                array_pop($t_data);
            }
            if (in_array($arr->record_type, $__FM_CONFIG['records']['priority'])) {
                $t_data['priority'] = $arr->record_priority;
            }
            if (in_array($arr->record_type, $__FM_CONFIG['records']['weight'])) {
                $t_data['weight'] = $arr->record_weight;
            }
            if (in_array($arr->record_type, $__FM_CONFIG['records']['append'])) {
                $t_data['append'] = $arr->record_append;
            }
            $t_data['status'] = $arr->record_status;
            $t_data['comment'] = $arr->record_comment;
            
            $data[] = $t_data;
            unset($t_data);
        }
    } elseif ($type == 'zone') {
        foreach ($result as $arr) {
            $data[] = [
                'id' => $arr->domain_id,
                'name' => $arr->domain_name,
                'soa_serial_no' => $arr->soa_serial_no,
                'reload' => $arr->domain_reload,
                'comment' => $arr->domain_comment,
                'servers' => getServerName($arr->domain_name_servers)
            ];
        }
    } elseif ($type == 'view') {
        foreach ($result as $arr) {
            $data[] = [
                'id' => $arr->view_id,
                'name' => $arr->view_name,
                'comment' => $arr->view_comment,
                'servers' => getServerName($arr->server_serial_no)
            ];
        }
    }

    return $data;
}
