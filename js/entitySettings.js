/**
 * TransferTicketEntity — entitySettings.js
 * Controls visibility of entity settings fields based on "Allow Transfer" toggle.
 *
 * GLPI 11 / Tabler: Dropdowns are rendered by Dropdown::showYesNo() which in GLPI 11
 * produces a <select> element (may be enhanced by Select2). We select by the
 * <select name="..."> attribute instead of fragile DOM-tree traversal.
 *
 * @copyright 2026 IMAGUNET S.A.S
 */

(function () {
    'use strict';

    /**
     * Read the current value of the allow_transfer select.
     * Returns "1" when enabled, "0" when disabled.
     */
    function getAllowTransferValue() {
        var sel = document.querySelector('select[name="allow_transfer"]');
        return sel ? sel.value : '0';
    }

    /**
     * Reset a Yes/No select to "No" (value = 0).
     * Works whether or not Select2 has been applied.
     */
    function resetYesNoSelect(name) {
        var sel = document.querySelector('select[name="' + name + '"]');
        if (!sel) return;
        sel.value = '0';
        // Notify Select2 (if active) so its display updates
        if (typeof $ !== 'undefined' && $(sel).data('select2')) {
            $(sel).trigger('change.select2');
        }
    }

    /**
     * Show or hide dependent rows and reset their selects when hiding.
     */
    function displayValue() {
        var enabled = getAllowTransferValue() === '1';

        var dependents = [
            'allow_entity_only_transfer',
            'justification_transfer',
            'keep_category',
            'itilcategories_id',
        ];

        dependents.forEach(function (rowId) {
            var row = document.getElementById(rowId);
            if (!row) return;

            if (enabled) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
                // Reset the inner select to No / None when hiding
                if (rowId !== 'itilcategories_id') {
                    resetYesNoSelect(rowId);
                }
            }
        });

        // itilcategories_id row visibility also depends on keep_category value
        if (enabled) {
            updateCategoryRowVisibility();
        }
    }

    /**
     * Show/hide the default category row based on keep_category value.
     * Category row only makes sense when "Keep category" is enabled.
     */
    function updateCategoryRowVisibility() {
        var keepCatSel = document.querySelector('select[name="keep_category"]');
        var catRow     = document.getElementById('itilcategories_id');
        if (!keepCatSel || !catRow) return;

        // Show the default-category row only when keep_category = 0
        // (i.e. category will NOT be kept → we need to specify a fallback category)
        catRow.style.display = (keepCatSel.value === '0') ? '' : 'none';
    }

    // ── Bootstrap: run once on load, then on any change ────────────────────

    // GLPI 11 may load scripts deferred; use DOMContentLoaded as safety net
    function init() {
        displayValue();

        // Listen on the whole form for any change (allow_transfer, keep_category, …)
        var form = document.querySelector('.transferticketentity');
        if (form) {
            form.addEventListener('change', function (event) {
                displayValue();
            });
        }

        // If Select2 is active, it fires a custom 'select2:select' event
        if (typeof $ !== 'undefined') {
            $(document).on('select2:select select2:unselect', 'select[name="allow_transfer"], select[name="keep_category"]', function () {
                displayValue();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
