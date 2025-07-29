($ => { // closure
    'use strict';
    $(() => {
        $('#stages-filters a').on('click', () => {
            const selector = $(this).attr('data-filter');
            $('#stages-container').isotope({ filter: selector });
            return false;
        });

        $('#country-filters a').on('click', () => {
            const selector = $(this).attr('data-filter');
            $('#stages-container').isotope({ filter: selector });
            return false;
        });

        $('#stages-container').isotope({
            getSortData: {
                route: '.route',
                stage: '.stage parseInt',
                country: '.country',
            },
        });

        $('#sort-by a').on('click', () => {
            const sortName = $(this).attr('href').slice(1);
            $('#stages-container').isotope({ sortBy: sortName });
            return false;
        });

        $('#teams-route-filters a').on('click', () => {
            const selector = $(this).attr('data-filter');
            $('#teams-container').isotope({ filter: selector });
            return false;
        });

        $('#teams-other-filters a').on('click', () => {
            const selector = $(this).attr('data-filter');
            $('#teams-container').isotope({ filter: selector });
            return false;
        });

        $('#teams-container').isotope({
            getSortData: {
                route: '.team-overview-route',
                teamname: '.team-name',
                sponsorcount: '.sponsor-count parseInt',
            },
        });

        $('#teams-sort-by a').on('click', () => {
            const sortName = $(this).attr('href').slice(1);
            $('#teams-container').isotope({ sortBy: sortName });
            return false;
        });

        $('.isotope-wrap .jumper').on('click', () => {
            const teamID = $('.isotope-wrap .team-selector').val();
            window.location.search = `id=${teamID}`;
        });

        $(window).on('load', () => {
            $('#teams-container').isotope();
        });
    });
})(jQuery); // closure