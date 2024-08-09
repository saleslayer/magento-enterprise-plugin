require(['jquery'], function ($) {
    $(".faq-question").click(function() {
        var $answer = $(this).next(".faq-answer");
        
        if ($answer.is(":visible")) {
            $answer.slideUp();
            $(this).removeClass("active");
        } else {
            $answer.slideDown();
            $(this).addClass("active");
        }
    });
});
