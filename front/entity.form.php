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

// Only process POST with our submit button
if (!isset($_POST['transfertticket'])) {
    // GET or other — redirect to entity form (or list if no id)
    $id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : -1;
    if ($id >= 0) {
        \Html::redirect(\Entity::getFormURL() . "?id={$id}");
    } else {
        \Html::redirect(\Entity::getFormURL());
    }
    exit;
}

global $DB;

// FIX: entity 0 (root) is a valid entity — use -1 as "not provided" sentinel
// We use 'plugin_entity_id' to avoid collision with GLPI's own 'id' POST param
// Cast to int: "0" → 0 (root entity, valid), "" or missing → we check separately
$rawId = $_POST['plugin_entity_id'] ?? null;

if ($rawId === null || $rawId === '') {
    // Fallback chain — should not normally be needed
    $rawId = $_POST['id'] ?? $_REQUEST['id'] ?? null;
}

// If still null/empty (not set at all), it's a truly invalid request
if ($rawId === null || $rawId === '') {
    \Session::addMessageAfterRedirect(__('Invalid entity ID', 'transferticketentity'), true, ERROR);
    \Html::redirect(\Entity::getFormURL());
    exit;
}

$ID = (int) $rawId; // 0 is valid (root entity)

// Check rights
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

// FIX: Use updateOrInsert pattern to avoid "first save fails" bug on initial insert.
// On GLPI 11, $DB->delete() on a non-existing row returns false but does NOT throw —
// however some MariaDB configs raise a warning that breaks the subsequent insert
// inside the same request. Using explicit check + conditional insert/update is safer.
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
    // Record exists — UPDATE in place
    $DB->update(
        'glpi_plugin_transferticketentity_entities_settings',
        $data,
        ['entities_id' => $ID]
    );
} else {
    // First time for this entity — INSERT
    $data['entities_id'] = $ID;
    $DB->insert('glpi_plugin_transferticketentity_entities_settings', $data);
}

// Flash message with entity name
$entityName = '';
foreach ($DB->request(['FROM' => 'glpi_entities', 'WHERE' => ['id' => $ID]]) as $row) {
    $entityName = $row['name'];
}
// Root entity (id=0) name may not appear in glpi_entities table — use fallback
if ($entityName === '') {
    $entityName = __('Root entity');
}

\Session::addMessageAfterRedirect(
    sprintf(__('Item successfully updated: %s'), $entityName),
    true,
    INFO
);

// FIX: forcetab must be URL-encoded. The tab key uses the plugin class FQCN.
// GlpiPlugin\Transferticketentity\Entity → tab id = "GlpiPlugin\Transferticketentity\Entity$1"
// URL-encode backslashes so the browser/GLPI router handles it correctly.
$tabId = urlencode(TransferEntity::class . '$1');

\Html::redirect(
    \Entity::getFormURL() . "?id={$ID}&forcetab={$tabId}"
);
exit;
