$.ajax({
    url: CFG_GLPI.root_doc + '/plugins/transferticketentity/ajax/getEntitiesRights.php',
    method: "GET",
    success: function (data) {
        if (typeof data === 'string') {
            data = JSON.parse(data);
        }

        if (document.querySelector('.tt_entity_choice') != null) {
            $('#entity_choice').select2();
            $('#group_choice').select2();

            document.querySelector('#tt_gest_error').style.display='none';
            document.querySelector('.form_transfert').style.display='block';
            document.querySelector('#nogroupfound').style.display = 'none';

            let entity_choice = document.querySelector('#entity_choice')
            let tt_group_choice = document.querySelector('.tt_group_choice')
            let tt_btn_open_modal_form = document.querySelector('#tt_btn_open_modal_form')
            
            const clone_all_groups = document.querySelectorAll('#group_choice option')
            let all_groups = []

            let all_groups_unchoice = document.querySelectorAll('#group_choice option')
            all_groups_unchoice.forEach(function(all_group_unchoice) {
                all_group_unchoice.remove()
            })

            $('#entity_choice').on('change', function (event) {
                if (entity_choice.value == '') {
                    tt_group_choice.style.display = 'none'
                    tt_btn_open_modal_form.disabled = true
                    tt_btn_open_modal_form.classList.remove('btn-warning')
                    tt_btn_open_modal_form.classList.add('btn-secondary')
                } else {
                    tt_group_choice.style.display = 'flex'
                    document.querySelector('#div_confirmation').style.display = 'block'
                    tt_btn_open_modal_form.disabled = true
                    tt_btn_open_modal_form.classList.remove('btn-warning')
                    tt_btn_open_modal_form.classList.add('btn-secondary')
                }
            })

            $('#entity_choice').on('change', function (event) {
                all_groups = []
                all_groups = clone_all_groups

                let entityRights = data.filter(e => e.entities_id == entity_choice.value)
                let justificationRight = entityRights[0]['justification_transfer']
                let groupRight = entityRights[0]['allow_entity_only_transfer']
                let categoryRight = entityRights[0]['keep_category']

                if (categoryRight) {
                    document.querySelector('.adv-msg').style.display='';
                } else {
                    document.querySelector('.adv-msg').style.display='none';
                }

                if (justificationRight == 1) {
                    document.querySelector('#justification').required = true;
                } else {
                    document.querySelector('#justification').required = false;
                }

                all_groups.forEach(function(all_group) {
                    if (groupRight == 1 && all_group.id != 'tt_none') {
                        all_group.remove();
                    }
                    if ('tt_plugin_entity_' + entity_choice.value == all_group.className || all_group.className == 'tt_plugin_entity_0' || all_group.value == '') {
                        if (groupRight == 1 && all_group.id != 'tt_none') {
                            document.querySelector('#group_choice').appendChild(all_group)
                        } else if (groupRight == 0) {
                            document.querySelector('#group_choice').appendChild(all_group)
                        } else {
                            all_group.remove();
                        }
                    } else {
                        all_group.remove()
                    }
                })

                if (groupRight == 0) {
                    document.querySelector('#nogroupfound').style.display = 'none';
                    document.querySelector('.tt_flex').style.display = '';
                } else {
                    if (document.querySelector('#group_choice')[1].id == 'tt_none'){
                        document.querySelector('#nogroupfound').style.display = '';
                        document.querySelector('.tt_flex').style.display = 'none';
                    }
                }

                document.querySelector('#no_select').selected = true
            })

            $('#group_choice').on('change', function (event) {
                if(document.querySelector('#no_select') !== null) {
                    if (document.querySelector('#no_select').selected == true) {
                        tt_btn_open_modal_form.disabled = true
                        tt_btn_open_modal_form.classList.remove('btn-warning')
                        tt_btn_open_modal_form.classList.add('btn-secondary')
                    } else {
                        tt_btn_open_modal_form.disabled = false
                        tt_btn_open_modal_form.classList.remove('btn-secondary')
                        tt_btn_open_modal_form.classList.add('btn-warning')
                    }
                }
            })
        }
    }, 
    error: function (data) {
        console.log(data);
    }
});