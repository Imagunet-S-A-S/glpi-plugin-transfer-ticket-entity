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

 @category  Ticket
 @package   Transferticketentity
 @author    Giovanny Rodriguez <giovanny.rodriguez@imagunet.com>
 * @author    Santiago Gomez <santiago.gomez@imagunet.com>
 * @author    Juan Gallego <juan.gallego@imagunet.com>
 @copyright 2026 IMAGUNET S.A.S
 @license   AGPL License 3.0 or (at your option) any later version
 @link      https://www.imagunet.com
 --------------------------------------------------------------------------
*/

namespace GlpiPlugin\Transferticketentity;

use CommonGLPI;
use Html;
use Session;
use Ticket as GlpiTicket;

class Ticket extends GlpiTicket
{
    /**
     * Check if the current user is a technician assigned to the ticket.
     * Returns an array: [0 => bool]
     *
     * @return array
     */
    public function checkTechRight(): array
    {
        global $DB;

        $id_ticket = (int) ($_REQUEST['id_ticket'] ?? $_REQUEST['id'] ?? 0);
        $id_user   = (int) ($_SESSION['glpiID'] ?? 0);

        $result = $DB->request([
            'SELECT' => ['tickets_id'],
            'FROM'   => 'glpi_tickets_users',
            'WHERE'  => [
                'tickets_id' => $id_ticket,
                'users_id'   => $id_user,
                'type'       => \CommonITILActor::ASSIGN,
            ],
        ]);

        return [0 => ($result->count() > 0)];
    }

    /**
     * Check if the current user has the "assign" right on tickets.
     *
     * @return bool
     */
    public function checkAssign(): bool
    {
        return \Session::haveRight('ticket', \Ticket::ASSIGN);
    }

    /**
     * Get the list of entity IDs that can receive the ticket
     * (entities that have allow_transfer = 1 in plugin settings).
     *
     * @return array
     */
    public function checkEntityETT(): array
    {
        global $DB;

        // The dropdown posts glpi_entities.id as entity_choice.
        // The plugin table stores the entity FK in 'entities_id'.
        // We must return the entities_id values so in_array() matches correctly.
        $result = $DB->request([
            'SELECT' => ['entities_id'],
            'FROM'   => 'glpi_plugin_transferticketentity_entities_settings',
            'WHERE'  => ['allow_transfer' => 1],
        ]);

        $array = [];
        foreach ($result as $data) {
            $array[] = (int) $data['entities_id'];
        }

        return $array;
    }

