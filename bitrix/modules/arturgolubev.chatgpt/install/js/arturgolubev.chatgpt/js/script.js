var agcg = BX.namespace("agcg");

// simple
agcg.getFormData = function(form){
	return new URLSearchParams(new FormData(form)).toString();
}

agcg.elementWindowAjaxEnd = function(results, message, htmlclass){
	results.innerHTML = '<div class="' + htmlclass + '">' + message + '</div>';
	agcg.removeAllLoaders();
}

agcg.removeAllLoaders = function(){
	loaders = document.querySelectorAll('.lds-dual-ring');
	if(loaders.length){
		loaders.forEach(function(item){
			item.remove();
		});
	}
	
	counters = document.querySelectorAll('.timed-counter');
	if(counters.length){
		counters.forEach(function(item){
			item.remove();
		});
	}
}
agcg.getFormContent = function(entity, form, fieldsWrap, results, masswork){
	var sendData = agcg.getFormData(form);
	
	if(entity == 'element' || entity == 'elements'){
		sendData = sendData + '&action=get_element_form_content';
	}else{
		sendData = sendData + '&action=get_section_form_content';
	}

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
			// console.log(data);
			results.innerHTML = '';	
			fieldsWrap.innerHTML = data;	
			
			agcg.initAppendFormActions();
			
			var renew = document.querySelectorAll('.js-renew-form');
			if(renew.length){
				renew.forEach(function(item){
					item.addEventListener('change', function(){
						agcg.getFormContent(entity, form, fieldsWrap, results, masswork);
					});
				});
			}
		},
		onfailure: function(p1, p2){
			var message = BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR");
					
			/* if(typeof(p2) == 'object'){
				if(!!p2.data){
					message = message + '<br><br>' + p2.data;
				}
			} */
			
			agcg.elementWindowAjaxEnd(results, message, 'error');
		},
	});
}

agcg.initAppendFormActions = function (){
	var appendButtons = document.querySelectorAll('.js-open-append');
	if(appendButtons.length){
		appendButtons.forEach(function(item){
			item.addEventListener('mouseover', function(){
				var list = item.querySelector('.append-list');
				list.classList.add('active');
			});
			item.addEventListener('mouseout', function(){
				var list = item.querySelector('.append-list');
				list.classList.remove('active');
			});
		});
	}
	
	var appendMacros = document.querySelectorAll('.js-add-append');
	if(appendMacros.length){
		appendMacros.forEach(function(item){
			item.addEventListener('click', function(){
				var txtarea = item.closest('td').querySelector('textarea');
				
				var start = txtarea.selectionStart, end = txtarea.selectionEnd,
				finText = txtarea.value.substring(0, start) + this.dataset.value + txtarea.value.substring(end);
				
				txtarea.value = finText;
				txtarea.focus();
				txtarea.selectionEnd = (start == end) ? (end + this.dataset.value.length) : start + this.dataset.value.length;
			});
		});
	}

	/* var savedTemplate = document.querySelectorAll('.js-saved-template');
	if(savedTemplate.length){
		savedTemplate.forEach(function(item){
			item.addEventListener('click', function(){
				var t = this, textarea = t.closest('table').querySelector('textarea'), templateVal = t.querySelector('.js-saved-template-value').innerHTML;

				if(templateVal){
					textarea.value = templateVal;
				}
			});
		});
	} */
};

agcg.simple_info = function(title, data){
	var agoz_alert = new BX.PopupWindow("agcg_simple_alert", null, {
		overlay: {backgroundColor: 'black', opacity: '80' },
		draggable: false,
		zIndex: 1,
		closeIcon : false,
		closeByEsc : true,
		lightShadow : false,
		autoHide : false,
		className: "agcg_simple_window",
		content: data,
		titleBar: title,
		offsetTop : 1,
		offsetLeft : 0,
		buttons: [
			new BX.PopupWindowButton({
				text: BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_BUTTON_CLOSE"),
				className: "webform-button-link-cancel",
				events: {click: function(){
					this.popupWindow.close();
					this.popupWindow.destroy();
				}}
			})
		]
	});
	agoz_alert.show();
}

