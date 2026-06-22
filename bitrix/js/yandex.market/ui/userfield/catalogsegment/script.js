(function(BX, $) {

    const Plugin = BX.namespace('YandexMarket.Plugin');
    const UserField = BX.namespace('YandexMarket.Ui.UserField');

    UserField.CatalogSegment = Plugin.Base.extend({

        defaults: {
            enableElement: '.ym-catalog-segment__enable input[type="checkbox"]',
	        paramElement: '.b-param-table',
        },

        initialize: function() {
            this.bind();
        },

        destroy: function() {
            this.unbind();
        },

        bind: function() {
            this.handleEnableChange(true);
        },

        unbind: function() {
            this.handleEnableChange(false);
        },

        handleEnableChange: function(dir) {
            this.getElement('enable')[dir ? 'on' : 'off']('change.uiCatalogSegment', $.proxy(this.onEnableChange, this));
        },

        onEnableChange: function(evt) {
            const enabled = evt.currentTarget.checked;

			this.syncEnable(evt.currentTarget, enabled);
            this.toggleDisabled(!enabled);
        },

	    syncEnable: function(checkbox, checked) {
			if (checkbox.form == null) { return; }

			for (const sibling of checkbox.form.querySelectorAll(`input[name="${checkbox.name}"]`)) {
				if (sibling === checkbox || sibling.checked === checked) { continue; }

				sibling.checked = checked;
				$(sibling).triggerHandler('change.uiCatalogSegment');
			}
	    },

        toggleDisabled: function(disabled) {
	        const param = Plugin.manager.getInstance(this.getElement('param'));

            this.$el.toggleClass('is--disabled', disabled);
	        param[disabled ? 'disable' : 'enable']();
        }

    }, {
        dataName: 'uiUserFieldCatalogSegment'
    });

})(BX, jQuery);