    /**
     * Get the list of group IDs that are assigned to the ticket.
     *
     * @return array
     */
    public function checkGroup(): array
    {
        global $DB;

        // Returns all group IDs that have is_assign=1 (assignable groups),
        // so the validation can confirm the submitted group_choice is valid.
        // This mirrors the list shown in the dropdown (getGroupEntities()).
        $result = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_groups',
            'WHERE'  => ['is_assign' => 1],
        ]);

        $array = [];
        foreach ($result as $data) {
            $array[] = (int) $data['id'];
        }

        return $array;
    }

    /**
     * Get the plugin entity settings for the chosen target entity.
     *
     * @return array
     */
    public function checkEntityRight(): array
    {
        global $DB;

        $entity_choice = (int) ($_REQUEST['entity_choice'] ?? 0);

        $result = $DB->request([
            'FROM'  => 'glpi_plugin_transferticketentity_entities_settings',
            'WHERE' => ['entities_id' => $entity_choice],
        ]);

        foreach ($result as $data) {
            return $data;
        }

        return [];
    }

    /**
     * Check if the ticket's current category exists in the target entity.
     *
     * @return bool
     */
    public function checkExistingCategory(): bool
    {
        global $DB;

        $id_ticket     = (int) ($_REQUEST['id_ticket'] ?? $_REQUEST['id'] ?? 0);
        $entity_choice = (int) ($_REQUEST['entity_choice'] ?? 0);

        // Get current category of the ticket
        $ticketResult = $DB->request([
            'SELECT' => ['itilcategories_id'],
            'FROM'   => 'glpi_tickets',
            'WHERE'  => ['id' => $id_ticket],
        ]);

        $itilcategories_id = 0;
        foreach ($ticketResult as $data) {
            $itilcategories_id = (int) $data['itilcategories_id'];
        }

        if ($itilcategories_id === 0) {
            return false;
        }

        // Check if that category exists in the target entity
        $catResult = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_itilcategories',
            'WHERE'  => [
                'id'          => $itilcategories_id,
                'entities_id' => $entity_choice,
            ],
        ]);

        return ($catResult->count() > 0);
    }

    /**
     * Check if a category is mandatory in the target entity's ITIL template.
     *
     * GLPI 11 compatibility:
     *  - Table renamed: glpi_tickettemplatesmandatoryfields → glpi_itiltemplatemandatoryfields
     *  - FK renamed:    tickettemplates_id                  → itiltemplates_id
     *  - We use \TicketTemplateMandatoryField::getTable() and the class constant so
     *    the code adapts automatically to whatever GLPI version is running.
     *  - The entity column 'tickettemplates_id' in glpi_entities is unchanged in GLPI 11.
     *
     * @return bool
     */
    public function checkMandatoryCategory(): bool
    {
        global $DB;

        $entity_choice = (int) ($_REQUEST['entity_choice'] ?? 0);

        // Find the default ticket template assigned to the target entity.
        // glpi_entities.tickettemplates_id is unchanged in GLPI 11.
        // For the root entity (id=0) this row may not exist → returns false safely.
        $entityResult = $DB->request([
            'SELECT' => ['tickettemplates_id'],
            'FROM'   => 'glpi_entities',
            'WHERE'  => ['id' => $entity_choice],
        ]);

        $template_id = 0;
        foreach ($entityResult as $data) {
            $template_id = (int) ($data['tickettemplates_id'] ?? 0);
        }

        if ($template_id === 0) {
            return false;
        }

        // Resolve table and FK name via GLPI class — handles GLPI 10 vs GLPI 11 rename.
        // GLPI 10: glpi_tickettemplatesmandatoryfields  / tickettemplates_id
        // GLPI 11: glpi_itiltemplatemandatoryfields     / itiltemplates_id
        $mandatoryFieldClass = new \TicketTemplateMandatoryField();
        $mandatoryTable      = $mandatoryFieldClass->getTable();

        // The FK column name also changed in GLPI 11. Detect dynamically:
        // GLPI 11 uses 'itiltemplates_id'; GLPI 10 used 'tickettemplates_id'.
        $fkColumn = $DB->fieldExists($mandatoryTable, 'itiltemplates_id')
            ? 'itiltemplates_id'
            : 'tickettemplates_id';

        // num = 7 is the field number for itilcategories_id in ticket templates.
        // This has not changed across GLPI versions.
        $mandatoryResult = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => $mandatoryTable,
            'WHERE'  => [
                $fkColumn => $template_id,
                'num'     => 7,
            ],
        ]);

        return ($mandatoryResult->count() > 0);
    }

    /**
     * Get the name of the target entity.
     *
     * @return string
     */
    public function theEntity(): string
    {
        global $DB;

        $entity_choice = (int) ($_REQUEST['entity_choice'] ?? 0);

        $result = $DB->request([
            'SELECT' => ['name'],
            'FROM'   => 'glpi_entities',
            'WHERE'  => ['id' => $entity_choice],
        ]);

        foreach ($result as $data) {
            return $data['name'];
        }

        return '';
    }

    /**
     * Get the name of the target group.
     *
     * @return string
     */
    public function theGroup(): string
    {
        global $DB;

        $group_choice = (int) ($_REQUEST['group_choice'] ?? 0);

        if ($group_choice === 0) {
            return '';
        }

        $result = $DB->request([
            'SELECT' => ['name'],
            'FROM'   => 'glpi_groups',
            'WHERE'  => ['id' => $group_choice],
        ]);

        foreach ($result as $data) {
            return $data['name'];
        }

        return '';
    }

    /**
     * If the profile is authorised, add an extra tab
     *
     * @param CommonGLPI $item         Ticket
     * @param int        $withtemplate 0
     *
     * @return string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        $checkProfiles = Profile::canUseProfiles();

        if (in_array($_SESSION['glpiactiveprofile']['id'], $checkProfiles)) {
            if ($item->getType() == 'Ticket') {
                return __("Transfer Ticket Entity", "transferticketentity");
            }
            return '';
        }

        return '';
    }

    /**
     * Give the ticket entity
     *
     * @return array
     */
    public function getTicketEntity()
    {
        global $DB;

        $id_ticket = $_REQUEST['id'];

        $result = $DB->request([
            'SELECT' => ['glpi_entities.id', 'glpi_entities.name'],
            'FROM' => 'glpi_tickets',
            'LEFT JOIN' => ['glpi_entities' => ['FKEY' => [
                'glpi_tickets'  => 'entities_id',
                'glpi_entities' => 'id'
            ]]],
            'WHERE' => ['glpi_tickets.id' => $id_ticket]
        ]);

        $array = [];

        foreach ($result as $data) {
            $array[] = $data['id'];
            $array[] = $data['name'];
        }

        return $array;
    }

    /**
     * Check that the ticket is not closed
     *
     * @return bool
     */
    public function checkTicket()
    {
        global $DB;

        $id_ticket = $_REQUEST['id'];

        $result = $DB->request([
            'SELECT' => 'id',
            'FROM' => 'glpi_tickets',
            'WHERE' => ['status' => 6]
        ]);

        $array = [];

        foreach ($result as $data) {
            $array[] = $data['id'];
        }

        return !in_array($id_ticket, $array);
    }

    /**
     * Get the group assigned to the ticket
     *
     * @return array
     */
    public function getTicketGroup()
    {
        global $DB;

        $id_ticket = $_REQUEST['id'];

        $result = $DB->request([
            'FROM' => 'glpi_groups_tickets',
            'WHERE' => ['tickets_id' => $id_ticket, 'type' => 2]
        ]);

        $array = [];

        foreach ($result as $data) {
            $array[] = $data['groups_id'];
        }

        return $array;
    }

    /**
     * Get all the entities which aren't the current entity with their rights
     *
     * @return array
     */
    public function getEntitiesRights()
    {
        global $DB;

        $getTicketEntity = self::getTicketEntity();
        $theEntity = $getTicketEntity[0];

        $result = $DB->request([
            'SELECT' => ['E.id', 'E.entities_id', 'E.name', 'TES.allow_entity_only_transfer', 'TES.justification_transfer', 'TES.allow_transfer'],
            'FROM' => 'glpi_entities AS E',
            'LEFT JOIN' => ['glpi_plugin_transferticketentity_entities_settings AS TES' => ['FKEY' => [
                'E'   => 'id',
                'TES' => 'entities_id'
            ]]],
            'WHERE' => ['NOT' => ['E.id' => $theEntity]],
            'GROUPBY' => 'E.id',
            'ORDER' => 'E.entities_id ASC'
        ]);

        $array = [];

        foreach ($result as $data) {
            $array[] = [
                'id'                        => $data['id'],
                'entities_id'               => $data['entities_id'],
                'name'                      => $data['name'],
                'allow_entity_only_transfer' => $data['allow_entity_only_transfer'],
                'justification_transfer'    => $data['justification_transfer'],
                'allow_transfer'            => $data['allow_transfer'],
            ];
        }

        return $array;
    }

    /**
     * Get the groups to which tickets can be assigned
     *
     * @return array
     */
    public function getGroupEntities()
    {
        global $DB;

        $result = $DB->request([
            'FROM' => 'glpi_groups',
            'WHERE' => ['is_assign' => 1],
            'ORDER' => ['entities_id ASC', 'id ASC']
        ]);

        $array = [];

        foreach ($result as $data) {
            $array[] = $data['id'];
            $array[] = $data['entities_id'];
            $array[] = $data['name'];
        }

        return $array;
    }

    /**
     * If we are on tickets, an additional tab is displayed
     *
     * @param CommonGLPI $item         Ticket
     * @param int        $tabnum       1
     * @param int        $withtemplate 0
     *
     * @return true
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == 'Ticket') {
            $ticket = new self();
            $ID = $item->getField('id');
            $ticket->showFormMcv($ID);
        }

        return true;
    }

    public static function addStyleSheetAndScript()
    {
        $ver = TRANSFERTICKETENTITY_VERSION;
        echo Html::css("/plugins/transferticketentity/public/css/style.css?v={$ver}");
        echo Html::script("/plugins/transferticketentity/public/js/script.js?v={$ver}");
    }

    /**
     * Return parent's entity name
     *
     * @param int $id Entity ID
     *
     * @return string
     */
    public function searchParentEntityName($id)
    {
        global $DB;

        $result = $DB->request([
            'FROM' => 'glpi_entities',
        ]);

        foreach ($result as $subArray) {
            if ($subArray['id'] == $id) {
                return $subArray['completename'];
            }
        }

        return '';
    }

    /**
     * Display the ticket transfer form
     *
     * @param int $ID Ticket ID
     *
     * @return void
     */
    public function showFormMcv($ID = 0)
    {
        global $DB, $CFG_GLPI;

        $getGroupEntities  = self::getGroupEntities();
        $getEntitiesRights = self::getEntitiesRights();

        $technician_profile = $_SESSION['glpiactiveprofile']['id'];

        $getAllEntities = [];

        foreach ($getEntitiesRights as $entity) {
            if ($entity['allow_transfer'] == 1) {
                $getAllEntities[] = $entity['entities_id'];
                $getAllEntities[] = $entity['id'];
                $getAllEntities[] = $entity['name'];
            }
        }

        if (!Session::haveRight('ticket', UPDATE)) {
            self::addStyleSheetAndScript();
            echo "<div class='unauthorised'>";
            echo "<p>" . __("You don't have right to update tickets. Please contact your administrator.", "transferticketentity") . "</p>";
            echo "</div>";
            return false;
        }

        if (empty($getAllEntities)) {
            self::addStyleSheetAndScript();
            echo "<div class='group_not_found'>";
            echo "<p>" . __("No entity available found, transfer impossible.", "transferticketentity") . "</p>";
            echo "</div>";
            return false;
        }

        $theServer = '';

        $id_ticket = $_REQUEST['id'];
        $id_user   = $_SESSION["glpiID"];
        $checkTicket = self::checkTicket();

        if ($checkTicket == false) {
            self::addStyleSheetAndScript();
            echo "<div class='unauthorised'>";
            echo "<p>" . __("Unauthorized transfer on closed ticket.", "transferticketentity") . "</p>";
            echo "</div>";
            return false;
        }

        echo "<div id='tt_gest_error'>";
        echo "<span class='loader'></span>";
        echo "</div>";

        $previousEntity = null;

        echo "
            <form class='form_transfert' action='" . $CFG_GLPI['root_doc'] . '/plugins/transferticketentity/front/ticket.form.php' . "' method='post'>
                <div class='tt_entity_choice'>
                    <label for='entity_choice'>" . __("Select ticket entity to transfer", "transferticketentity") . " : </label>
                    <select name='entity_choice' id='entity_choice' style='width: 30%'>
                        <option selected disabled value=''>-- " . __("Choose your entity", "transferticketentity") . " --</option>";

        foreach ($getEntitiesRights as $entity) {
            if ($entity['allow_transfer']) {
                if ($entity['entities_id'] === null) {
                    echo "<optgroup label='" . __('No previous entity', 'transferticketentity') . "'>";
                    echo "<option value='" . htmlspecialchars($entity['id']) . "'>" . htmlspecialchars($entity['name']) . "</option>";
                } else {
                    $searchParentEntityName = self::searchParentEntityName($entity['entities_id']);
                    if ($previousEntity != $searchParentEntityName) {
                        echo "</optgroup>";
                        echo "<optgroup label='" . htmlspecialchars($searchParentEntityName) . "'>";
                    }
                    echo "<option value='" . htmlspecialchars($entity['id']) . "'>" . htmlspecialchars($entity['name']) . "</option>";
                    $previousEntity = $searchParentEntityName;
                }
            }
        }

        echo "</optgroup>
                    </select>
                </div>";

        echo " <div class='group_not_found' id='nogroupfound'>" .
            __("No group found with « Assigned to » right while a group is required. Transfer impossible.", "transferticketentity") .
            "</div>";

        echo " <div class='tt_flex'>
                    <div class='tt_group_choice'>
                        <label for='group_choice'>" . __("Select the group to assign", "transferticketentity") . " : </label>
                        <select name='group_choice' id='group_choice' style='width: 30%'>
                            <option id='no_select' disabled value=''>-- " . __("Choose your group", "transferticketentity") . " --</option>
                            <option value='' id='tt_none'> " . __("None", "transferticketentity") . " </option>";

        for ($i = 0; $i < count($getGroupEntities); $i = $i + 3) {
            echo "<option class='tt_plugin_entity_" . htmlspecialchars($getGroupEntities[$i + 1]) . "' value='" . htmlspecialchars($getGroupEntities[$i]) . "'>" . htmlspecialchars($getGroupEntities[$i + 2]) . "</option>";
        }

        echo "  </select>
                        <div id='div_confirmation'>";
        echo "<button type='button' id='tt_btn_open_modal_form' class='btn btn-warning' disabled
                    data-bs-toggle='modal' data-bs-target='#tt_modal_form_adder'>" .
             __('Confirm', 'transferticketentity') .
             "</button>";
        echo "  </div>
                    </div>";

        echo Html::hidden("technician_profile", ["value" => $technician_profile]);
        echo Html::hidden("id_ticket", ["value" => $id_ticket]);
        echo Html::hidden("id_user", ["value" => $id_user]);
        echo Html::hidden("theServer", ["value" => $theServer]);

        echo "
                </div>

                <div class='modal fade' id='tt_modal_form_adder' tabindex='-1' aria-labelledby='tt_modal_label' aria-hidden='true'>
                    <div class='modal-dialog modal-dialog-centered'>
                        <div class='modal-content'>
                            <div class='modal-header'>
                                <h5 class='modal-title' id='tt_modal_label'>" . __("Confirm transfer ?", "transferticketentity") . "</h5>
                            </div>
                            <div class='modal-body'>
                                <p class='text-muted mb-3'>" . __("Once the transfer has been completed, the ticket will remain visible only if you have the required rights.", "transferticketentity") . "</p>
                                <div class='mb-3 text-start'>
                                    <label for='justification' class='form-label fw-bold'>" . __("Please explain your transfer", "transferticketentity") . " :</label>
                                    <textarea id='justification' name='justification' class='form-control' rows='3' required></textarea>
                                </div>
                                <p class='adv-msg fst-italic text-warning small mb-0'><i class='ti ti-alert-triangle me-1'></i>" . __("Warning, category will be reset if it does not exist in the target entity.", "transferticketentity") . "</p>
                            </div>
                            <div class='modal-footer'>";
        echo "<button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>" . __('Cancel') . "</button>";
        echo Html::submit(__('Confirm', 'transferticketentity'), ['name' => 'transfertticket', 'id' => 'transfertticket', 'class' => 'btn btn-warning']);
        echo "          </div>
                        </div>
                    </div>
                </div>";
        Html::closeForm();

        self::addStyleSheetAndScript();
        self::javascriptTranslate();
    }

    /**
     * Translate text added with JavaScript
     *
     * @return void
     */
    public function javascriptTranslate()
    {
        $addText = __('optional', 'transferticketentity');

        $jsPluginTTE = "
            $.ajax({
                url: CFG_GLPI.root_doc + '/plugins/transferticketentity/ajax/getEntitiesRights.php',
                method: 'GET',
                success: function (data) {
                    if (typeof data === 'string') { data = JSON.parse(data); }

                    if (document.querySelector('.tt_entity_choice') != null) {
                        let explainText = document.getElementById('justification').previousElementSibling.innerHTML;

                        $('#entity_choice').on('change', function (event) {
                            let entityRights = data.filter(e => e.entities_id == entity_choice.value)
                            let justificationRight = entityRights[0]['justification_transfer']
                            let addText = '';

                            if (justificationRight == 1) {
                                addText = ':'
                                document.getElementById('justification').previousElementSibling.innerHTML = explainText + addText;
                            } else {
                                addText = '($addText)' + ' :'
                                document.getElementById('justification').previousElementSibling.innerHTML = explainText + addText;
                            }
                        })
                    }
                }, 
                error: function (data) {
                    console.log(data);
                }
            });
        ";

        echo Html::scriptBlock($jsPluginTTE);
    }
}
