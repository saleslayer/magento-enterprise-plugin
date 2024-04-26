require(['jquery'], function ($) {
    $(document).ready(function () {
        setInterval(function () {
            var cells = $('.grid-col-status-class:not(.grid-header-class)');
            cells.each(function () {
                if ($(this).text().trim().length > 0) {
                    location.reload();
                }
            });

        }, 60000);
    });
});