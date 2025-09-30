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
 | Processes module dashboard page                                         |
 +-------------------------------------------------------------------------+
*/

if (defined('CLIENT')) {
    header('Location: ' . $GLOBALS['RELPATH']);
    exit;
}

if (defined('NO_DASH')) {
    list($filtered_menu, $filtered_submenu) = getCurrentUserMenu();
    ksort($filtered_menu);
    ksort($filtered_submenu);

    /** Loop through the available menu items for the first page */
    foreach ($filtered_menu as $menu) {
        $menu_key = $menu[5];
        if (!array_key_exists($menu_key, $filtered_submenu)) {
            header('Location: ' . $menu_key);
            exit;
        }
        foreach ($filtered_submenu[$menu_key] as $submenu) {
            if ($submenu[5]) {
                header('Location: ' . $submenu[5]);
                exit;
            }
        }
    }
}

printHeader();
@printMenu();

$response = isset($response) ? $response : functionalCheck();

echo printPageHeader($response, null, false) . buildDashboard();

printFooter();
