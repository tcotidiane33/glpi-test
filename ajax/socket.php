<?php

/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2022 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

include('../inc/includes.php');

// Send UTF8 Headers
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

switch ($_POST['action']) {
    case 'getItemsFromItemtype':
        if ($_POST['itemtype'] && class_exists($_POST['itemtype'])) {
            $_POST['itemtype']::dropdown(['name'                => $_POST['dom_name'],
                'display_emptychoice' => true,
                'rand' => $_POST['dom_rand']
            ]);
        }
        break;

    case 'getNetworkPortFromItem':
        NetworkPort::dropdown(['name'                => 'networkports_id',
            'display_emptychoice' => true,
            'condition'           => ["items_id" => $_POST['items_id'],
                "itemtype" => $_POST['itemtype']
            ]
        ]);
        break;
}
