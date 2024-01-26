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

namespace tests\units;

use DbTestCase;

/* Test for inc/networkequipment.class.php */

class NetworkEquipment extends DbTestCase
{
    public function testNetEquipmentCRUD()
    {
        $this->login();

       //create network equipment
        $device = new \NetworkEquipment();
        $input = [
            'name'         => 'Test equipment',
            'entities_id'  => 0
        ];
        $netequipments_id = $device->add($input);
        $this->integer($netequipments_id)->isGreaterThan(0);

        $this->boolean($device->getFromDB($netequipments_id))->isTrue();
        $this->string($device->fields['name'])->isIdenticalTo('Test equipment');

       //create ports attached
        $netport = new \NetworkPort();
        $input = [
            'itemtype'           => $device->getType(),
            'items_id'           => $device->getID(),
            'entities_id'        => 0,
            'logical_number'     => 1256,
            'name'               => 'Test port',
            'instantiation_type' => 'NetworkPortEthernet'
        ];
        $netports_id = $netport->add($input);
        $this->integer($netports_id)->isGreaterThan(0);

        $this->boolean($netport->getFromDB($netports_id))->isTrue();
        $this->string($netport->fields['name'])->isIdenticalTo('Test port');

        $input = [
            'itemtype'           => $device->getType(),
            'items_id'           => $device->getID(),
            'entities_id'        => 0,
            'logical_number'     => 1257,
            'name'               => 'Another test port',
            'instantiation_type' => 'NetworkPortAggregate'
        ];
        $netports_id = $netport->add($input);
        $this->integer($netports_id)->isGreaterThan(0);

        $this->boolean($netport->getFromDB($netports_id))->isTrue();
        $this->string($netport->fields['name'])->isIdenticalTo('Another test port');

        $this->integer($netport->countForItem($device))->isIdenticalTo(2);

       //remove network equipment
        $this->boolean($device->delete(['id' => $netequipments_id], true))->isTrue();

       //see if links are dropped
        $this->integer($netport->countForItem($device))->isIdenticalTo(0);
    }
}
