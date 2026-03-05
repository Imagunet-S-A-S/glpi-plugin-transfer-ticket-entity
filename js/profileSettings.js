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
        var selector = 'input[type="checkbox"][name^="_' + fieldName + '["]';
        var inputs   = document.querySelectorAll(selector);
        return inputs.length > 0 ? inputs[inputs.length - 1] : null;
    }

    function init() {
        var useRight    = findRightCheckbox('plugin_transferticketentity_use');
        var bypassRight = findRightCheckbox('plugin_transferticketentity_bypass');

        if (!useRight || !bypassRight) {
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
