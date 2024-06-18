const showPopup = () => {
    'use strict';

    const popup = document.getElementById('copy-popup');
    // Add the "show" class
    popup.classList.add('show');
    // After 2 seconds, remove the show class
    setTimeout(() => popup.classList.remove('show'), 2000);
};

/**
 * @param {HTMLElement} node
 */
const selectToken = node => {
    'use strict';

    const range = document.createRange();
    range.selectNode(node);
    window.getSelection().empty(); // clear current selection
    window.getSelection().addRange(range); // to select text
};

/**
 * @param {HTMLElement} node
 */
const copyTokenToClipboard = node => {
    'use strict';

    if (!navigator.clipboard) {
        // fallback to old deprecated execCommand function
        selectToken(node);
        document.execCommand('copy');
        window.getSelection().removeAllRanges();
        showPopup();
    } else {
        const text_to_copy = node.innerText;
        navigator.clipboard.writeText(text_to_copy).then(() => showPopup());
    }
};

/**
 * @param {HTMLElement} parent
 */
const showLoaderOfParent = parent => {
    'use strict';
    const loader = parent.querySelector('.loader');
    loader.classList.add('show');
};

/**
 * @param {HTMLElement} parent
 */
const hideLoaderOfParent = parent => {
    'use strict';
    const loader = parent.querySelector('.loader');
    loader.classList.remove('show');
};

/**
 * @param {string} message
 */
const showErrorMessage = message => {
    'use strict';
    const messageNode = document.getElementById('error-message');
    messageNode.innerText = message;
    messageNode.classList.add('show');
};

const hideErrorMessage = () => {
    'use strict';
    const messageNode = document.getElementById('error-message');
    messageNode.innerText = '';
    messageNode.classList.remove('show');
};

/**
 * @param {string} fundraisingEventId
 * @param {string|string[]} searchTerms
 * @param {HTMLElement} loaderParent
 */
const fetchAndFilterDonations = (fundraisingEventId, searchTerms, loaderParent) => {
    'use strict';

    const url = `https://api.betterplace.org/de/api_v4/fundraising_events/${fundraisingEventId}/opinions.json`;
    const params = {
        facets: "has_message:true",
        order: "confirmed_at:DESC",
        per_page: 200
    };

    showLoaderOfParent(loaderParent);

    jQuery.ajax(url, {
        type: 'GET',
        data: params,
        dataType: 'json',
        cache: false,
        success: (response) => {
            const donations = response.data;

            let filteredDonations;
            if (Array.isArray(searchTerms) && searchTerms.length > 0) {
                filteredDonations = donations.filter(donation => donation.message && searchTerms.some(searchTerm => donation.message.includes(searchTerm)));
            } else {
                filteredDonations = donations.filter(donation => donation.message && donation.message.includes(searchTerms));
            }

            // TODO: translate
            if (filteredDonations.length > 0) {
                hideErrorMessage();
                document.getElementById('donation-done-form').submit();
            } else {
                // Deine Spende kann bisher noch nicht gefunden werden. Warte ein bisschen und versuche es erneut.
                showErrorMessage(wp.i18n.__('Your donation cannot be found yet. Wait a little and try again.', 'donation not found', 'h3-mgmt'));
            }
            hideLoaderOfParent(loaderParent);
        },
        error: () => {
            // Die betterplace.org-API scheint derzeit nicht erreichbar zu sein. Versuche es später nochmal.
            showErrorMessage(wp.i18n.__('It seems that the betterplace.org API is currently not reachable. Please try again later.', 'donation', 'h3-mgmt'));
            hideLoaderOfParent(loaderParent);
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const donationToken = document.getElementById('donation-token');
    const copyIconButton = document.getElementById('copy-icon-button');
    // const donationDoneButton = document.getElementById('donation-done-button');

    if (donationToken !== null) {
        donationToken.addEventListener('click', e => selectToken(e.currentTarget));
        copyIconButton.addEventListener('click', () => copyTokenToClipboard(donationToken));
        // donationDoneButton.addEventListener('click', e => fetchAndFilterDonations('32770', donationToken.innerText, e.currentTarget));
    }
});
