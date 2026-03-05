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
                if (rowId !== 'itilcategories_id') {
                    resetYesNoSelect(rowId);
                }
            }
        });
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
        catRow.style.display = (keepCatSel.value === '0') ? '' : 'none';
    }

    function init() {
        displayValue();

        var form = document.querySelector('.transferticketentity');
        if (form) {
            form.addEventListener('change', function (event) {
                displayValue();
            });
        }

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
