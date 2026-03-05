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
 * @author    Santiago Gomez <santiago.gomez@imagunet.com>
 * @author    Juan Gallego <juan.gallego@imagunet.com>
 @copyright 2026 IMAGUNET S.A.S
 @license   AGPL License 3.0 or (at your option) any later version
            https://www.gnu.org/licenses/gpl-3.0.html
 @link      https://www.imagunet.com
 --------------------------------------------------------------------------
*/

namespace GlpiPlugin\Transferticketentity;

use CommonGLPI;
use Html;
use Profile as GlpiProfile;
use ProfileRight;
use Session;

class Profile extends GlpiProfile
{
    static $rightname = "profile";

    static function getAllRights()
    {
        $rights = [
            [
                'itemtype'  => 'PluginTransferTicketEntityUse',
                'label'     => __('Authorized entity transfer', 'transferticketentity'),
                'field'     => 'plugin_transferticketentity_use',
                'rights'    => [ALLSTANDARDRIGHT => __('Active', 'transferticketentity')]
            ],
            [
                'itemtype'  => 'PluginTransferTicketEntityBypass',
                'label'     => __('Transfer authorized without assignment of technician or associated group', 'transferticketentity'),
                'field'     => 'plugin_transferticketentity_bypass',
                'rights'    => [ALLSTANDARDRIGHT => __('Active', 'transferticketentity')]
            ]
        ];

        return $rights;
    }

    /**
     * Add an additional tab
     *
     * @param CommonGLPI $item         Item
     * @param int        $withtemplate 0
     *
     * @return string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == 'Profile') {
            return __("Transfer Ticket Entity", "transferticketentity");
        }

        return '';
    }

    /**
     * Get profiles authorised to use entity transfer
     *
     * @return array $allProfiles
     */
    public static function canUseProfiles()
    {
        global $DB;

        $result = $DB->request([
            'SELECT' => ['profiles_id'],
            'FROM' => 'glpi_profilerights',
            'WHERE' => ['name' => 'plugin_transferticketentity_use', 'NOT' => ['rights' => 0]],
            'ORDER' => 'name ASC'
        ]);

        $array = [];

        foreach ($result as $data) {
            $array[] = $data['profiles_id'];
        }

        return $array;
    }

    /**
     * Display tab content for profiles
     *
     * @param CommonGLPI $item         Item
     * @param int        $tabnum       1
     * @param int        $withtemplate 0
     *
     * @return true
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == 'Profile') {
            $profile = new self();
            $ID = $item->getField('id');
            $profile->showFormMcv($ID);
        }

        return true;
    }

    /**
     * @param int   $profiles_id Profile ID
     * @param array $rights      Rights
     */
    static function addDefaultProfileInfos($profiles_id, $rights)
    {
        $profileRight = new ProfileRight();

        foreach ($rights as $right => $value) {
            if (!countElementsInTable(
                'glpi_profilerights',
                ['profiles_id' => $profiles_id, 'name' => $right]
            )) {
                $myright['profiles_id'] = $profiles_id;
                $myright['name']        = $right;
                $myright['rights']      = $value;
                $profileRight->add($myright);

                // Add right to the current session
                $_SESSION['glpiactiveprofile'][$right] = $value;
            }
        }
    }

    /**
     * @param int $profiles_id Profile ID
     */
    static function createFirstAccess($profiles_id)
    {
        foreach (self::getAllRights() as $right) {
            self::addDefaultProfileInfos(
                $profiles_id,
                ['plugin_transferticketentity_use' => ALLSTANDARDRIGHT]
            );
        }
    }

    public static function addScript()
    {
        echo Html::script("/plugins/transferticketentity/public/js/profileSettings.js");
    }

    /**
     * Display the plugin configuration form
     *
     * @param int $ID id
     *
     * @return void
     */
    public function showFormMcv($ID)
    {
        echo "<div class='firstbloc'>";

        if ($canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE])) {
            $profile = new GlpiProfile();
            echo "<form method='post' action='" . $profile->getFormURL() . "' class='transferticketentity'>";
        }

        $profile = new GlpiProfile();
        $profile->getFromDB($ID);

        $rights = self::getAllRights();

        $profile->displayRightsChoiceMatrix(
            $rights,
            [
                'canedit'       => $canedit,
                'default_class' => 'tab_bg_2',
                'title'         => __('General')
            ]
        );

        if ($canedit) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $ID]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
            echo "</div>\n";
            Html::closeForm();
        }

        echo "</div>";

        self::addScript();
    }
}
