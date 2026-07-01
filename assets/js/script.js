(function($) {
    "use strict";

    // Preloader
    function handlePreloader() {
        if ($('.preloader').length && !$('body').hasClass('page-loaded')) {
            $('body').addClass('page-loaded');
            $('.preloader').fadeOut(300);
        }
    }

    // Header style on scroll
    function headerStyle() {
        if ($('.main-header').length) {
            var windowpos = $(window).scrollTop();
            var siteHeader = $('.main-header');
            var scrollLink = $('.scroll-to-top');
            var sticky_header = $('.main-header .sticky-header');
            if (windowpos > 100) {
                siteHeader.addClass('fixed-header');
                sticky_header.addClass("animated slideInDown");
                scrollLink.fadeIn(300);
            } else {
                siteHeader.removeClass('fixed-header');
                sticky_header.removeClass("animated slideInDown");
                scrollLink.fadeOut(300);
            }
        }
    }

    headerStyle();

    // ========== MOBILE MENU SETUP (FIXED SELECTORS) ==========
    function initMobileMenu() {
        // 1. Add dropdown button to desktop menu items that have children
        if ($('.main-header .main-menu .navigation li.dropdown').length) {
            $('.main-header .main-menu .navigation li.dropdown').append('<div class="dropdown-btn"><span class="fa fa-angle-down"></span></div>');
        }

        // 2. Clone the desktop menu into mobile container
        if ($('.mobile-menu').length) {
            var mobileMenuContent = $('.main-header .nav-outer .main-menu').html();
            $('.mobile-menu .menu-box .menu-outer').append(mobileMenuContent);
            $('.sticky-header .main-menu').append(mobileMenuContent);

            $('.mobile-menu .menu-box').mCustomScrollbar();

            // 3. Bind dropdown toggle on the cloned mobile menu
            $(document).on('click', '.mobile-menu .dropdown-btn', function(e) {
                e.preventDefault();
                var parent = $(this).closest('li');
                var submenu = parent.children('ul');
                if (submenu.length) {
                    submenu.stop(true, true).slideToggle(300, function() {
                        $('.mobile-menu .menu-box').mCustomScrollbar("update");
                    });
                    parent.toggleClass('open');
                }
            });

            // 4. Open mobile menu when hamburger is clicked
            $('.mobile-nav-toggler').on('click', function() {
                $('body').addClass('mobile-menu-visible');
            });

            // 5. Close mobile menu
            $('.mobile-menu .menu-backdrop, .mobile-menu .close-btn').on('click', function() {
                $('body').removeClass('mobile-menu-visible');
            });
        }
    }

    // Search Popup (keep your existing code)
    if ($('#search-popup').length) {
        $('.search-toggler').on('click', function() {
            $('#search-popup').addClass('popup-visible');
        });
        $(document).keydown(function(e) {
            if (e.keyCode === 27) {
                $('#search-popup').removeClass('popup-visible');
            }
        });
        $('.close-search, .search-popup .overlay-layer').on('click', function() {
            $('#search-popup').removeClass('popup-visible');
        });
    }

    // Banner Carousel
    if ($('.banner-carousel').length) {
        $('.banner-carousel').owlCarousel({
            loop: true,
            animateOut: 'fadeOut',
            animateIn: 'fadeIn',
            margin: 0,
            nav: true,
            smartSpeed: 500,
            autoplay: 6000,
            navText: ['<span class="icon flaticon-arrows"></span>', '<span class="icon flaticon-arrows"></span>'],
            responsive: { 0: { items: 1 }, 600: { items: 1 }, 800: { items: 1 }, 1024: { items: 1 } }
        });
    }

    // Donation Progress Bar
    if ($('.count-bar').length) {
        $('.count-bar').appear(function() {
            var el = $(this);
            var percent = el.data('percent');
            $(el).css('width', percent).addClass('counted');
        }, { accY: -50 });
    }

    // Single Item Carousel
    if ($('.single-item-carousel').length) {
        $('.single-item-carousel').owlCarousel({
            loop: true, margin: 10, nav: true, smartSpeed: 700, autoplay: 5000,
            navText: ['<span class="fa fa-angle-left"></span>', '<span class="fa fa-angle-right"></span>'],
            responsive: { 0: { items: 1 }, 600: { items: 1 }, 800: { items: 1 }, 1024: { items: 1 } }
        });
    }

    // Fact Counter
    if ($('.count-box').length) {
        $('.count-box').appear(function() {
            var $t = $(this),
                n = $t.find(".count-text").attr("data-stop"),
                r = parseInt($t.find(".count-text").attr("data-speed"), 10);
            if (!$t.hasClass("counted")) {
                $t.addClass("counted");
                $({ countNum: $t.find(".count-text").text() }).animate({ countNum: n }, {
                    duration: r, easing: "linear",
                    step: function() { $t.find(".count-text").text(Math.floor(this.countNum)); },
                    complete: function() { $t.find(".count-text").text(this.countNum); }
                });
            }
        }, { accY: 0 });
    }

    // Gallery Carousel
    if ($('.insta-gallery-carousel').length) {
        $('.insta-gallery-carousel').owlCarousel({
            loop: true, margin: 0, nav: true, smartSpeed: 500, autoplay: true,
            navText: ['<span class="fa fa-angle-left"></span>', '<span class="fa fa-angle-right"></span>'],
            responsive: { 0: { items: 1 }, 600: { items: 2 }, 800: { items: 3 }, 1024: { items: 4 }, 1500: { items: 5 }, 1920: { items: 6 } }
        });
    }

    // Team Carousel
    if ($('.team-carousel').length) {
        $('.team-carousel').owlCarousel({
            loop: true, margin: 30, nav: true, smartSpeed: 700, autoplay: 5000,
            navText: ['<span class="icon flaticon-arrows-12"></span>', '<span class="icon flaticon-arrows-12"></span>'],
            responsive: { 0: { items: 1 }, 600: { items: 1 }, 768: { items: 2 }, 800: { items: 2 }, 1024: { items: 3 } }
        });
    }

    // Patrons logic: if > 9 items, group into 9-logo slides (3x3 grid) and turn into carousel, otherwise show static 3-column grid
    if ($('.patrons-grid-container').length) {
        var patronsList = $('.patrons-grid-container');
        var patronItems = patronsList.find('.patron-item');
        var patronsCount = patronItems.length;

        if (patronsCount > 9) {
            // Clear current flat layout in container
            patronsList.empty();
            
            // Group the patron items into chunks of 9
            var chunkSize = 9;
            for (var i = 0; i < patronsCount; i += chunkSize) {
                var chunk = patronItems.slice(i, i + chunkSize);
                var slideDiv = $('<div class="patrons-slide-grid row clearfix"></div>');
                chunk.addClass('col-4 mb-3').appendTo(slideDiv);
                patronsList.append(slideDiv);
            }
            
            // Initialize Owl Carousel (each slide displays a 9-logo grid)
            patronsList.addClass('owl-carousel owl-theme patrons-carousel');
            patronsList.owlCarousel({
                loop: true,
                margin: 0,
                nav: false,
                dots: true,
                autoplay: true,
                autoplayTimeout: 5000,
                smartSpeed: 600,
                items: 1
            });
        } else {
            // Under 9 items: just display as a static 3-column grid
            patronsList.addClass('row clearfix');
            patronItems.addClass('col-4 mb-3');
        }
    }

    // Awards Carousel (Stays vertical list by default; becomes carousel if count > 3)
    if ($('.awards-list').length) {
        var awardsCount = $('.awards-list .award-item').length;
        if (awardsCount > 3) {
            $('.awards-list').addClass('owl-carousel owl-theme');
            $('.awards-list').owlCarousel({
                loop: true,
                margin: 20,
                nav: false,
                dots: true,
                autoplay: true,
                autoplayTimeout: 5000,
                smartSpeed: 600,
                items: 1
            });
        }
    }

    // Testimonials Carousel
    if ($('.testimonials-carousel').length) {
        $('.testimonials-carousel').owlCarousel({
            loop: true, margin: 30, nav: true, smartSpeed: 500, autoplay: 6000,
            navText: ['<span class="fa fa-arrow-left"></span>', '<span class="fa fa-arrow-right"></span>'],
            responsive: { 0: { items: 1 }, 600: { items: 1 }, 800: { items: 1 }, 1024: { items: 1 } }
        });
    }

    // MixitUp Gallery Filters
    if ($('.filter-list').length) {
        $('.filter-list').mixItUp({});
    }

    // Tabs Box
    if ($('.tabs-box').length) {
        $('.tabs-box .tab-buttons .tab-btn').on('click', function(e) {
            e.preventDefault();
            var target = $($(this).attr('data-tab'));
            if ($(target).is(':visible')) { return false; } else {
                target.parents('.tabs-box').find('.tab-buttons .tab-btn').removeClass('active-btn');
                $(this).addClass('active-btn');
                target.parents('.tabs-box').find('.tabs-content .tab').fadeOut(0).removeClass('active-tab');
                $(target).fadeIn(300).addClass('active-tab');
            }
        });
    }

    // Accordion Box
    if ($('.accordion-box').length) {
        $(".accordion-box").on('click', '.acc-btn', function() {
            var outerBox = $(this).parents('.accordion-box');
            var target = $(this).parents('.accordion');
            if ($(this).hasClass('active') !== true) {
                $(outerBox).find('.accordion .acc-btn').removeClass('active');
            }
            if ($(this).next('.acc-content').is(':visible')) { return false; } else {
                $(this).addClass('active');
                $(outerBox).children('.accordion').removeClass('active-block');
                $(outerBox).find('.accordion').children('.acc-content').slideUp(300);
                target.addClass('active-block');
                $(this).next('.acc-content').slideDown(300);
            }
        });
    }

    // Price Range Slider
    if ($('.price-range-slider').length) {
        $(".price-range-slider").slider({
            range: true, min: 0, max: 400, values: [50, 300],
            slide: function(event, ui) { $("input.property-amount").val(ui.values[0] + " - " + ui.values[1]); }
        });
        $("input.property-amount").val($(".price-range-slider").slider("values", 0) + " - $" + $(".price-range-slider").slider("values", 1));
    }

    // Custom Select Box
    if ($('.custom-select-box').length) {
        $('.custom-select-box').selectmenu().selectmenu('menuWidget').addClass('overflow');
    }

    // Related Products Carousel
    if ($('.related-products-carousel').length) {
        $('.related-products-carousel').owlCarousel({
            loop: true, margin: 30, nav: true, smartSpeed: 500, autoplay: true,
            navText: ['<span class="flaticon-next-1"></span>', '<span class="flaticon-next-1"></span>'],
            responsive: { 0: { items: 1 }, 600: { items: 1 }, 768: { items: 2 }, 1024: { items: 3 }, 1280: { items: 4 } }
        });
    }

    // Quantity Spinner
    if ($('.quantity-spinner').length) {
        $("input.quantity-spinner").TouchSpin({ verticalbuttons: true });
    }

    // LightBox / Fancybox
    if ($('.lightbox-image').length) {
        $('.lightbox-image').fancybox({
            openEffect: 'fade', closeEffect: 'fade',
            helpers: { media: {} }
        });
    }

    // Contact Form Validation
    if ($('#contact-form').length) {
        $('#contact-form').validate({
            rules: { username: { required: true }, email: { required: true, email: true }, message: { required: true } }
        });
    }

    // Scroll to Target
    if ($('.scroll-to-target').length) {
        $(".scroll-to-target").on('click', function() {
            var target = $(this).attr('data-target');
            $('html, body').animate({ scrollTop: $(target).offset().top }, 1500);
        });
    }

    // WOW Animations
    if ($('.wow').length) {
        var wow = new WOW({ boxClass: 'wow', animateClass: 'animated', offset: 0, mobile: false, live: true });
        wow.init();
    }

    // Run on document ready
    $(document).ready(function() {
        initMobileMenu();

        // Voice Card Read More Toggle
        $('.voices-section').on('click', '.read-more-toggle', function(e) {
            e.preventDefault();
            var card = $(this).closest('.voice-card');
            card.toggleClass('expanded');
            if (card.hasClass('expanded')) {
                $(this).find('.btn-title').text('Show less');
            } else {
                $(this).find('.btn-title').text('Read more');
            }
        });
    });

    $(window).on('scroll', function() { headerStyle(); });
    $(window).on('load', function() { handlePreloader(); });

    // Fallback: force preloader to disappear after 2.5 seconds
    setTimeout(function() {
        handlePreloader();
    }, 2500);

})(window.jQuery);

/* ==========================================================================
   Program Page Tab Switcher
========================================================================== */
function assraSwitchTab(tabName, btn) {
    var contents = document.getElementsByClassName("assra-tab-content");
    for (var i = 0; i < contents.length; i++) { contents[i].style.display = "none"; }
    var buttons = document.getElementsByClassName("program-tab");
    for (var i = 0; i < buttons.length; i++) { buttons[i].classList.remove("active"); }
    var selectedTab = document.getElementById("tab-" + tabName);
    if (selectedTab) selectedTab.style.display = "block";
    if (btn) btn.classList.add("active");
}