<?php

/**
 -------------------------------------------------------------------------
 LICENSE

 This file is part of Transferticketentity plugin for GLPI.

 @category  Ticket
 @package   Transferticketentity
 @author    Giovanny Rodriguez <giovanny.rodriguez@imagunet.com>
 @author    Santiago Gomez <santiago.gomez@imagunet.com>
 @author    Juan Gallego <juan.gallego@imagunet.com>
 @copyright 2026 IMAGUNET S.A.S
 @license   AGPL License 3.0 or (at your option) any later version
 @link      https://www.imagunet.com
 --------------------------------------------------------------------------
*/

Html::header_nocache();
Session::checkLoginUser();

header('Content-Type: application/json; charset=UTF-8');

global $DB;

$result = $DB->request([
    'SELECT' => [
        'entities_id',
        'allow_entity_only_transfer',
        'justification_transfer',
        'allow_transfer',
        'keep_category',
    ],
    'FROM'  => 'glpi_plugin_transferticketentity_entities_settings',
    'ORDER' => ['entities_id ASC'],
]);

$data = [];
foreach ($result as $row) {
    $data[] = [
        'entities_id'                => (int) $row['entities_id'],
        'allow_entity_only_transfer' => (int) $row['allow_entity_only_transfer'],
        'justification_transfer'     => (int) $row['justification_transfer'],
        'allow_transfer'             => (int) $row['allow_transfer'],
        'keep_category'              => (int) $row['keep_category'],
    ];
}

echo json_encode($data, JSON_THROW_ON_ERROR);
