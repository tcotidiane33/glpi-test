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

/**
 * @var DB $DB
 * @var Migration $migration
 */

// CleanSoftwareCron cron task
CronTask::register(
    'CleanSoftwareCron',
    'cleansoftware',
    MONTH_TIMESTAMP,
    [
        'state'         => 0,
        'param'         => 1000,
        'mode'          => 2,
        'allowmode'     => 3,
        'logs_lifetime' => 300,
    ]
);
// /CleanSoftwareCron cron task

// Add architecture to software versions
if (!$DB->fieldExists('glpi_softwareversions', 'arch', false)) {
    $migration->addField(
        'glpi_softwareversions',
        'arch',
        'string',
        [
            'after' => 'name'
        ]
    );
    $migration->addKey('glpi_softwareversions', 'arch');
}
// /Add architecture to software versions
