const coordinates = app_vars.coordinates;
const messages = app_vars.messages;
const coord_center_lat = parseFloat(app_vars.coord_center_lat);
const coord_center_lng = parseFloat(app_vars.coord_center_lng);
/** @type {google.maps.marker.AdvancedMarkerElement[]} */
const markerList = [];

const dummyPath = [
    new google.maps.LatLng(0, 0),
    new google.maps.LatLng(0, 0),
];

let linePath = new google.maps.Polyline({
    path: dummyPath,
    geodesic: true,
    strokeColor: '#000000',
    strokeOpacity: 1.0,
    strokeWeight: 3,
});

function initMap() {
    'use strict';

    const map = new google.maps.Map(document.getElementById('map-canvas'), {
        center: { lat: coord_center_lat, lng: coord_center_lng }, // lat: 46.3682855, lng: 14.4170272
        zoom: 5,
        mapId: 'DEMO_MAP_ID',
    });

    const bounds = new google.maps.LatLngBounds();

    linePath.setMap(map);

    const infoWindow = new google.maps.InfoWindow();

    for (let i = 0; i < coordinates.length; ++i) {
        const loc = coordinates[i].split(",");
        const lat = parseFloat(loc[0]);
        const lng = parseFloat(loc[1]);
        const myLatlng = new google.maps.LatLng(lat, lng);

        bounds.extend(myLatlng);

        const contentString = `<div><img class="no-bsl-adjust team-qi-route-logo" alt="Route Logo" src="${
            messages[i].route_image
        }" style="width: 33px; float: left; padding: 0; margin-right: 10px;"/><p class="ticker-message-title" style="padding-bottom: 1em;">${
            messages[i].team_name_url
        }</p><p class="ticker-message-mates" style="padding-bottom: 0.01em; font-size: 120%;">${
            messages[i].mates
        }</p><p class="ticker-message-time" style="padding-bottom: 1em; font-size: 120%;">${
            messages[i].date
        }</p><p style="font-weight: bold; font-size: 120%;">${
            messages[i].message
        }</p></div>`;

        const pin = new google.maps.marker.PinElement({
            background: `#${messages[i].hex_color}`,
        });

        const marker = new google.maps.marker.AdvancedMarkerElement({
            map,
            position: myLatlng,
            title: messages[i].team_name,
            content: pin.element,
            gmpClickable: true,
        });

        marker.dataset.teamId = messages[i].team_id;

        markerList[i] = marker;

        map.fitBounds(bounds);
        map.panToBounds(bounds);

        bindInfoWindow(marker, markerList, infoWindow, contentString);
    }
}

window.addEventListener('load', initMap);

/**
 * @param {google.maps.marker.AdvancedMarkerElement} marker
 * @param {google.maps.marker.AdvancedMarkerElement[]} marker_all
 * @param {google.maps.InfoWindow} infoWindow
 * @param {string} contentString
 */
function bindInfoWindow(marker, marker_all, infoWindow, contentString) {
    'use strict';
    marker.addListener('click', function () {
        /** @type {google.maps.LatLng[]} */
        const lines = [];

        infoWindow.close();

        for (let index = 0; index < marker_all.length; ++index) {
            marker_all[index].content.classList.remove('bounce');

            if (marker_all[index].dataset.teamId === marker.dataset.teamId) {
                lines.push(marker_all[index].position);
                marker_all[index].content.classList.add('bounce');
            }
        }

        linePath.setMap(null);

        linePath = new google.maps.Polyline({
            path: lines, geodesic: true, strokeColor: '#000000', strokeOpacity: 1.0, strokeWeight: 3,
        });

        linePath.setMap(marker.map);

        infoWindow.setContent(contentString);
        infoWindow.open(marker.map, marker);
    });
}