// events
agcg.initMassWork = function(params){
	console.log('initMassWork', params);
	
	if(params.eids.length && params.sids.length){
		agcg.simple_info(BX.message("ARTURGOLUBEV_CHATGPT_JS_MASS_WINDOW_TITLE"), BX.message("ARTURGOLUBEV_CHATGPT_JS_MASS_WINDOW_SECTION_ELEMENT_ERROR"));
	}else if(params.eids.length > 10000 && params.limits == 'Y'){
		agcg.simple_info(BX.message("ARTURGOLUBEV_CHATGPT_JS_MASS_WINDOW_TITLE"), BX.message("ARTURGOLUBEV_CHATGPT_JS_MASS_WINDOW_SECTION_ELEMENT_MAX_COUNT"));
	}else{
		if(params.eids.length){
			params.entity = 'elements';
			agcg.massWindowShow(params, params.eids);
		}
		
		if(params.sids.length){
			params.entity = 'sections';
			agcg.massWindowShow(params, params.sids);
		}
	}
};

agcg.initElementButton = function(params){
	// console.log('initElementButton', params);
	document.addEventListener("DOMContentLoaded", function(){
		var panels = document.querySelectorAll('.adm-detail-toolbar-right');
		if(panels.length){
			panels.forEach(function(item){
				var btnHtml = '<a href="javascript:void(0);" class="adm-btn adm-btn-green" id="agcg_element_window_get" onClick="agcg.elementWindowShow(' + params.IBLOCK_ID + ', ' + params.ID + ', \''+params.ENTITY_TYPE+'\');">' + BX.message('ARTURGOLUBEV_CHATGPT_JS_ELEMENT_BUTTON'); + '</a>';
				item.insertAdjacentHTML('beforeend', btnHtml);
			});
		}
	});
};

// page
agcg.initImagePage = function(){
	var examples = document.querySelectorAll('.agcg-example-query');
	if(examples.length){
		examples.forEach(function(item){
			item.addEventListener('click', function(){
				var t = this, area = document.querySelector('.js-image-area');
				area.innerHTML = t.innerHTML;
			});
		});
	}

	var sendBtn = document.querySelector('.js-image-send');
	sendBtn.addEventListener('click', function(){
		agcg.requestImagePage(this, {
			'first': 1,
			'keynum': 0,
			'renew': 0,
		});
	});
}
agcg.requestImagePage = function(_this, options){
	// console.log('requestImagePage', options);

	var button = _this, form = document.querySelector('.js-image-form'), result = document.querySelector('.js-image-result'), area = document.querySelector('.js-image-area');
	
	if(!area.value){
		result.innerHTML = BX.message('ARTURGOLUBEV_CHATGPT_JS_ASK_EMPTY_REQUEST');
		return;
	}
	
	if(button.classList.contains('work')){
		result.innerHTML = BX.message('ARTURGOLUBEV_CHATGPT_JS_ASK_IN_USE');
		return;
	}
	
	button.classList.add("work");
	button.insertAdjacentHTML('beforeend', '<span class="lds-dual-ring"></span>');
	
	if(options.first)
		result.innerHTML = BX.message('ARTURGOLUBEV_CHATGPT_JS_ASK_GENERATE_STARTED');
	
	BX.ajax.runAction('arturgolubev:chatgpt.Simple.imageRequest', {
		data: {'data': Object.fromEntries(new FormData(form))}
	}).then(function (response) {
		data = response.data;
		console.log('Debug Controller=Simple.imageRequest', data);
		
		button.classList.remove("work");
		agcg.removeAllLoaders();

		if(data.error){
			result.innerHTML = data.error_message;
		}else{
			let newHtml = '<img src="'+data.image+'" alt="" style="max-width: 100%;" /><br><br><a target="_blank" href="'+data.image+'">'+BX.message("ARTURGOLUBEV_CHATGPT_JS_IMAGE_IN_NEWTAB")+'</a>';
			result.innerHTML = newHtml;
		}
	}, function (response) {
		button.classList.remove("work");
		agcg.removeAllLoaders();

		result.innerHTML = BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR");
	});
};


