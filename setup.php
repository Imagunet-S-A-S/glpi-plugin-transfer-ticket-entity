<?php

/**
 -------------------------------------------------------------------------
 LICENSE

 This file is part of Transferticketentity plugin for GLPI.

 Transferticketentity is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 Transferticketentity is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with Reports. If not, see <http://www.gnu.org/licenses/>.

 @category  Ticket
 @package   Transferticketentity
 @author    Giovanny Rodriguez <giovanny.rodriguez@imagunet.com>
 @author    Santiago Gomez <santiago.gomez@imagunet.com>
 @author    Juan Gallego <juan.gallego@imagunet.com>
 @copyright 2026 IMAGUNET S.A.S
 @license   AGPL License 3.0 or (at your option) any later version
            https://www.gnu.org/licenses/agpl-3.0.html
 @link      https://www.imagunet.com
 --------------------------------------------------------------------------
*/

use GlpiPlugin\Transferticketentity\Entity;
use GlpiPlugin\Transferticketentity\Profile;
use GlpiPlugin\Transferticketentity\Ticket;

define('TRANSFERTICKETENTITY_VERSION', '2.1.0');

function plugin_init_transferticketentity()
{
    global $PLUGIN_HOOKS;

    Plugin::registerClass(Profile::class, ['addtabon' => \Profile::class]);
    Plugin::registerClass(Ticket::class,  ['addtabon' => \Ticket::class]);
    Plugin::registerClass(Entity::class,  ['addtabon' => \Entity::class]);

    $PLUGIN_HOOKS['csrf_compliant']['transferticketentity'] = true;
}

function plugin_version_transferticketentity()
{
    return [
        'name'           => 'TransferTicketEntity',
        'version'        => TRANSFERTICKETENTITY_VERSION,
        'author'         => 'Giovanny Rodriguez, Santiago Gomez, Juan Gallego &mdash; IMAGUNET S.A.S',
        'license'        => 'AGPLv3+',
        'homepage'       => 'https://www.imagunet.com',
        'requirements'   => [
            'glpi' => [
                'min' => '11.0',
                'max' => '12.0',
            ],
        ],
    ];
}

function plugin_transferticketentity_check_prerequisites()
{
    $version = preg_replace('/^((\d+\.?)+).*$/', '$1', GLPI_VERSION);

    if (version_compare($version, '11.0', '<')) {
        echo "This plugin requires GLPI >= 11.0";
        return false;
    }

    return true;
}

function plugin_transferticketentity_check_config($verbose = false)
{
    return true;
}

function plugin_transferticketentity_options()
{
    return [
        Plugin::OPTION_AUTOINSTALL_DISABLED => true,
    ];
}
