/* global L, app_vars */

/**
 * @typedef {{ route_id: string, route_name: string, route_image: string, hex_color: string,
 * team_id: string, team_name: string, team_name_anchor: string, mates: string,
 * date: string, message: string, coordinates: string
 * }} Message
 */

/**
 * @type {Message[]} messages
 */
const messages = app_vars.messages;
const coordCenterLat = parseFloat(app_vars.coord_center_lat);
const coordCenterLng = parseFloat(app_vars.coord_center_lng);

/**
 * Gets the SVG for the marker with the given style.
 *
 * @param {string} style
 * @return {string}
 */
function getMarkerSVG(style) {
	'use strict';

	return `<svg xmlns="http://www.w3.org/2000/svg" style="${style}" height="32px" viewBox="0 -960 960 960" width="32px" fill="#000000">
				<path d="M536.5-503.5Q560-527 560-560t-23.5-56.5Q513-640 480-640t-56.5 23.5Q400-593 400-560t23.5 56.5Q447-480 480-480t56.5-23.5ZM480-80Q319-217 239.5-334.5T160-552q0-150 96.5-239T480-880q127 0 223.5 89T800-552q0 100-79.5 217.5T480-80Z"/>
			</svg>`;
}

/**
 * Gets a custom div icon for the marker with the given style.
 *
 * @param {string} style
 * @return {L.DivIcon}
 */
function getMarkerIcon(style) {
	'use strict';

	return L.divIcon({
		className: 'custom-div-icon',
		html: getMarkerSVG(style),
		iconSize: [32, 32],
		iconAnchor: [16, 32],
		popupAnchor: [0, -32],
	});
}

/**
 * Gets the content for the popup of the marker with the given message object.
 *
 * @param {Message} message
 * @return {string}
 */
function getPopupContent(message) {
	'use strict';

	return `<div><img class="no-bsl-adjust team-qi-route-logo" alt="Route Logo" src="${
		message.route_image
	}" style="width: 33px; float: left; padding: 0; margin-right: 10px;"/><p class="ticker-message-title" style="padding-bottom: 1em;">${
		message.team_name_anchor
	}</p><p class="ticker-message-mates" style="padding-bottom: 5px; font-size: 120%;">${
		message.mates
	}</p><p class="ticker-message-time" style="padding-bottom: 12px; font-size: 120%;">${
		message.date
	}</p><p style="font-weight: bold; font-size: 120%;">${
		message.message
	}</p></div>`;
}

/**
 * Gets the LatLng for the given coordinate string.
 *
 * @param {string} coordinate
 * @return {L.LatLng|null}
 */
function getLatLng(coordinate) {
	if (coordinate === '') {
		return null;
	}

	const loc = coordinate.split(',');

	if (loc.length !== 2) {
		return null;
	}

	const lat = parseFloat(loc[0]);
	const lng = parseFloat(loc[1]);

	if (isNaN(lat) || isNaN(lng)) {
		return null;
	}

	return L.latLng(lat, lng);
}

function initMap() {
	'use strict';

	if (typeof L === 'undefined' || document.getElementById('map') === null) {
		return;
	}

	const map = L.map('map').setView([coordCenterLat, coordCenterLng], 5);

	/** @type {{ routeID: string, routeName: string, icon: L.DivIcon, group: L.FeatureGroup }[]}} */
	const routes = [];
	/** @type {Map<string, { name: string, group: L.FeatureGroup, polyLine: L.Polyline}>} teams */
	const teams = new Map();

	for (const message of messages) {
		const latLng = getLatLng(message.coordinates);

		if (!latLng) {
			continue;
		}

		const routeId = message.route_id;
		const teamId = message.team_id;

		let route = routes.find((r) => r.routeID === routeId);

		if (!route) {
			const routeLayer = L.featureGroup();
			routeLayer.addTo(map);
			route = {
				routeID: routeId,
				routeName: message.route_name,
				icon: getMarkerIcon(`fill: #${message.hex_color};`),
				group: routeLayer,
			};
			routes.push(route);
		}

		if (!teams.has(teamId)) {
			const teamLayer = L.featureGroup();
			teamLayer.addTo(route.group);
			teams.set(teamId, {
				name: message.team_name,
				group: teamLayer,
				polyLine: L.polyline([]),
			});
		}

		const { group, polyLine } = teams.get(teamId);

		polyLine.addLatLng(latLng);

		const marker = L.marker(latLng, { icon: route.icon });

		marker
			.bindPopup(getPopupContent(message))
			.on('popupopen', () => {
				polyLine.addTo(map);
				group.eachLayer((layer) => {
					/** @type {HTMLDivElement} */
					const markerElem = layer.getElement();
					markerElem.children[0].classList.add('bounce');
				});
			})
			.on('popupclose', () => {
				polyLine.remove();
				group.eachLayer((layer) => {
					/** @type {HTMLDivElement} */
					const markerElem = layer.getElement();
					markerElem.children[0].classList.remove('bounce');
				});
			});

		marker.addTo(group);
	}

	if (routes.length === 0 || teams.size === 0) {
		return;
	}

	const overlays = {};
	const bounds = routes[0].group.getBounds();

	if (routes.length > 1) {
		routes.forEach((route, index) => {
			overlays[route.routeName] = route.group;
			if (index === 0) {
				return;
			}
			bounds.extend(route.group.getBounds());
		});
	} else if (teams.size > 1) {
		teams.forEach((team) => {
			overlays[team.name] = team.group;
		});
	}

	if (Object.keys(overlays).length > 0) {
		L.control.layers(null, overlays).addTo(map);
	}

	map.fitBounds(bounds);

	L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution:
			'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
	}).addTo(map);
}

window.addEventListener('load', initMap);