agcg.initAskPage = function(){
	var examples = document.querySelectorAll('.agcg-example-query');
	if(examples.length){
		examples.forEach(function(item){
			item.addEventListener('click', function(){
				var t = this, area = document.querySelector('.js-ask-area');
				area.innerHTML = t.innerHTML;
			});
		});
	}
	
	var sendBtn = document.querySelector('.js-ask-send');
	sendBtn.addEventListener('click', function(){
		agcg.requestAskPage(this, {
			'first': 1,
			'keynum': 0,
			'renew': 0,
		});
	});
};
agcg.requestAskPage = function(_this, options){
	// console.log('requestAskPage', options);

	var button = _this, form = document.querySelector('.js-ask-form'), result = document.querySelector('.js-ask-result'), area = document.querySelector('.js-ask-area');
	
	if(!area.value){
		result.innerHTML = BX.message('ARTURGOLUBEV_CHATGPT_JS_ASK_EMPTY_REQUEST');
		return;
	}
	
	if(button.classList.contains('work')){
		result.innerHTML = BX.message('ARTURGOLUBEV_CHATGPT_JS_ASK_IN_USE');
		return;
	}
	
	button.classList.add("work");
	button.insertAdjacentHTML('beforeend', '<span class="lds-dual-ring"></span>');
	
	if(options.first)
		result.innerHTML = BX.message('ARTURGOLUBEV_CHATGPT_JS_ASK_GENERATE_STARTED');
	
	BX.ajax.runAction('arturgolubev:chatgpt.Simple.textRequest', {
		data: {'data': Object.fromEntries(new FormData(form))}
	}).then(function (response) {
		data = response.data;
		console.log('Debug Controller=Simple.textRequest', data);
		
		button.classList.remove("work");
		agcg.removeAllLoaders();

		if(data.error){
			result.innerHTML = data.error_message;
		}else{
			result.innerHTML = '<textarea>' + data.text + '</textarea><br><br>';
		}
	}, function (response) {
		button.classList.remove("work");
		agcg.removeAllLoaders();

		result.innerHTML = BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR");
	});
};

