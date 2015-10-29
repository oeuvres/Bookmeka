var Omeka = Omeka || {};
(function ($) {
    Omeka.tocScroll = function () {
        var $toc   = $("nav.toc"),
            $window = $(window),
            offset  = $toc.offset(),
            topPadding = 62;
        console.log($toc);
        $window.scroll(function () {
            if($window.scrollTop() > offset.top && $window.width() > 767 && ($window.height() - topPadding - 85) >  $toc.height()) {
                $toc.stop().animate({
                    marginTop: $window.scrollTop() - offset.top + topPadding
                    });
            } else {
                $toc.stop().animate({
                    marginTop: 0
                });
            }
        });
    };
   
})(jQuery);
