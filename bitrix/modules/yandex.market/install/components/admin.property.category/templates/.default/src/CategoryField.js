import type {Transport} from "./Component/Transport";
import type {Locale} from "./Component/Locale";

const $ = window.YMarketJQuery || window.jQuery;

export class CategoryField {

    static defaults = {
		selectElement: 'select',
	    parentElement: '[data-entity="parentCategory"]',
	    copyElement: '[data-entity="copy"]',
    }

	_bindSearchPaste = false;
    _lastError;

    constructor(element: HTMLSelectElement, transport: Transport, locale: Locale, options: Object = {}) {
        this.$el = $(element);
        this.el = element;
        this.options = Object.assign({}, this.constructor.defaults, options);
        this.transport = transport;
        this.locale = locale;

	    this.bind();
        this.bootSelect();
    }

	destroy() : void {
		this.unbind();
		this.destroySelect();
	}

	bind() {
		this.handleSelectOpen(true);
		this.handleSelectClose(true);
		this.handleCopy(true);
	}

	unbind() {
		this.handleSelectOpen(false);
		this.handleSelectClose(false);
		this.handleSearchPaste(false);
		this.handleCopy(false);
	}

	handleChange(dir: boolean, callback: () => {}) : void {
		this.selectElement()[dir ? 'on' : 'off']('change', callback);
	}

	handleCopy(dir: boolean) : void {
		this.$el.find(this.options.copyElement)[dir ? 'on' : 'off']('click', this.onCopyClick);
	}

	handleSelectOpen(dir: boolean) : void {
		this.selectElement()[dir ? 'on' : 'off']('select2:open', this.onSelectOpen);
	}

	handleSelectClose(dir: boolean) : void {
		this.selectElement()[dir ? 'on' : 'off']('select2:closing', this.onSelectClosing);
	}

	handleSearchPaste(dir: boolean) : void {
		if (this._bindSearchPaste === dir) { return; }

		const search = $('.select2-search__field');

		search[dir ? 'on' : 'off']('paste', this.onSearchPaste);
		this._bindSearchPaste = dir;
	}

	onCopyClick = () : void => {
		this.copyClipboard(this.value() || this.parentValue());
	}

	onSelectOpen = () : void => {
		this.handleSearchPaste(true);
	}

	onSelectClosing = () : void => {
		this.handleSearchPaste(false);
	}

	onSearchPaste = (e) : void => {
		const paste = (e.originalEvent.clipboardData || window.clipboardData).getData('text/plain').toString();
		const lines = paste.split(/(\n\r|\n|\r)/gm).map((line) => line.trim()).filter((line) => line !== '');

		if (paste.indexOf(' / ') !== -1 || lines.length < 2) { return; }

		e.target.value = lines.join(' / ');
		e.preventDefault();

		$(e.target).trigger('input');
	}

    bootSelect() : void {
        this.selectElement().select2(Object.assign({}, {
	        width: '100%',
            minimumInputLength: 2,
            selectOnClose: true,
	        allowClear: true,
            ajax: {
                cache: true,
                delay: 1000,
                transport: this.ajaxTransport,
            },
            templateSelection: this.templateSelection,
        }, this.getLanguageOptions()));
    }

	destroySelect() : void {
		this.selectElement().select2('destroy');
	}

    getLanguageOptions() : Object {
        return {
            placeholder: this.parentValue() || this.locale.message('CATEGORY_PLACEHOLDER'),
            language: Object.assign(this.languageDefaults(), {
                errorLoading: () => {
                    if (this._lastError != null) {
                        const error = this._lastError;
                        this._lastError = null;

                        return error;
                    }

                    return this.locale.message('CATEGORY_LOAD_ERROR');
                },
            }),
        }
    }

    languageDefaults() : Object {
        try {
            // noinspection JSUnresolvedReference
	        return $.fn.select2.amd.require(`select2/i18n/${this.locale.language()}`)
        } catch (e) {
            console.error(e);
            return {};
        }
    }

    ajaxTransport = (params: Object, success: () => {}, failure: () => {}) : void => {
	    if (/.+\/.+\[\d+]/.test(params.data.q)) {
		    success(this.prepareResults([params.data.q]));
		    return;
	    }

        this.transport.fetch('categories', {
            query: params.data.q,
            language: this.locale.language(),
        })
            .then((data) => {
                success(this.prepareResults(data));
            })
            .catch((error: Error) => {
                this._lastError = error.message;
                failure();
            });

        return {};
    }

	prepareResults(variants: string[]) {
		return {
			results: variants.map((variant: string) => {
				return {id: variant, text: variant};
			}),
		};
	}

    templateSelection = (variant: Object) : string => {
        return variant.id === '' ? variant.text : variant.id;
    }

	copyClipboard(text: string) : void {
		const fake = $('<textarea></textarea>').css({
			position: 'absolute',
			left: '-9999px',
			top: '-9999px',
		});

		fake.insertAfter(this.$el);
		fake.val(text);
		fake[0].select();

		this.execCommand('copy');

		fake.remove();
	}

	execCommand(command: string) : boolean {
		try {
			// noinspection JSDeprecatedSymbols
			document.execCommand(command);
			return true;
		} catch (err) {
			console.log(err);
			return false;
		}
	}

	selectElement() {
		return this.$el.find(this.options.selectElement);
	}

	resetParent(category: string) : void {
		const placeholder = category || this.locale.message('CATEGORY_PLACEHOLDER');

		this.$el.find(this.options.parentElement).val(category || '');

		this.$el.find('.select2-selection__rendered').attr('title', placeholder)
		this.$el.find('.select2-selection__placeholder').text(placeholder);
	}

	parentValue() : string {
		return this.$el.find(this.options.parentElement).val();
	}

	value() : ?string {
		return this.selectElement().val();
	}
}