// masswork
agcg.massWindowShow = function(params, elements){
	console.log('massWindowShow', params, elements);
	
	var wTitle = BX.message('ARTURGOLUBEV_CHATGPT_JS_MASS_WINDOW_TITLE'), wContent = '', wButtons = [];
	
	if(params.demo){
		wContent = wContent + '<div style="font-size: 13px;">'+BX.message('ARTURGOLUBEV_CHATGPT_JS_DEMO_NOTIFY')+' ' + params.dcount + '. ' + BX.message('ARTURGOLUBEV_CHATGPT_JS_DEMO_NOTIFY_CONTINUE') +'</div><br/>';
	}
	
	wContent = wContent + '<div style="font-size: 13px;">'+BX.message('ARTURGOLUBEV_CHATGPT_JS_BACKUP_NOTIFY')+'</div><br/>';
	
	wContent = wContent + '<div style="font-size: 13px;">';
		if(params.action_all_rows){
			wContent = wContent + BX.message('ARTURGOLUBEV_CHATGPT_JS_MASS_WINDOW_ACTION_ALL_ROWS');
		}
		
		if(params.entity == 'elements'){
			wContent = wContent + BX.message('ARTURGOLUBEV_CHATGPT_JS_MASS_WINDOW_SELECTED_ELEMENTS').replace('#num#', elements.length);
		}else{
			wContent = wContent + BX.message('ARTURGOLUBEV_CHATGPT_JS_MASS_WINDOW_SELECTED_SECTIONS').replace('#num#', elements.length);
		}
		
		for (var key in elements) {
			if(parseInt(key) > 0){
				wContent = wContent + ', ';
			}else{
				wContent = wContent + ' ';
			}
			
			wContent = wContent + elements[key];
		}
		
		wContent = wContent + '</div>';
	wContent = wContent + '</div><br/>';
	
	wContent = wContent + agcg.massWindowShow_getBaseForm(params.ibid);
	
	wButtons[0] = new BX.PopupWindowButton({
		text: BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_BUTTON_START"),
		className: "main-grid-buttons apply",
		events: {click: function(){
			agcg.massGenerateStart(this, params, elements);
		}}
	})

	wButtons[1] = new BX.PopupWindowButton({
		text: BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_BUTTON_PREVIEW"),
		className: "main-grid-buttons apply",
		events: {click: function(){
			var paramsPreview = {
				'IBLOCK_ID': params.ibid,
				'ELEMENT_ID': elements[0],
				'ENTITY': params.entity,
				'REQUEST_TYPE': 'preview',
			};

			console.log('paramsPreview', paramsPreview);
			agcg.requestIblockWindow(this, 0, paramsPreview);
		}}
	})

	wButtons[2] = new BX.PopupWindowButton({
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

	agcg.getFormContent(params.entity, document.querySelector('.agcg-element-form'), document.querySelector('.agcg-element-form-fields'), document.querySelector('.agcg-element-form .results'), 1);
}
	agcg.massGenerateStart = function(_this, params, elements){
		// console.log('massGenerateStart', _this, params, elements);
		
		params.keynum = 0;
		params.used_tokens_cnt = 0;
		
		params.base_elements = elements.slice(0);

		params.full_count = params.base_elements.length;
		
		params.nodes = {};
		params.nodes.startButton = _this.buttonNode;
		params.nodes.results = document.querySelector('.agcg-element-form .results');
		params.nodes.form = document.querySelector('.agcg-element-form');
		params.nodes.provider = document.querySelector('.agcg-element-form select[name=provider]');
		
		params.use_count = 0;
		params.use_sleep = (elements.length > 3 ? 1 : 0);
		params.skip_sleep = 1;
		
		if(params.nodes.startButton.classList.contains('work')){
			return;
		}
		
		agcg.logClear(params.nodes.results);
		
		var requiredError = 0, requiredFields = document.querySelectorAll('.agcg-element-form .js-required');
		if(requiredFields.length){
			requiredFields.forEach(function(item){
				if(!item.value){
					requiredError = 1;
				}
			});
			
			if(requiredError){
				params.nodes.results.innerHTML = '<div class="error">' + BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_REQUIRED_ERROR") + '</div>';
				return;
			}
		}
		
		params.nodes.startButton.classList.add("work");
		params.nodes.startButton.insertAdjacentHTML('beforeend', '<span class="lds-dual-ring"></span><span class="timed-counter" style="margin-left: 15px; display: inline-block;"></span>');
		
		agcg.logAddLine(params.nodes.results, '<div class="agcg-logtitle">' + BX.message("ARTURGOLUBEV_CHATGPT_JS_MASS_LOG_SUBTITLE") + '</div>');
		agcg.logAddLine(params.nodes.results, BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_GENERATE_STARTED"));
		
		agcg.massGenerateProcess(params);
	}
	agcg.massGenerateProcess = function(params){
		// console.log('massGenerateProcess', params);
		
		if(!document.querySelector('#agcg_element_window')){
			console.log('Stop generate By Close');
			return false; // stop on close window
		}

		var callback = function(){
			if(params.base_elements.length){
				params.skip_sleep = 1;
				
				var counter = document.querySelector('.timed-counter');
				if(counter){
					counter.innerHTML = '(' + (params.full_count - params.base_elements.length + 1) + BX.message('ARTURGOLUBEV_CHATGPT_JS_MASS_WORK_OF') + params.full_count+')';
				}

				var backup_eid = params.base_elements.slice(0), eid = params.base_elements.shift(), urlCreate;
				
				agcg.logAddLine(params.nodes.results, '<br><div id="log_title_'+eid+'">' + BX.message('ARTURGOLUBEV_CHATGPT_JS_MASS_WORK_WITH_ID') + '[' + eid + ']</div>');
				
				if(params.entity == 'elements'){
					urlCreate = agcg.getFormData(params.nodes.form) + '&action=mass_create_elements&eid='+eid;
				}else{
					urlCreate = agcg.getFormData(params.nodes.form) + '&action=mass_create_sections&eid='+eid;
				}

				urlCreate = urlCreate + '&sessid=' + BX.bitrix_sessid();

				let startTime = Date.now();

				BX.ajax({
					method: 'POST',
					url: '/bitrix/tools/arturgolubev.chatgpt/ajax.php',
					data: urlCreate,
					// dataType: "html",
					dataType: 'json',
					
					async: true,
					processData: true,
					scriptsRunFirst: false,
					emulateOnload: false,
					start: true,
					cache: false,
					
					onsuccess: function(data){
						params.use_count = params.use_count + 1;
						params.show_tokens = data.show_tokens;
						
						if(data.used_tokens_cnt){
							params.used_tokens_cnt = params.used_tokens_cnt + data.used_tokens_cnt;
						}

						if(params.nodes.provider.length){
							var provider = params.nodes.provider.value;

							if(provider == 'chatgpt'){
								if(!!data.gpt_model && data.gpt_model == 'gpt-3.5-turbo'){
									params.skip_sleep = 0;
								}
							}
						}

						if(!document.querySelector('#agcg_element_window')){
							console.log('Stop generate By Close');
							return false; // stop on close window
						}

						// console.log('data', data);
						// return; //todo
						
						if(data.error){
							params.skip_sleep = 1;

							if(false){ // data.next_key
								params.keynum = params.keynum + 1;
								params.base_elements = backup_eid;
								
								agcg.logAddLine(params.nodes.results, BX.message('ARTURGOLUBEV_CHATGPT_JS_ERROR') + data.error_message + '. ' + BX.message("ARTURGOLUBEV_CHATGPT_JS_NEXT_KEY_INFO") + ' ['+ (params.keynum+2) + ']');
							}else{
								agcg.logAddLine(params.nodes.results, BX.message('ARTURGOLUBEV_CHATGPT_JS_ERROR') + data.error_message);
							}
						}else{
							// agcg.logAddLine(params.nodes.results, 'Q: ' + data.question);
							// agcg.logAddLine(params.nodes.results, 'A: ' + data.answer);
							agcg.logAddLine(params.nodes.results, BX.message('ARTURGOLUBEV_CHATGPT_JS_MASS_GENERATE_AND_SAVE_SUCCESS'));
						}
						
						if(!!data.element_name){
							var logTitle = document.querySelector('#log_title_'+eid);
							logTitle.innerHTML = BX.message('ARTURGOLUBEV_CHATGPT_JS_MASS_WORK_WITH_ID') + data.element_name + ' [' + eid + ']';
						}

						agcg.massGenerateProcess(params);
					},
					onfailure: function(p1, p2){
						var message = BX.message("ARTURGOLUBEV_CHATGPT_JS_AJAX_ERROR");
			
						let duration = (Date.now() - startTime) / 1000;
						message = message + ' (Time: '+ duration + ' s)';

						if(typeof(p2) == 'object'){
							if(!!p2.data){
								message = message + '<br>' + p2.data;
							}
						}

						agcg.logAddLine(params.nodes.results, message);

						agcg.massGenerateProcess(params);
						
						// agcg.elementWindowAjaxEnd(results, BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR"), 'error');
					},
				});
			}else{
				agcg.massGenerateEnd(params);
			}
		}
		
		if(params.use_sleep && params.use_count > 0 && params.base_elements.length > 0 && !params.skip_sleep){
			BX.ajax({
				method: 'POST',
				url: '/bitrix/tools/arturgolubev.chatgpt/ajax.php?action=sleep20',
				data: {sessid: BX.bitrix_sessid()},
				dataType: 'json',
				timeout: 30,
				async: true,
				processData: true,
				scriptsRunFirst: false,
				emulateOnload: false,
				start: true,
				cache: false,
				onsuccess: function(data){
					callback();
					agcg.logTmpClear();
				},
				onfailure: function(){
					callback();
					agcg.logTmpClear();
				},
			});
			
			agcg.logAddTmpLine(params.nodes.results, BX.message('ARTURGOLUBEV_CHATGPT_JS_MASS_GENERATE_WAITING'));
		}else{
			callback();
		}
	}
	
	agcg.massGenerateEnd = function(params){
		// console.log('massGenerateEnd', params);
		
		agcg.logAddLine(params.nodes.results, '<br>' + BX.message('ARTURGOLUBEV_CHATGPT_JS_MASS_WINDOW_GENERATE_END'));
		
		if(params.show_tokens && params.used_tokens_cnt){
			agcg.logAddLine(params.nodes.results, '<br>' + BX.message('ARTURGOLUBEV_CHATGPT_JS_MASS_WINDOW_USED_TOKENS') + params.used_tokens_cnt);
		}
		
		params.nodes.startButton.classList.remove("work");
		agcg.removeAllLoaders();
	}
	agcg.logAddLine = function(log, linetext){
		log.insertAdjacentHTML('beforeend', '<div class="agcg-logline">'+linetext+'</div>');
		log.scrollTop = 99999;
	}
	agcg.logAddTmpLine = function(log, linetext){
		log.insertAdjacentHTML('beforeend', '<div class="agcg-logline agcg-logline-temp">'+linetext+'</div>');
		log.scrollTop = 99999;
	}
	agcg.logTmpClear = function(){
		var lines = document.querySelectorAll('.agcg-logline-temp');
		if(lines.length){
			lines.forEach(function(item){
				item.remove();
			});
		}
	}
	agcg.logClear = function(log){
		log.innerHTML = '';
	}
	
	agcg.massWindowShow_getBaseForm = function(IBLOCK_ID){
		var html = '';
		
		html = html + '<form class="agcg-element-form" spellcheck="false">';
			html = html + '<input type="hidden" name="MASS" value="Y" />';
			html = html + '<input type="hidden" name="IBLOCK_ID" value="'+IBLOCK_ID+'" />';
			
			html = html + '<div class="agcg-element-form-title">'+BX.message('ARTURGOLUBEV_CHATGPT_JS_MASS_WINDOW_FORM_SUBTITLE')+'</div>';
			html = html + '<div class="agcg-element-form-fields">';
				html = html + '<div class=""><span class="lds-dual-ring"></span></div>';
			html = html + '</div>';
			
			html = html + '<div class="results"></div>';
		html = html + '</form>';
		
		return html
	};

// element
agcg.elementWindowShow = function(IBLOCK_ID, ELEMENT_ID, ENTITY){
	// console.log('elementWindowShow', IBLOCK_ID, ELEMENT_ID, ENTITY);
	
	var wTitle = BX.message('ARTURGOLUBEV_CHATGPT_JS_ELEMENT_WINDOW_TITLE'), wContent = agcg.elementWindowShow_getBaseForm(IBLOCK_ID, ELEMENT_ID), wButtons = [];
	
	wButtons[0] = new BX.PopupWindowButton({
		text: BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_BUTTON_START"),
		className: "main-grid-buttons apply",
		events: {
			click: function(){
				var params = {
					'IBLOCK_ID': IBLOCK_ID,
					'ELEMENT_ID': ELEMENT_ID,
					'ENTITY': ENTITY,
				};
				agcg.requestIblockWindow(this, 0, params);
			}
		}
	})
	wButtons[1] = new BX.PopupWindowButton({
		text: BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_BUTTON_PREVIEW"),
		className: "main-grid-buttons apply",
		events: {
			click: function(){
				var params = {
					'IBLOCK_ID': IBLOCK_ID,
					'ELEMENT_ID': ELEMENT_ID,
					'ENTITY': ENTITY,
					'REQUEST_TYPE': 'preview',
				};
				agcg.requestIblockWindow(this, 0, params);
			}
		}
	})
	wButtons[2] = new BX.PopupWindowButton({
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
	
	agcg.getFormContent(ENTITY, document.querySelector('.agcg-element-form'), document.querySelector('.agcg-element-form-fields'), document.querySelector('.agcg-element-form .results'), 0);
};

agcg.calcWindowStyles = function(){
	var winContent = document.querySelectorAll('.agcg_simple_window .popup-window-content');
	if(winContent.length){
		var maxHeight = window.innerHeight * 0.8 - 49 - 54;
		winContent.forEach(function(item){
			item.style.maxHeight = maxHeight + 'px';
		});
	}
};

agcg.requestIblockWindow = function(_this, keynum, params){
	var startButton = _this.buttonNode, results = document.querySelector('.agcg-element-form .results'), form = document.querySelector('.agcg-element-form'), urlCreate, urlSave;
	
	if(startButton.classList.contains('work')){
		return;
	}

	var preview_mode = (!!params.REQUEST_TYPE && params.REQUEST_TYPE == 'preview');
	
	var requiredError = 0, requiredFields = document.querySelectorAll('.agcg-element-form .js-required');
	if(requiredFields.length){
		requiredFields.forEach(function(item){
			if(!item.value){
				requiredError = 1;
			}
		});
		
		if(requiredError){
			results.innerHTML = '<div class="error">' + BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_REQUIRED_ERROR") + '</div>';
			return;
		}
	}
	
	startButton.classList.add("work");
	startButton.insertAdjacentHTML('beforeend', '<span class="lds-dual-ring"></span>');
	
	results.innerHTML = BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_GENERATE_STARTED");
	
	if(params.ENTITY == 'element' || params.ENTITY == 'elements'){
		urlCreate = agcg.getFormData(form) + '&action=create_element_text';
	}else{
		urlCreate = agcg.getFormData(form) + '&action=create_section_text';
	}
	
	if(preview_mode){
		urlCreate = urlCreate + '&preview=1&ID='+params.ELEMENT_ID;
	}

	urlCreate = urlCreate + '&sessid=' + BX.bitrix_sessid();
	
	let startTime = Date.now();
	
	BX.ajax({
		method: 'POST',
		url: '/bitrix/tools/arturgolubev.chatgpt/ajax.php',
		data: urlCreate,
		// dataType: "html",
		dataType: 'json',
		
		async: true,
		processData: true,
		scriptsRunFirst: false,
		emulateOnload: false,
		start: true,
		cache: false,
		
		onsuccess: function(data){
			console.log(data);
			/* startButton.classList.remove("work");
			agcg.removeAllLoaders();
			return; //todo */
			
			var html = '';
			
			if(preview_mode){
				html = '<b>' + BX.message("ARTURGOLUBEV_CHATGPT_JS_PREVIEW_REQUEST") + '</b><br><br>' + data.question;

				if(data.files_vals){
					html += '<br>Files: ' + data.files_vals;
				}

				results.innerHTML = html;
			}else{
				if(data.error){
					if(false){ // data.next_key
						startButton.classList.remove("work");
						agcg.removeAllLoaders();
						
						results.innerHTML = results.innerHTML + '<br>' + data.error_message + '. ' + BX.message("ARTURGOLUBEV_CHATGPT_JS_NEXT_KEY_INFO") + ' ['+ (keynum+2) + ']';
						agcg.requestIblockWindow(_this, keynum + 1, params);
					}else{
						if(!!data.question && data.question_show){
							html = html + '<div class="result-textareas"><div class="label">' + BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_GENERATE_SUCCESS_QUESTION") + '</div><div>' + data.question + '</div></div>';
						}
						
						html = html + '<div class="error">'+data.error_message+'</div>';
						
						results.innerHTML = results.innerHTML + '<br>' + html;
					}
				}else{
					html = '<div style="margin-bottom: 5px; color: green;">' + BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_GENERATE_SUCCESS") + '</div>';
					
					// html = html + '<div class="result-textareas"><div class="label">' + BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_GENERATE_SUCCESS_QUESTION") + '</div><textarea name="question">' + data.question + '</textarea></div>';
					
					if(data.question_show){
						html = html + '<div class="result-textareas"><div class="label">' + BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_GENERATE_SUCCESS_QUESTION") + '</div><div>' + data.question + '</div></div>';
					}

					if(data.show_tokens && data.used_tokens_cnt){
						html = html + '<div class="result-textareas"><div>' + data.used_tokens + '</div></div>';
					}

					if(data.content_type == 'image'){
						html = html + '<div class="result-textareas"><div class="label">' + BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_GENERATE_SUCCESS_RESULT") + '</div><input type="hidden" name="genresult" value="' + data.answer + '"><img src="' + data.answer + '" alt="" style="max-width: 100%;" /></div>';
					}else{
						html = html + '<div class="result-textareas"><div class="label">' + BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_GENERATE_SUCCESS_RESULT") + '</div><textarea name="genresult" rows="10">' + data.answer + '</textarea></div>';
					}
					
					html = html + '<div class="result-buttons">';
						html = html + '<div class="label">' + BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_GENERATE_SAVE_ANSWER") + '</div>';
						html = html + '<select name="savefield">';
							for (i in data.save_fields){
								if(data.save_fields[i].length){
									if(i != 'no_name'){
										html = html + '<optgroup label="'+ BX.message("ARTURGOLUBEV_CHATGPT_FORM_ELEMENT_GROUP_"+i) +'">';
									}
										data.save_fields[i].forEach(function(item){
											if(!!item.DEFAULT){
												html = html + '<option value="' + item.CODE + '" selected>' + item.NAME + '</option>';
											}else{
												html = html + '<option value="' + item.CODE + '">' + item.NAME + '</option>';
											}
										});
									if(i != 'no_name'){
										html = html + '</optgroup>';
									}
								}
							}
						html = html + '</select>';
						html = html + '<div class="result-button result-button-save">' + BX.message("ARTURGOLUBEV_CHATGPT_JS_SAVE") + '</div>';
					html = html + '</div>';
					
					results.innerHTML = html;
					
					document.querySelector('.result-button-save').addEventListener('click', function(){	
						if(params.ENTITY == 'element'){
							urlSave = agcg.getFormData(form) + '&action=save_answer_to_element';
						}else{
							urlSave = agcg.getFormData(form) + '&action=save_answer_to_section';
						}

						urlSave = urlSave + '&sessid=' + BX.bitrix_sessid();

						var savefield = document.querySelector('select[name="savefield"]');
						if(!savefield.value){
							document.querySelector('.result-buttons').insertAdjacentHTML('beforeend', '<div class="error">' + BX.message("ARTURGOLUBEV_CHATGPT_JS_EMPTY_SAVE_FIELD") + '</div>');
							return false;
						}
					
						BX.ajax({
							method: 'POST',
							url: '/bitrix/tools/arturgolubev.chatgpt/ajax.php',
							data: urlSave,
							// dataType: "html",
							dataType: 'json',
							
							async: true,
							processData: true,
							scriptsRunFirst: false,
							emulateOnload: false,
							start: true,
							cache: false,
							
							onsuccess: function(data){
								console.log(data);
								// return; // todo
								
								if(data.error){
									results.innerHTML = '<div class="error">' + data.error_message + '</div>';
								}else{
									if(data.savefield_type == 'section_uf'){
										var setInput = document.querySelectorAll("[name=" + data.savefield  + "]");
										if(setInput.length){
											setInput[0].value = data.genresult;
										}
									}
									
									if(data.savefield_type == 'property'){
										var setInput = document.querySelectorAll("#tr_PROPERTY_" + data.savefield_id  + " input[type=text]");
										if(setInput.length){
											setInput[0].value = data.genresult;
										}
									}
									
									if(data.savefield_type == 'property_html'){
										var setInput = document.querySelectorAll("#tr_PROPERTY_" + data.savefield_id  + " textarea");
										if(setInput.length){
											setInput[0].value = data.genresult;
											
											if(typeof onChangeInputType == "function"){
												onChangeInputType(setInput[0].getAttribute('id').substr(5));
											}
										}
									}
									
									if(data.savefield_type == 'seo'){
										var setInput = document.querySelectorAll("#IPROPERTY_TEMPLATES_" + data.savefield);
										var setCheck = document.querySelectorAll("#ck_IPROPERTY_TEMPLATES_" + data.savefield);
										if(setInput.length){
											setInput[0].value = data.genresult;
											
											if(setCheck.length){
												if(!setCheck[0].checked){
													setCheck[0].click(); 
												}
											}
										}
									}
									
									if(data.savefield_type == 'field'){
										var setInput = document.querySelectorAll("#bxed_" + data.savefield);
										if(setInput.length){
											setInput[0].value = data.genresult;
											
											if(typeof onChangeInputType == "function"){
												onChangeInputType(data.savefield);
											}
										}else{
											setInput = document.querySelectorAll("#" + data.savefield);
											if(setInput.length){
												setInput[0].value = data.genresult;
											}
										}
									}
									
									results.innerHTML = BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_GENERATE_ANSWER_SAVED");
								}
							},
							onfailure: function(p1, p2){	
								// return; // todo
								var message = BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR");
										
								/* if(typeof(p2) == 'object'){
									if(!!p2.data){
										message = message + '<br><br>' + p2.data;
									}
								} */
								
								agcg.elementWindowAjaxEnd(results, message, 'error');
							},
						});
					});
				}
			}
			
			startButton.classList.remove("work");
			agcg.removeAllLoaders();
		},
		onfailure: function(p1, p2){
			startButton.classList.remove("work");
			

			var message = BX.message("ARTURGOLUBEV_CHATGPT_JS_ELEMENT_AJAX_ERROR");

			let duration = (Date.now() - startTime) / 1000;
			message = message + ' (Time: '+ duration + ' s)';
			
			if(typeof(p2) == 'object'){
				if(!!p2.data){
					message = message + '<br>' + p2.data;
				}
			}
			
			agcg.elementWindowAjaxEnd(results, message, 'error');
		},
	});
};

agcg.pasteTemplate = function(data){
	document.querySelector('textarea.js-button-area').value = data;
}

agcg.pasteMacro = function(macros){
	var txtarea = document.querySelector('textarea.js-button-area');
	
	var start = txtarea.selectionStart, end = txtarea.selectionEnd,
	finText = txtarea.value.substring(0, start) + macros + txtarea.value.substring(end);
	
	txtarea.value = finText;
	txtarea.focus();
	txtarea.selectionEnd = (start == end) ? (end + macros.length) : start + macros.length;
}

agcg.elementWindowShow_getBaseForm = function(IBLOCK_ID, ELEMENT_ID){
	var html = '';
	
	html = html + '<form class="agcg-element-form" spellcheck="false">';
		html = html + '<input type="hidden" name="IBLOCK_ID" value="'+IBLOCK_ID+'" />';
		html = html + '<input type="hidden" name="ID" value="'+ELEMENT_ID+'" />';
		
		html = html + '<div class="agcg-element-form-fields">';
			html = html + '<div class=""><span class="lds-dual-ring"></span></div>';
		html = html + '</div>';
		
		html = html + '<div class="results"></div>';
	html = html + '</form>';
	
	return html
};