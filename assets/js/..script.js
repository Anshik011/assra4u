(function($) {
    "use strict";
    $(document).ready(function() {
        // Add dropdown buttons to desktop menu items with children
        $('.main-header .main-menu .navigation li.dropdown').append('<div class="dropdown-btn"><span class="fa fa-angle-down"></span></div>');

        // Clone desktop menu into mobile menu
        var mobileMenuContent = $('.main-header .nav-outer .main-menu').html();
        $('.mobile-menu .menu-box .menu-outer').html(mobileMenuContent);

        // Add dropdown buttons to mobile menu items with children
        $('.mobile-menu .navigation li').each(function() {
            if ($(this).children('ul').length) {
                if (!$(this).find('.dropdown-btn').length) {
                    $(this).append('<div class="dropdown-btn"><span class="fa fa-angle-down"></span></div>');
                }
                $(this).addClass('dropdown');
            }
        });

        // Toggle submenu on dropdown button click
        $(document).on('click', '.mobile-menu .dropdown-btn', function(e) {
            e.preventDefault();
            var $li = $(this).closest('li');
            $li.children('ul').slideToggle(300);
            $li.toggleClass('open');
        });

        // Open/close mobile menu
        $('.mobile-nav-toggler').on('click', function() {
            $('body').addClass('mobile-menu-visible');
        });
        $('.mobile-menu .menu-backdrop, .mobile-menu .close-btn').on('click', function() {
            $('body').removeClass('mobile-menu-visible');
        });
    });
})(jQuery);