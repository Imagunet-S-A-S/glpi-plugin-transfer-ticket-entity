/**
 * TransferTicketEntity — profileSettings.js
 * Enforces dependency between the "Use" right and the "Bypass" right:
 *  - If "Use" is unchecked, "Bypass" must also be unchecked.
 *  - If "Bypass" is checked, "Use" must also be checked.
 *
 * FIX: The original code used a hardcoded element name suffix "[31_0]" which
 * corresponds to ALLSTANDARDRIGHT = 31 combined with the profile row index 0.
 * This is fragile — the suffix can change with GLPI versions or if ALLSTANDARDRIGHT
 * changes. We now locate checkboxes by searching all inputs whose name CONTAINS
 * the right key, making the code robust to GLPI 11 profile matrix rendering.
 *
 * @copyright 2026 IMAGUNET S.A.S
 */

(function () {
    'use strict';

    /**
     * Find the "active" (ALLSTANDARDRIGHT) checkbox for a given right field name.
     * GLPI 11 renders profile matrix checkboxes as:
     *   <input type="checkbox" name="_plugin_transferticketentity_use[31_0]" ...>
     * The suffix [31_0] = [ALLSTANDARDRIGHT_rowIndex].
     * We match any input whose name starts with "_<fieldName>[" to be version-safe.
     */
    function findRightCheckbox(fieldName) {
        // Inputs whose name attribute starts with _<fieldName>[ — picks the active right checkbox
        var selector = 'input[type="checkbox"][name^="_' + fieldName + '["]';
        var inputs   = document.querySelectorAll(selector);
        // Return the last match — GLPI profile matrix may render multiple rows
        // (one per profile or per right value); we want the "active" checkbox (ALLSTANDARDRIGHT)
        return inputs.length > 0 ? inputs[inputs.length - 1] : null;
    }

    function init() {
        var useRight    = findRightCheckbox('plugin_transferticketentity_use');
        var bypassRight = findRightCheckbox('plugin_transferticketentity_bypass');

        if (!useRight || !bypassRight) {
            // Rights checkboxes not present on this page — nothing to wire up
            return;
        }

        useRight.addEventListener('change', function () {
            if (!useRight.checked) {
                bypassRight.checked = false;
            }
        });

        bypassRight.addEventListener('change', function () {
            if (bypassRight.checked) {
                useRight.checked = true;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
