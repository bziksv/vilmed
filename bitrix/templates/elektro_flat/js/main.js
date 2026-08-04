$(function() {
	//SCROLL_UP//
	var top_show = 150,
		delay = 500;
	$("body").append($("<a />").addClass("scroll-up").attr({"href": "javascript:void(0)", "id": "scrollUp"}).append($("<i />").addClass("fa fa-angle-up")));
	$("#scrollUp").click(function(e) {
		e.preventDefault();
		$("body, html").animate({scrollTop: 0}, delay);
		return false;
    });

	$(window).scroll(function () {
		if($(this).scrollTop() > top_show) {
			$("#scrollUp").fadeIn();
		} else {
			$("#scrollUp").fadeOut();
		}
    });

	//DISABLE_FORM_SUBMIT_ENTER//
	$(".add2basket_form").on("keyup keypress", function(e) {
		var keyCode = e.keyCode || e.which;
		if(keyCode === 13) {
			e.preventDefault();
			return false;
		}
	});

	//CALLBACK//
	var callbackBtn = BX("callbackAnch");
	if(!!callbackBtn)
		BX.bind(callbackBtn, "click", BX.delegate(function(){openFormCallback();}, this));

	//BTN_ANIMATION
    setInterval( BX.delegate(function () {
        openbtn();
    }, this), 5000);

	//TOP_PANEL_CONTACTS//
	$(".showcontacts").click(function() {
		var clickitem = $(this);
		if(clickitem.parent("li").hasClass("")) {
			clickitem.parent("li").addClass("active");
		} else {
			clickitem.parent("li").removeClass("active");
		}
		if($(".showsection").parent("li").hasClass("active")) {
			$(".showsection").parent("li").removeClass("active");
			$(".showsection").parent("li").find(".catalog-section-list").css({"display":"none"});
		}
		if($(".showsubmenu").parent("li").hasClass("active")) {
			$(".showsubmenu").parent("li").removeClass("active");
			$(".showsubmenu").parent("li").find("ul.submenu").css({"display":"none"});
		}
		if($(".showsearch").parent("li").hasClass("active")) {
			$(".showsearch").parent("li").removeClass("active");
			$(".header_2").css({"display":"none"});
			$(".title-search-result").css({"display":"none"});
		}
		$(".header_4").slideToggle();
	});

	//TOP_PANEL_SEARCH//
	$(".showsearch").click(function() {
		var clickitem = $(this);
		if(clickitem.parent("li").hasClass("")) {
			clickitem.parent("li").addClass("active");
		} else {
			clickitem.parent("li").removeClass("active");
			$(".title-search-result").css({"display":"none"});
		}
		if($(".showsection").parent("li").hasClass("active")) {
			$(".showsection").parent("li").removeClass("active");
			$(".showsection").parent("li").find(".catalog-section-list").css({"display":"none"});
		}
		if($(".showsubmenu").parent("li").hasClass("active")) {
			$(".showsubmenu").parent("li").removeClass("active");
			$(".showsubmenu").parent("li").find("ul.submenu").css({"display":"none"});
		}
		if($(".showcontacts").parent("li").hasClass("active")) {
			$(".showcontacts").parent("li").removeClass("active");
			$(".header_4").css({"display":"none"});
		}
		$(".header_2").slideToggle();
	});

	//TABS_MAIN//
	if($(".tabs__box.new .filtered-items").length < 1)
		$(".tabs__tab.new, .tabs__box.new").remove();
	if($(".tabs__box.hit .filtered-items").length < 1)
		$(".tabs__tab.hit, .tabs__box.hit").remove();
	if($(".tabs__box.discount .filtered-items").length < 1)
		$(".tabs__tab.discount, .tabs__box.discount").remove();

	$(".tabs-main .tabs__tab").first().addClass("current");
	$(".tabs-main .tabs__box").first().css({"display":"block"});

	//ITEMS_HEIGHT//
	var itemsTable = $(".filtered-items:visible .catalog-item-card");
	if(!!itemsTable && itemsTable.length > 0) {
		$(window).resize(function() {
			adjustItemHeight(itemsTable);
		});
		adjustItemHeight(itemsTable);
	}

	//CHANGE_TAB//
	$("body").on("click", ".tabs__tab:not(.current)", function() {
		var $box = $(this).parent().siblings(".tabs__box").eq($(this).index());
		$(this).addClass("current").siblings().removeClass("current")
			.parent().siblings(".tabs__box").eq($(this).index()).fadeIn(150).siblings(".tabs__box").hide();

		if (window.vilmedLoadDeferredImages && $box.length) {
			window.vilmedLoadDeferredImages($box[0]);
		}

		//ITEMS_HEIGHT//
		var itemsTable = $(this).parent().siblings(".tabs__box").eq($(this).index()).find(".catalog-item-card");
		if(!!itemsTable && itemsTable.length > 0) {
			$(window).resize(function() {
				adjustItemHeight(itemsTable);
			});
			adjustItemHeight(itemsTable);
		}
	});

	//DELAY//
	var currPage = window.location.pathname;
	var delayIndex = window.location.search;
	if((currPage == "/personal/cart/") && (document.getElementById("id-shelve-list")) && (delayIndex == "?delay=Y")) {
		$("#id-shelve-list").show();
		$("#id-cart-list").hide();
	} else {
		$("#id-shelve-list").hide();
		$("#id-cart-list").show();
	}

	//CUSTOM_FORMS//
	$(".custom-forms").customForms({});


//CATALOG_MENU_HIDDEN//
    var flag=1;
    $("#catalog_wrap_btn").click(function() {

        $("#catalog_wrap").slideToggle("slow");
       if(flag==0){
	       flag=1;
            $("#catalog_wrap_btn .showfilter .fa-angle-down").css({"display":"block"});
            $("#catalog_wrap_btn .showfilter .fa-angle-up").css({"display":"none"});
	   }
        else{
		 flag=0;
            $("#catalog_wrap_btn .showfilter .fa-angle-down").css({"display":"none"});
            $("#catalog_wrap_btn .showfilter .fa-angle-up").css({"display":"block"});
     	}
    });

	var text = $('#pagetitle').text();
    $('#pagetitle').text(text.replace(/&quot;/g, ''));



	if ($('.tag-slider').length && typeof $.fn.slick === 'function') {
	$('.tag-slider').slick({
		dots: false,
		arrows: true,
		infinite: true,
		autoplay: true,
		variableWidth: true,
		centerMode: true,
		slidesToShow: 2,
		responsive: [
			{
				breakpoint: 767,
				settings: {
					slidesToShow: 1,
					slidesToScroll: 1
				}
			}
		]
	});

	$(document).ready(function() {
		$(".subcategories .open").click(function(){
			$(this).hide();
			$(".subcategories .close").show();
			$(".subcategories .sub-links-2").addClass("open");
			$('.tag-slider').slick('unslick');
		});
		$(".subcategories .close").click(function(){
			$(this).hide();
			$(".subcategories .open").show();
			$(".subcategories .sub-links-2").removeClass("open");
			$('.tag-slider').slick({
				dots: false,
				arrows: true,
				infinite: true,
				autoplay: true,
				variableWidth: true,
				centerMode: true,
				slidesToShow: 3,
			});
		});
	});
	}

	if ($("[data-text_script]").length){
		$('[data-text_script]').each(function(i, el){
			var span = $(el);
			span.html(span.data('text_script'));
		});
	}

	$('.catalog-item-table-view .catalog-item-card .item-all-title').hover(function () {
		$(this).height($(this)[0].scrollHeight);
	}, function () {
		$(this).removeAttr('style');
	});

	if ($(".item-hide-image").length){
		let sectionItem = $(".item-hide-image").closest('.catalog-item-info');
		sectionItem.find('a[itemprop="url"]').attr('href','#');
	}
	
});

