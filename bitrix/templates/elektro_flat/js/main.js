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
		$(this).addClass("current").siblings().removeClass("current")
			.parent().siblings(".tabs__box").eq($(this).index()).fadeIn(150).siblings(".tabs__box").hide();

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

function flyingCart(from, to, JCCatalogItem) {

	let origin = from;
	let block = $('<div></div>').append(origin.html());
	block.css({
		'z-index': 100500,
		background: '#b21001',
		top: origin.offset().top,
		left: origin.offset().left,
		width: origin.width()+'px',
		height: origin.height()+'px',
		position: 'absolute',
		overflow: 'hidden'
	}).appendTo('body').css({'padding':'10px','background':'#b21001','color':'#fff'}).animate({
		top: to.offset().top,
		left: to.offset().left,
		width: '10px',
		height: '10px',
		opacity: 0.7
	}, 600, function() {
		$(this).remove();
		JCCatalogItem.BasketResult();
	});
}
