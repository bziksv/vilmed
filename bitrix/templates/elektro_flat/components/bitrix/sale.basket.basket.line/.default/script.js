function BitrixSmallCart(){}

BitrixSmallCart.prototype = {
	activate: function() {
		this.cartElement = BX(this.cartId);

		this.setCartBodyClosure = this.closure("setCartBody");
		BX.addCustomEvent(window, "OnBasketChange", this.closure("refreshCart", {}));

		if (!this.cartElement || !this.cartElement.querySelector(".cart")) {
			var self = this;
			var run = function() {
				self.loadFromBasketLine();
			};
			if (window.requestIdleCallback) {
				requestIdleCallback(run, {timeout: 2500});
			} else {
				window.addEventListener("load", run, {once: true});
			}
		}
	},

	closure: function(fname, data) {
		var obj = this;
		return data
			? function(){obj[fname](data)}
			: function(arg1){obj[fname](arg1)};
	},

	loadFromBasketLine: function() {
		BX.ajax({
			url: "/ajax/basket_line.php",
			method: "GET",
			dataType: "html",
			onsuccess: BX.proxy(this.setCartBodyFromBasketLine, this)
		});
	},

	setCartBodyFromBasketLine: function(result) {
		if (!this.cartElement || !result || !String(result).replace(/\s/g, "")) {
			return;
		}

		var wrapper = document.createElement("div");
		wrapper.innerHTML = result;
		var source = wrapper.querySelector("#" + this.cartId) || wrapper.querySelector(".cart_line");
		if (!source) {
			return;
		}

		this.cartElement.innerHTML = source.innerHTML;
	},

	refreshCart: function(data) {
		data.sessid = BX.bitrix_sessid();
		data.siteId = this.siteId;
		data.templateName = this.templateName;
		data.arParams = this.arParams;
		BX.ajax({
			url: this.ajaxPath,
			method: "POST",
			dataType: "html",
			data: data,
			onsuccess: BX.proxy(function(result) {
				if (!result || !String(result).replace(/\s/g, "")) {
					this.loadFromBasketLine();
					return;
				}
				this.setCartBody(result);
			}, this)
		});
	},

	setCartBody: function(result) {
		if (!result || !String(result).replace(/\s/g, "")) {
			return;
		}

		if (typeof jQuery === "undefined") {
			this.setCartBodyFromBasketLine(result);
			return;
		}

		var basketCont = jQuery(this.cartElement);

		if (!basketCont.find(".cart").length) {
			var inner = jQuery(result).find(".cart_line").html();
			basketCont.html(inner || result);
			return;
		}

		basketCont.find(".qnt").text(jQuery(result).find(".qnt").text());

		basketCont.find(".sum").data("decimal", jQuery(result).find(".sum").data("decimal"));

		var sumOld = basketCont.find(".sum").data("sum");
		basketCont.find(".sum").data("sum", jQuery(result).find(".sum").data("sum"));
		var sumCurr = basketCont.find(".sum").data("sum");

		if (sumCurr != sumOld) {
			var options = {
				useEasing: false,
				useGrouping: true,
				separator: basketCont.find(".sum").data("separator"),
				decimal: basketCont.find(".sum").data("dec-point")
			};
			vilmedAnimateCountUp("cartCounter", sumOld, sumCurr, basketCont.find(".sum").data("decimal"), 0.5, options);
		}

		basketCont.find(".oformit_cont").html(jQuery(result).find(".oformit_cont").html());
	}
};