/** Visible cart icon for fly-to-cart (old #cart_line1 is display:none under floating header). */
function getFlyingCartTarget() {
	var selectors = [
		'.vilmed-fh.is-visible [data-vfh="cart"]',
		'.vilmed-hdr-icons [data-vfh="cart"]',
		'#cart_line1 a.cart',
		'a.cart'
	];
	var i, $el, el, r;
	for (i = 0; i < selectors.length; i++) {
		$el = $(selectors[i]).filter(function() {
			r = this.getBoundingClientRect();
			return r.width > 2 && r.height > 2 && r.bottom > 0 && r.top < (window.innerHeight || 800);
		}).first();
		if ($el.length) {
			return $el;
		}
	}
	$el = $('[data-vfh="cart"]').filter(function() {
		r = this.getBoundingClientRect();
		return r.width > 2 && r.height > 2;
	}).first();
	return $el.length ? $el : $();
}

function flyingCart(from, to, JCCatalogItem) {
	var origin = from && from.length ? from.first() : $();
	var target = getFlyingCartTarget();
	if (!target.length && to && to.length) {
		target = to.first();
	}
	var done = function() {
		if (JCCatalogItem && typeof JCCatalogItem.BasketResult === 'function') {
			JCCatalogItem.BasketResult();
		}
	};
	if (!origin.length || !target.length) {
		done();
		return;
	}

	var fromRect = origin[0].getBoundingClientRect();
	var toRect = target[0].getBoundingClientRect();
	var block = $('<div></div>').append(origin.html());
	block.css({
		'z-index': 100500,
		background: '#b21001',
		color: '#fff',
		padding: '10px',
		top: fromRect.top,
		left: fromRect.left,
		width: Math.max(fromRect.width, 10) + 'px',
		height: Math.max(fromRect.height, 10) + 'px',
		position: 'fixed',
		overflow: 'hidden',
		pointerEvents: 'none'
	}).appendTo('body').animate({
		top: toRect.top + toRect.height / 2 - 5,
		left: toRect.left + toRect.width / 2 - 5,
		width: '10px',
		height: '10px',
		opacity: 0.7
	}, 600, function() {
		$(this).remove();
		done();
	});
}
