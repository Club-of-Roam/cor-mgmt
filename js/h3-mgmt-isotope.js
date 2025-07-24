/* global IsotopeParams */

jQuery('#stages-filters a').on('click', () => {
    'use strict';
    const selector = jQuery(this).attr('data-filter');
    jQuery('#stages-container').isotope({ filter: selector });
    return false;
});

jQuery('#country-filters a').on('click', () => {
    'use strict';
    const selector = jQuery(this).attr('data-filter');
    jQuery('#stages-container').isotope({ filter: selector });
    return false;
});

jQuery('#stages-container').isotope({
    getSortData: {
        route: '.route',
        stage: '.stage parseInt',
        country: '.country',
    },
});

jQuery('#sort-by a').on('click', () => {
    'use strict';
    const sortName = jQuery(this).attr('href').slice(1);
    jQuery('#stages-container').isotope({ sortBy: sortName });
    return false;
});

jQuery('#teams-route-filters a').on('click', () => {
    'use strict';
    const selector = jQuery(this).attr('data-filter');
    jQuery('#teams-container').isotope({ filter: selector });
    return false;
});

jQuery('#teams-other-filters a').on('click', () => {
    'use strict';
    const selector = jQuery(this).attr('data-filter');
    jQuery('#teams-container').isotope({ filter: selector });
    return false;
});

jQuery('#teams-container').isotope({
    getSortData: {
        route: '.team-overview-route',
        teamname: '.team-name',
        sponsorcount: '.sponsor-count parseInt',
    },
});

jQuery('#teams-sort-by a').on('click', () => {
    'use strict';
    const sortName = jQuery(this).attr('href').slice(1);
    jQuery('#teams-container').isotope({ sortBy: sortName });
    return false;
});

jQuery(window).on('load', () => {
    'use strict';
    jQuery('#teams-container').isotope();
});

jQuery('.isotope-wrap .jumper').on('click', () => {
    'use strict';
    const teamID = jQuery('.isotope-wrap .team-selector').val();
    window.location = `${IsotopeParams.redirect}?id=${teamID}`;
});
