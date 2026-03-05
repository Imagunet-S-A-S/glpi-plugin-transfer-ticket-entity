<?php

/**
 -------------------------------------------------------------------------
 LICENSE

 This file is part of Transferticketentity plugin for GLPI.

 Transferticketentity is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

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

namespace GlpiPlugin\Transferticketentity;

use CommonGLPI;
use Dropdown;
use Entity as GlpiEntity;
use Html;
use Session;

class Entity extends GlpiEntity
{
    /**
     * Return available ITIL categories for the given entity,
     * including recursive categories from ancestor entities.
     *
     * FIX: entity ID 0 (root) is valid — ancestors_cache may be NULL or empty
     * string in DB for root; handle both cases without discarding entity 0.
     *
     * @return array
     */
    public function availableCategories(): array
    {
        global $DB;

        $entity = (int) ($_REQUEST['id'] ?? 0);

        $allItilCategories = [0 => __('None')];

        $result = $DB->request([
            'FROM'  => 'glpi_entities',
            'WHERE' => ['id' => $entity],
        ]);

        $ancestorsEntities = [];

        foreach ($result as $data) {
            $cache = $data['ancestors_cache'] ?? '';
            if (!empty($cache) && $cache !== 'null') {
                $decoded = json_decode($cache, true);
                if (is_array($decoded)) {
                    $ancestorsEntities = array_keys($decoded);
                }
            }
            $ancestorsEntities[] = $entity;
        }

        if (empty($ancestorsEntities)) {
            $ancestorsEntities = [$entity];
        }

        $ancestorsEntities = array_unique($ancestorsEntities);

        foreach ($ancestorsEntities as $ancestorId) {
            $where = ['entities_id' => $ancestorId];
            if ($ancestorId !== $entity) {
                $where['is_recursive'] = 1;
            }

            $catResult = $DB->request([
                'FROM'  => 'glpi_itilcategories',
                'WHERE' => $where,
            ]);

            foreach ($catResult as $data) {
                $allItilCategories[$data['id']] = $data['completename'] ?? $data['name'];
            }
        }

        return $allItilCategories;
    }

    /**
     * If the profile is authorised, add an extra tab.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() === 'Entity') {
            return __('Transfer Ticket Entity', 'transferticketentity');
        }
        return '';
    }

    /**
     * Display tab content for entities.
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() === 'Entity') {
            $entity = new self();
            $ID     = $item->getField('id');
            $entity->showFormMcv((int) $ID);
        }
        return true;
    }

    /**
     * Read plugin settings for this entity from DB.
     * Returns defaults if no row exists yet (first time).
     */
    public function checkRights(int $ID): array
    {
        global $DB;

        $defaults = [
            'allow_entity_only_transfer' => 0,
            'justification_transfer'     => 0,
            'allow_transfer'             => 0,
            'keep_category'              => 0,
            'itilcategories_id'          => 0,
        ];

        $result = $DB->request([
            'FROM'  => 'glpi_plugin_transferticketentity_entities_settings',
            'WHERE' => ['entities_id' => $ID],
        ]);

        foreach ($result as $data) {
            return [
                'allow_entity_only_transfer' => (int) $data['allow_entity_only_transfer'],
                'justification_transfer'     => (int) $data['justification_transfer'],
                'allow_transfer'             => (int) $data['allow_transfer'],
                'keep_category'              => (int) $data['keep_category'],
                'itilcategories_id'          => (int) $data['itilcategories_id'],
            ];
        }

        return $defaults;
    }

    public static function addScript(): void
    {
        echo Html::script('/plugins/transferticketentity/public/js/entitySettings.js');
    }

    /**
     * Display the entity transfer settings form.
     * FIX: use $ID directly (integer), do not re-read from $_REQUEST to avoid
     * entity-0 being lost when PHP casts it implicitly to falsy.
     */
    public function showFormMcv(int $ID): void
    {
        global $CFG_GLPI;

        $checkRights         = $this->checkRights($ID);
        $availableCategories = $this->availableCategories();

        $formAction = $CFG_GLPI['root_doc'] . '/plugins/transferticketentity/front/entity.form.php';

        $tabId = urlencode(self::class . '$1');

        echo "<div class='firstbloc'>";

        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);

        if ($canedit) {
            echo "<form class='transferticketentity' method='post' action='" . htmlspecialchars($formAction) . "'>";
        }

        echo "<table class='tab_cadre_fixe'>";
        echo "<tbody>";
        echo "<tr>";
        echo "<th colspan='2'>" . __('Settings Transfer Ticket Entity', 'transferticketentity') . "</th>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td class='b'>" . __('Allow Transfer function', 'transferticketentity') . "</td>";
        echo "<td>";
        echo Dropdown::showYesNo(
            'allow_transfer',
            $checkRights['allow_transfer'],
            -1,
            ['display' => false, 'class' => 'allow_transfer form-select']
        );
        echo "</td></tr>";

        echo "<tr class='tab_bg_1' id='allow_entity_only_transfer'>";
        echo "<td class='b'>" . __('Assigned group required', 'transferticketentity') . "</td>";
        echo "<td>";
        echo Dropdown::showYesNo(
            'allow_entity_only_transfer',
            $checkRights['allow_entity_only_transfer'],
            -1,
            ['display' => false, 'class' => 'form-select']
        );
        echo "</td></tr>";

        echo "<tr class='tab_bg_1' id='justification_transfer'>";
        echo "<td class='b'>" . __('Justification required', 'transferticketentity') . "</td>";
        echo "<td>";
        echo Dropdown::showYesNo(
            'justification_transfer',
            $checkRights['justification_transfer'],
            -1,
            ['display' => false, 'class' => 'form-select']
        );
        echo "</td></tr>";

        echo "<tr class='tab_bg_1' id='keep_category'>";
        echo "<td class='b'>" . __('Keep category after transfer', 'transferticketentity') . "</td>";
        echo "<td>";
        echo Dropdown::showYesNo(
            'keep_category',
            $checkRights['keep_category'],
            -1,
            ['display' => false, 'class' => 'form-select']
        );
        echo "</td></tr>";

        echo "<tr class='tab_bg_1' id='itilcategories_id'>";
        echo "<td class='b'>" . __('Default category', 'transferticketentity') . "</td>";
        echo "<td>";
        Dropdown::showFromArray(
            'itilcategories_id',
            $availableCategories,
            [
                'value'   => $checkRights['itilcategories_id'],
                'class'   => 'itilcategories_id form-select',
                'display' => true,
            ]
        );
        echo "</td></tr>";

        echo "</tbody>";
        echo "</table>";

        if ($canedit) {
            echo Html::hidden('plugin_entity_id', ['value' => $ID]);
            echo Html::hidden('forcetab', ['value' => $tabId]);

            echo "<div class='center mt-2'>";
            echo Html::submit(_sx('button', 'Save'), ['name' => 'transfertticket', 'class' => 'btn btn-primary']);
            echo "</div>\n";
            Html::closeForm();
        }

        echo "</div>";

        self::addScript();
    }
}
