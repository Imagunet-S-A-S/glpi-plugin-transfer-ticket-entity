<?php

/**
 -------------------------------------------------------------------------
 LICENSE

 This file is part of Transferticketentity plugin for GLPI.
 GLPI 11 compatible — executed by LegacyFileLoadController with full GLPI bootstrap.

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

use GlpiPlugin\Transferticketentity\Entity as TransferEntity;

if (!isset($_SESSION['glpiactiveprofile']['id'])) {
    \Html::redirect(\Entity::getFormURL());
    exit;
}

if (!isset($_POST['transfertticket'])) {
    $id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : -1;
    if ($id >= 0) {
        \Html::redirect(\Entity::getFormURL() . "?id={$id}");
    } else {
        \Html::redirect(\Entity::getFormURL());
    }
    exit;
}

global $DB;

$rawId = $_POST['plugin_entity_id'] ?? null;

if ($rawId === null || $rawId === '') {
    $rawId = $_POST['id'] ?? $_REQUEST['id'] ?? null;
}

if ($rawId === null || $rawId === '') {
    \Session::addMessageAfterRedirect(__('Invalid entity ID', 'transferticketentity'), true, ERROR);
    \Html::redirect(\Entity::getFormURL());
    exit;
}

$ID = (int) $rawId;

if (!\Session::haveRightsOr(TransferEntity::$rightname, [CREATE, UPDATE, PURGE])) {
    \Html::redirect(\Entity::getFormURL() . "?id={$ID}");
    exit;
}

$allow_entity_only_transfer = (int) ($_POST['allow_entity_only_transfer'] ?? 0);
$justification_transfer     = (int) ($_POST['justification_transfer'] ?? 0);
$allow_transfer             = (int) ($_POST['allow_transfer'] ?? 0);
$keep_category              = (int) ($_POST['keep_category'] ?? 0);
$raw_cat                    = $_POST['itilcategories_id'] ?? '';
$itilcategories_id          = ($raw_cat !== '' && $raw_cat !== 'null') ? (int) $raw_cat : 0;

$existing = iterator_to_array($DB->request([
    'SELECT' => ['id'],
    'FROM'   => 'glpi_plugin_transferticketentity_entities_settings',
    'WHERE'  => ['entities_id' => $ID],
]));

$data = [
    'allow_entity_only_transfer' => $allow_entity_only_transfer,
    'justification_transfer'     => $justification_transfer,
    'allow_transfer'             => $allow_transfer,
    'keep_category'              => $keep_category,
    'itilcategories_id'          => $itilcategories_id,
];

if (!empty($existing)) {
    $DB->update(
        'glpi_plugin_transferticketentity_entities_settings',
        $data,
        ['entities_id' => $ID]
    );
} else {
    $data['entities_id'] = $ID;
    $DB->insert('glpi_plugin_transferticketentity_entities_settings', $data);
}

$entityName = '';
foreach ($DB->request(['FROM' => 'glpi_entities', 'WHERE' => ['id' => $ID]]) as $row) {
    $entityName = $row['name'];
}

if ($entityName === '') {
    $entityName = __('Root entity');
}

\Session::addMessageAfterRedirect(
    sprintf(__('Item successfully updated: %s'), $entityName),
    true,
    INFO
);

$tabId = urlencode(TransferEntity::class . '$1');

\Html::redirect(
    \Entity::getFormURL() . "?id={$ID}&forcetab={$tabId}"
);
exit;
