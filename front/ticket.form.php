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

use GlpiPlugin\Transferticketentity\Ticket as TransferTicket;

if (!isset($_SESSION['glpiactiveprofile']['id'])) {
    \Html::redirect(\Ticket::getFormURL());
    exit;
}

$id_ticket = (int) ($_POST['id_ticket'] ?? 0);
$fallbackUrl = $id_ticket > 0
    ? \Ticket::getFormURL() . "?id={$id_ticket}"
    : \Ticket::getFormURL();

if (isset($_POST['canceltransfert'])) {
    \Session::addMessageAfterRedirect(
        __("Transfer canceled", 'transferticketentity'),
        true,
        ERROR
    );
    \Html::redirect($fallbackUrl);
    exit;
}

if (!isset($_POST['transfertticket'])) {
    \Html::redirect($fallbackUrl);
    exit;
}

global $DB;

$entity_choice = (int) ($_REQUEST['entity_choice'] ?? 0);
$group_choice  = (int) ($_REQUEST['group_choice'] ?? 0);
$justification = $_POST['justification'] ?? '';

$transfer = new TransferTicket();

$checkTechRight         = $transfer->checkTechRight();
$checkAssign            = $transfer->checkAssign();
$checkEntity            = $transfer->checkEntityETT();
$checkGroup             = $transfer->checkGroup();
$checkEntityRight       = $transfer->checkEntityRight();
$checkExistingCategory  = $transfer->checkExistingCategory();
$checkMandatoryCategory = $transfer->checkMandatoryCategory();
$theEntity              = $transfer->theEntity();
$theGroup               = $transfer->theGroup();
$requiredGroup          = true;

// Validations
if (empty($justification) && !empty($checkEntityRight['justification_transfer'])) {
    \Session::addMessageAfterRedirect(__("Please explain your transfer", 'transferticketentity'), true, ERROR);
    \Html::redirect($fallbackUrl);
    exit;
}

if (empty($group_choice) && !empty($checkEntityRight['allow_entity_only_transfer'])) {
    \Session::addMessageAfterRedirect(__("Please select a valid group", 'transferticketentity'), true, ERROR);
    \Html::redirect($fallbackUrl);
    exit;
} elseif (empty($group_choice)) {
    $requiredGroup = false;
}

if (empty($checkTechRight) || (!$checkTechRight[0] && !$checkAssign)) {
    \Session::addMessageAfterRedirect(__("You must be assigned to the ticket to be able to transfer it", 'transferticketentity'), true, ERROR);
    \Html::redirect($fallbackUrl);
    exit;
}

if (!in_array($entity_choice, $checkEntity)) {
    \Session::addMessageAfterRedirect(__("Please select a valid entity", 'transferticketentity'), true, ERROR);
    \Html::redirect($fallbackUrl);
    exit;
}

if (!empty($group_choice) && !in_array($group_choice, $checkGroup)) {
    \Session::addMessageAfterRedirect(__("Please select a valid group", 'transferticketentity'), true, ERROR);
    \Html::redirect($fallbackUrl);
    exit;
}

// Execute transfer
$ticket = new \Ticket();
$ticket_update = ['id' => $id_ticket, 'entities_id' => $entity_choice];

if (!empty($checkEntityRight['keep_category'])) {
    if (!$checkExistingCategory && !empty($checkEntityRight['itilcategories_id'])) {
        $ticket_update['itilcategories_id'] = $checkEntityRight['itilcategories_id'];
    }
} else {
    $ticket_update['itilcategories_id'] = !empty($checkEntityRight['itilcategories_id'])
        ? $checkEntityRight['itilcategories_id'] : 0;
}

if (isset($ticket_update['itilcategories_id']) && $ticket_update['itilcategories_id'] == 0 && $checkMandatoryCategory) {
    \Session::addMessageAfterRedirect(
        __("Category will be set to null but its configured as mandatory in GLPIs template, please contact your administrator.", 'transferticketentity'),
        true, ERROR
    );
    \Html::redirect($fallbackUrl);
    exit;
}

// Remove assigned users and groups
$ticket_user = new \Ticket_User();
foreach ($ticket_user->find(['tickets_id' => $id_ticket, 'type' => \CommonITILActor::ASSIGN]) as $id => $tu) {
    $ticket_user->delete(['id' => $id]);
}

$group_ticket = new \Group_Ticket();
foreach ($group_ticket->find(['tickets_id' => $id_ticket, 'type' => \CommonITILActor::ASSIGN]) as $id => $tu) {
    $group_ticket->delete(['id' => $id]);
}

// FIX double log: GLPI 11 core generates an automatic log entry when entities_id changes
// on $ticket->update(). We suppress it with '_nolog' => true so only our single
// ITILFollowup (below) is recorded — avoiding the duplicate message in the timeline.
$ticket_update['_nolog'] = true;
$ticket->update($ticket_update);

if ($requiredGroup && !empty($group_choice)) {
    $group_check = ['tickets_id' => $id_ticket, 'groups_id' => $group_choice, 'type' => \CommonITILActor::ASSIGN];
    if (!$group_ticket->find($group_check)) {
        $group_ticket->add($group_check);
    }
}

// Build the log content for the single transfer record
$groupText = $theGroup
    ? __("in the group", "transferticketentity") . " " . htmlspecialchars($theGroup)
    : '';

$content = __("Escalation to", "transferticketentity") . " " . htmlspecialchars($theEntity);
if ($groupText !== '') {
    $content .= " " . $groupText;
}
if (!empty($justification)) {
    $content .= "<br><br>" . htmlspecialchars($justification);
}

// Use ITILFollowup (private) as the single transfer log entry.
// TicketTask was causing a double entry because GLPI 11 also auto-logs the
// entities_id change as a task. ITILFollowup is the correct mechanism for
// internal transfer notes and does NOT trigger an additional auto-log.
$followup = new \ITILFollowup();
$followup->add([
    'itemtype'   => 'Ticket',
    'items_id'   => $id_ticket,
    'is_private' => 1,
    'content'    => $content,
]);

\Session::addMessageAfterRedirect(
    __("Successful transfer for ticket n° : ", "transferticketentity") . $id_ticket,
    true, INFO
);

\Html::redirect($fallbackUrl);
exit;
