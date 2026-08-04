// task list page
agcg.taskListDelete = function(task_id){
    let confirm_delete = confirm(BX.message('ARTURGOLUBEV_CHATGPT_JS_DELETE_TASK_CONFIRM'));
    if(confirm_delete){
        agcg.deleteTask(task_id);
    }
}
agcg.taskEditDelete = function(task_id){
    let confirm_delete = confirm(BX.message('ARTURGOLUBEV_CHATGPT_JS_DELETE_TASK_CONFIRM'));
    if(confirm_delete){
        agcg.deleteTask(task_id, function(){
            document.location.href = '/bitrix/admin/arturgolubev_chatgpt_automatic_tasks.php?lang=ru';
        });
    }
}
    agcg.deleteTask = function(task_id, callback){
        BX.ajax.runAction('arturgolubev:chatgpt.Tasks.delete', {
            data: {'task_id': task_id}
        }).then(function (response) {
            data = response.data;
            console.log('Debug Controller=Tasks.delete', data);
            
            let grid = BX.Main.gridManager.getById('agcg_tasks_list');
            if (grid && grid.instance) {
                grid.instance.reloadTable();
            }

            if(typeof(callback) == 'function'){
                callback();
            }
        }, function (response) {
            alert(BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR"));
        });
	}

agcg.taskListCopy = function(task_id){
    agcg.copyTask(task_id, function(){
        document.location.reload();
    });
}
    agcg.copyTask = function(task_id){
        BX.ajax.runAction('arturgolubev:chatgpt.Tasks.copy', {
            data: {'task_id': task_id}
        }).then(function (response) {
            data = response.data;
            console.log('Debug Controller=Tasks.copy', data);
            
            let grid = BX.Main.gridManager.getById('agcg_tasks_list');
            if (grid && grid.instance) {
                grid.instance.reloadTable();
            }
        }, function (response) {
            alert(BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR"));
        });
	}

// task edit page
agcg.initTaskEditPage = function(params){
    let form = document.querySelector('.js-task-edit-form'), fields = document.querySelector('.js-task-edit-form-fields'), results = document.querySelector('.js-task-edit-form .results');
    if(form){
        agcg.getTaskContent(form, fields, results);
    }

    agcg.initTaskButtons(form, fields, results);
    agcg.initTabs();
}
    agcg.checkRequiredFields = function(fields, results){
        let requiredError = 0, requiredFields = fields.querySelectorAll('.js-required');
        if(requiredFields.length){
            requiredFields.forEach(function(item){
                if(!item.value){
                    requiredError = 1;
                }
            });

            if(requiredError){
                results.innerHTML = '<div class="error">' + BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_REQUIRED_ERROR") + '</div>';
                return 0;
            }
        }

        return 1;
    }
    agcg.initTaskButtons = function(form, fields, results){
        let btn_save = document.querySelector('.js-taskedit-save');
        if(btn_save){
            btn_save.addEventListener('click', function(e){
                if(!agcg.checkRequiredFields(fields, results)){
                    return;
                }

                BX.ajax({
                    method: 'POST',
                    url: '/bitrix/tools/arturgolubev.chatgpt/ajax.php',
                    data: agcg.getFormData(form)+'&sessid='+BX.bitrix_sessid(),
                    // dataType: "html",
                    dataType: 'json',
                    async: true,
                
                    processData: true,
                    scriptsRunFirst: false,
                    emulateOnload: false,
                    start: true,
                    cache: false,
                    
                    onsuccess: function(data){
                        // console.log('Debug Action=Save', data);
                        
                        if(data.error){
                            results.innerHTML = data.error_message;
                        }else{
                            if(data.action == 'tasks_new' && data.new_id){
                                document.location.href = '/bitrix/admin/arturgolubev_chatgpt_automatic_tasks.php?action=tasks_edit&id='+data.new_id+'&lang=ru';
                            }

                            if(data.action == 'tasks_edit'){
                                results.innerHTML = BX.message('ARTURGOLUBEV_CHATGPT_JS_SAVED');
                            }
                        }
                    },
                    onfailure: function(p1, p2){
                        alert(BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR"));
                    },
                });
            });
        }

        let btn_start = document.querySelector('.js-taskedit-start');
        if(btn_start){
            btn_start.addEventListener('click', function(e){
                if(!agcg.checkRequiredFields(fields, results)){
                    return;
                }

                BX.ajax({
                    method: 'POST',
                    url: '/bitrix/tools/arturgolubev.chatgpt/ajax.php',
                    data: agcg.getFormData(form)+'&sessid='+BX.bitrix_sessid(),
                    // dataType: "html",
                    dataType: 'json',
                    async: true,
                
                    processData: true,
                    scriptsRunFirst: false,
                    emulateOnload: false,
                    start: true,
                    cache: false,
                    
                    onsuccess: function(data){
                        // console.log('Debug Action=Save', data);
                        
                        if(data.error){
                            results.innerHTML = data.error_message;
                        }else{
                            let task_id = form.querySelector('input[name="id"]').value;
                            
                            BX.ajax.runAction('arturgolubev:chatgpt.Tasks.start', {
                                data: {'task_id': task_id}
                            }).then(function (response) {
                                data = response.data;
                                console.log('Debug Controller=Tasks.start', data);

                                if(data.error){
                                    results.innerHTML = data.error_message;
                                }else{
                                    document.location.reload();
                                }
                            }, function (response) {
                                alert(BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR"));
                            });
                        }
                    },
                    onfailure: function(p1, p2){
                        alert(BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR"));
                    },
                });
            });
        }

        let btn_restart = document.querySelector('.js-taskedit-restart');
        if(btn_restart){
            btn_restart.addEventListener('click', function(e){
                if(!agcg.checkRequiredFields(fields, results)){
                    return;
                }

                console.log('btn_restart', btn_restart);

                BX.ajax({
                    method: 'POST',
                    url: '/bitrix/tools/arturgolubev.chatgpt/ajax.php',
                    data: agcg.getFormData(form)+'&sessid='+BX.bitrix_sessid(),
                    // dataType: "html",
                    dataType: 'json',
                    async: true,
                
                    processData: true,
                    scriptsRunFirst: false,
                    emulateOnload: false,
                    start: true,
                    cache: false,
                    
                    onsuccess: function(data){
                        // console.log('Debug Action=Save', data);
                        
                        if(data.error){
                            results.innerHTML = data.error_message;
                        }else{
                            let task_id = form.querySelector('input[name="id"]').value;
                            
                            BX.ajax.runAction('arturgolubev:chatgpt.Tasks.restart', {
                                data: {'task_id': task_id}
                            }).then(function (response) {
                                data = response.data;
                                console.log('Debug Controller=Tasks.restart', data);

                                if(data.error){
                                    results.innerHTML = data.error_message;
                                }else{
                                    document.location.reload();
                                }
                            }, function (response) {
                                alert(BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR"));
                            });
                        }
                    },
                    onfailure: function(p1, p2){
                        alert(BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR"));
                    },
                });
            });
        }

        let btn_stop = document.querySelector('.js-taskedit-stop');
        if(btn_stop){
            btn_stop.addEventListener('click', function(e){
                let task_id = form.querySelector('input[name="id"]').value;
                
                BX.ajax.runAction('arturgolubev:chatgpt.Tasks.stop', {
                    data: {'task_id': task_id}
                }).then(function (response) {
                    data = response.data;
                    console.log('Debug Controller=Tasks.stop', data);

                    if(data.error){
                        results.innerHTML = data.error_message;
                    }else{
                        document.location.reload();
                    }

                }, function (response) {
                    alert(BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR"));
                });
            });
        }
    }

    agcg.initTabs = function(){
        const buttons = document.querySelectorAll('.agcg-tab-buttons button');
        const contents = document.querySelectorAll('.agcg-tab-content');

        buttons.forEach(button => {
        button.addEventListener('click', () => {
            buttons.forEach(btn => btn.classList.remove('agcg-active'));
            contents.forEach(tab => tab.classList.remove('agcg-active'));

            button.classList.add('agcg-active');
            document.getElementById(button.dataset.tab).classList.add('agcg-active');
        });
        });
    }

    agcg.getTaskContent = function(form, fieldsWrap, results){
        var sendData = agcg.getFormData(form);
        sendData = sendData + '&action=get_tasks_form_content';
    	sendData = sendData + '&sessid=' + BX.bitrix_sessid();
        
        BX.ajax({
            method: 'POST',
            url: '/bitrix/tools/arturgolubev.chatgpt/ajax.php',
            data: sendData,
            dataType: "html",
            async: true,
        
            processData: true,
            scriptsRunFirst: false,
            emulateOnload: false,
            start: true,
            cache: false,
            
            onsuccess: function(data){
                results.innerHTML = '';	
                fieldsWrap.innerHTML = data;	
                
                agcg.initAppendFormActions();
                
                var renew = document.querySelectorAll('.js-renew-form');
                if(renew.length){
                    renew.forEach(function(item){
                        item.addEventListener('change', function(){
                            agcg.getTaskContent(form, fieldsWrap, results);
                        });
                    });
                }
            },
            onfailure: function(p1, p2){
                alert(BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR"));
            },
        });
    }

// add window
agcg.initAddWindow = function(params){
	// console.log('initAddWindow', params);
	
	if(params.eids.length && params.sids.length){
		agcg.simple_info(BX.message("ARTURGOLUBEV_CHATGPT_JS_ADD_WINDOW_TITLE"), BX.message("ARTURGOLUBEV_CHATGPT_JS_MASS_WINDOW_SECTION_ELEMENT_ERROR"));
	}else if(params.eids.length > 10000 && params.limits == 'Y'){
		agcg.simple_info(BX.message("ARTURGOLUBEV_CHATGPT_JS_ADD_WINDOW_TITLE"), BX.message("ARTURGOLUBEV_CHATGPT_JS_MASS_WINDOW_SECTION_ELEMENT_MAX_COUNT"));
	}else{
		if(params.eids.length){
			params.entity = 'elements';
			agcg.addWindowShow(params, params.eids);
		}
		
		if(params.sids.length){
			params.entity = 'sections';
			agcg.addWindowShow(params, params.sids);
		}
	}
};
    agcg.addWindowShow = function(params, elements){
        // console.log('addWindowShow', params, elements);
        
        var wTitle = BX.message('ARTURGOLUBEV_CHATGPT_JS_ADD_WINDOW_TITLE'), wContent = '', wButtons = [];
        
        if(params.demo){
            wContent += '<div style="font-size: 13px; color: #333;">'+BX.message('ARTURGOLUBEV_CHATGPT_JS_DEMO_NOTIFY')+' ' + params.dcount + '. ' + BX.message('ARTURGOLUBEV_CHATGPT_JS_DEMO_NOTIFY_CONTINUE') +'</div><br/>';
        }
        
        wContent += '<div style="font-size: 13px; color: #333;">'+BX.message('ARTURGOLUBEV_CHATGPT_JS_BACKUP_NOTIFY')+'</div><br/>';
        
        wContent += '<div style="font-size: 13px; color: #333;">';
            if(params.action_all_rows){
                wContent += BX.message('ARTURGOLUBEV_CHATGPT_JS_MASS_WINDOW_ACTION_ALL_ROWS');
            }
            
            if(params.entity == 'elements'){
                wContent += BX.message('ARTURGOLUBEV_CHATGPT_JS_ADD_WINDOW_SELECTED_ELEMENTS');
            }else{
                wContent += BX.message('ARTURGOLUBEV_CHATGPT_JS_ADD_WINDOW_SELECTED_SECTIONS');
            }
            
            
            wContent += ' (' +elements.length+ ') :';
            
            for (var key in elements) {
                if(parseInt(key) > 0){
                    wContent += ', ';
                }else{
                    wContent += ' ';
                }
                
                wContent += elements[key];
            }
            
            wContent += '</div>';
        wContent += '</div><br/>';
   
        wButtons[0] = new BX.PopupWindowButton({
            text: BX.message("ARTURGOLUBEV_CHATGPT_JS_ADD_WINDOW_BUTTON_ADD"),
            className: "main-grid-buttons apply",
            events: {click: function(){
                agcg.addElementsToTask(params, elements);
            }}
        })

        wButtons[1] = new BX.PopupWindowButton({
            text: BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_BUTTON_CLOSE"),
            className: "main-grid-buttons",
            events: {click: function(){
                this.popupWindow.close();
                this.popupWindow.destroy();
            }}
        })
        
        BX.ajax({
            method: 'POST',
            url: '/bitrix/tools/arturgolubev.chatgpt/ajax.php',
            data: {action: 'tasks_addlist', ibid: params.ibid, entity: params.entity, sessid: BX.bitrix_sessid()},
            dataType: 'json',
            async: true,
        
            processData: true,
            scriptsRunFirst: false,
            emulateOnload: false,
            start: true,
            cache: false,
            
            onsuccess: function(data){
                wContent += '<div>';
                    wContent += '<div class="agcg-element-form-title">'+BX.message('ARTURGOLUBEV_CHATGPT_JS_ADD_WINDOW_FIELD_SELECT_TASK')+'</div>';
                    wContent += '<div class="agcg-element-form-title"><select class="js-task-select">';
                        wContent += '<option value="new">'+BX.message('ARTURGOLUBEV_CHATGPT_JS_ADD_WINDOW_FIELD_SELECT_TASK_NEW')+'</option>';
                        if(data.list.length){
                            data.list.forEach(function(item){
                                wContent += '<option value="'+item.ID+'">'+item.UF_NAME+'</option>';
                            });
                        }
                    wContent += '</select></div>';
                wContent += '</div>';

                wContent += '<div class="results js-task-element-add-result"></div>';

                var agcg_element_window = new BX.PopupWindow("agcg_element_window", null, {
                    overlay: {backgroundColor: 'black', opacity: '80' },
                    draggable: false,
                    zIndex: 1,
                    closeIcon : false,
                    closeByEsc : true,
                    lightShadow : false,
                    autoHide : false,
                    className : 'agcg_simple_window',
                    
                    content: wContent,
                    titleBar: wTitle,
                    offsetTop : 1,
                    offsetLeft : 0,
                    buttons: wButtons
                });
                agcg_element_window.show();

                agcg.calcWindowStyles();
            },
            onfailure: function(p1, p2){
                alert(BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR"));
            },
        });
    }
    agcg.addElementsToTask = function(params, elements){
		// console.log('addElementsToTask', params, elements);
		
        let task_id = document.querySelector('.js-task-select').value, results = document.querySelector('.js-task-element-add-result');
        
        BX.ajax({
            method: 'POST',
            url: '/bitrix/tools/arturgolubev.chatgpt/ajax.php',
            data: {action: 'tasks_add_elements', task_id: task_id, params: params, elements: elements, sessid: BX.bitrix_sessid()},
            dataType: 'json',
            async: true,
        
            processData: true,
            scriptsRunFirst: false,
            emulateOnload: false,
            start: true,
            cache: false,
            
            onsuccess: function(data){
                // console.log('Debug Action=Add To Task', data);

                if(data.error){
                    results.innerHTML = data.error_message;
                }else{
                    let result_text = BX.message('ARTURGOLUBEV_CHATGPT_JS_ADD_WINDOW_RESULT_ADD_SUCCESS');

                    if(!!data.counts.new_task_id){
                        result_text += BX.message('ARTURGOLUBEV_CHATGPT_JS_ADD_WINDOW_RESULT_ADD_SUCCESS_NEW_TASK').replace('#id#', data.counts.new_task_id);
                    }

                    result_text += BX.message('ARTURGOLUBEV_CHATGPT_JS_ADD_WINDOW_RESULT_ADD_SUCCESS_ADEDDED').replace('#cnt#', data.counts.add);
                    
                    if(data.counts.skip){
                        result_text += BX.message('ARTURGOLUBEV_CHATGPT_JS_ADD_WINDOW_RESULT_ADD_SUCCESS_SKIP').replace('#cnt#', data.counts.skip);
                    }

                    if(data.counts.task_id){
                        result_text += BX.message('ARTURGOLUBEV_CHATGPT_JS_ADD_WINDOW_RESULT_ADD_SUCCESS_OPEN_TASK').replace('#id#', data.counts.task_id);
                    }

                    results.innerHTML = result_text;
                }
            },
            onfailure: function(p1, p2){
                alert(BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR"));
            },
        });
	}

/* task elements */
agcg.showTaskElementInfoWindow = function(task_element_id){
    BX.ajax.runAction('arturgolubev:chatgpt.TaskElements.getInfo', {
        data: {'element_id': task_element_id}
    }).then(function (response) {
        data = response.data;
        console.log('Debug Controller=TaskElements.getInfo', data);

        var wTitle = BX.message('ARTURGOLUBEV_CHATGPT_JS_TASK_ELEMENT_MORE_WINDOW_TITLE') + ' #' + task_element_id, wContent = '', wButtons = [];
    
        if(data.error){
            wContent += '<div>'+data.error_message+'</div>';
        }else{
            wContent += '<div>'+BX.message('ARTURGOLUBEV_CHATGPT_JS_TASK_ELEMENT_MORE_FIELD_UF_ELEMENT')+': '+data.info.UF_ELEMENT+'</div>';
            wContent += '<div style="margin-top: 10px;">'+BX.message('ARTURGOLUBEV_CHATGPT_JS_TASK_ELEMENT_MORE_FIELD_UF_STATUS')+': '+BX.message('ARTURGOLUBEV_CHATGPT_JS_TASK_ELEMENT_MORE_FIELD_UF_STATUS_'+data.info.UF_STATUS)+'</div>';

            if(data.info.UF_STATUS == 'error' || data.info.UF_STATUS == 'skip'){
                wContent += '<div>'+data.info.UF_PARAMS.error_message+' ['+data.info.UF_PARAMS.error_type+']'+'</div>';
            }

            if(data.info.UF_GENERATION_DATE){
                wContent += '<div style="margin-top: 10px;">'+BX.message('ARTURGOLUBEV_CHATGPT_JS_TASK_ELEMENT_MORE_FIELD_UF_GENERATION_DATE')+': '+data.info.UF_GENERATION_DATE+'</div>';
            }

            if(data.info.UF_GENERATION_RESULT){
                wContent += '<div style="margin-top: 10px;">'+BX.message('ARTURGOLUBEV_CHATGPT_JS_TASK_ELEMENT_MORE_FIELD_UF_VALUE_BACKUP')+': <div class="tasks-generation-info-data">'+BX.util.htmlspecialchars(data.info.UF_VALUE_BACKUP)+'</div></div>';
                wContent += '<div style="margin-top: 10px;"><b>'+BX.message('ARTURGOLUBEV_CHATGPT_JS_TASK_ELEMENT_MORE_FIELD_UF_GENERATION_RESULT')+':</b> <div class="tasks-generation-info-data">'+BX.util.htmlspecialchars(data.info.UF_GENERATION_RESULT)+'</div></div>';
            }
        }
            
        wButtons[0] = new BX.PopupWindowButton({
            text: BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_BUTTON_CLOSE"),
            className: "main-grid-buttons",
            events: {click: function(){
                this.popupWindow.close();
                this.popupWindow.destroy();
            }}
        })

        var agcg_element_window = new BX.PopupWindow("agcg_element_window", null, {
            overlay: {backgroundColor: 'black', opacity: '80' },
            draggable: false,
            zIndex: 1,
            closeIcon : false,
            closeByEsc : true,
            lightShadow : false,
            autoHide : false,
            className : 'agcg_simple_window',
            
            content: wContent,
            titleBar: wTitle,
            offsetTop : 1,
            offsetLeft : 0,
            buttons: wButtons
        });
        agcg_element_window.show();

        agcg.calcWindowStyles();
    }, function (response) {
        alert(BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR"));
    });
}

agcg.deleteTaskElementConfirm = function(task_element_id){
    let confirm_delete = confirm(BX.message('ARTURGOLUBEV_CHATGPT_JS_DELETE_TASK_ELEMENT_CONFIRM'));
    if(confirm_delete){
        agcg.deleteTaskElement(task_element_id);
    }
}
    agcg.deleteTaskElement = function(task_element_id){
        BX.ajax.runAction('arturgolubev:chatgpt.TaskElements.deleteFromTask', {
            data: {'element_id': task_element_id}
        }).then(function (response) {
            let grid = BX.Main.gridManager.getById('agcg_task_elements_list');
            if (grid && grid.instance) {
                grid.instance.reloadTable();
            }
        }, function (response) {
            alert(BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR"));
        });
	}
agcg.readdTaskElement = function(task_element_id){
    BX.ajax.runAction('arturgolubev:chatgpt.TaskElements.readdElement', {
        data: {'element_id': task_element_id}
    }).then(function (response) {
        let grid = BX.Main.gridManager.getById('agcg_task_elements_list');
        if (grid && grid.instance) {
            grid.instance.reloadTable();
        }
    }, function (response) {
        alert(BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR"));
    });
}
agcg.returnDataTaskElement = function(task_element_id){
    BX.ajax.runAction('arturgolubev:chatgpt.TaskElements.returnDataTaskElement', {
        data: {'element_id': task_element_id}
    }).then(function (response) {
        let grid = BX.Main.gridManager.getById('agcg_task_elements_list');
        if (grid && grid.instance) {
            grid.instance.reloadTable();
        }
    }, function (response) {
        alert(BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR"));
    });
}