/* External media is loaded only after an explicit visitor action. */
const tickerMediaControls = 'button[data-ticker-video], button[data-ticker-position], button[data-ticker-map], button[data-ticker-location]';
const enableTickerMedia = () => document.querySelectorAll(tickerMediaControls).forEach(button => { button.disabled = false; });
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', enableTickerMedia);
else enableTickerMedia();
document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;
    const button = event.target.closest(tickerMediaControls);
    if (!button) return;
    const container = button.parentElement;
    if (button.dataset.tickerLocation !== undefined) {
        const form = button.closest('form');
        const status = form.querySelector('[data-ticker-location-status]');
        const fail = () => { status.textContent = button.dataset.error; button.disabled = false; };
        if (!navigator.geolocation) { fail(); return; }
        button.disabled = true;
        navigator.geolocation.getCurrentPosition(position => {
            form.querySelector('[name=media]').value = `${position.coords.latitude},${position.coords.longitude}`;
            form.querySelector('[name=ticker_kind]').value = 'location';
            status.textContent = form.querySelector('[name=media]').value;
            button.disabled = false;
        }, fail, { timeout: 10000, maximumAge: 60000 });
        return;
    }
    if (button.dataset.tickerMap !== undefined) {
        if (!window.L || container.dataset.loaded) return;
        let points;
        try { points = JSON.parse(container.dataset.points); } catch { return; }
        if (!Array.isArray(points)) return;
        container.dataset.loaded = '1';
        const area = document.createElement('div');
        area.className = 'cor-ticker-map';
        area.tabIndex = 0;
        area.setAttribute('role', 'region');
        area.setAttribute('aria-label', button.textContent);
        container.append(area);
        const map = window.L.map(area, { scrollWheelZoom: false });
        window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);
        const bounds = [];
        const routes = new Map();
        const teams = new Map();
        for (const point of points) {
            if (!Array.isArray(point) || point.length < 3 || !Number.isFinite(point[0]) || !Number.isFinite(point[1]) || Math.abs(point[0]) > 90 || Math.abs(point[1]) > 180) continue;
            const context = point[3] && typeof point[3] === 'object' ? point[3] : {};
            const routeKey = String(context.route_id || 'unassigned');
            const teamKey = context.team_id ? `T_${context.team_id}` : `P_${point[2]}`;
            const colorValue = `#${String(context.color_code || '').replace(/^#/, '')}`;
            const color = /^#[a-f0-9]{6}$/i.test(colorValue) ? colorValue : '#005a9c';
            if (!routes.has(routeKey)) routes.set(routeKey, { name: context.route_name || routeKey, layer: window.L.featureGroup().addTo(map) });
            const route = routes.get(routeKey);
            if (!teams.has(teamKey)) teams.set(teamKey, { name: context.team_name || point[2], layer: window.L.featureGroup().addTo(route.layer), line: window.L.polyline([], { color }) });
            const team = teams.get(teamKey);
            team.line.addLatLng([point[0], point[1]]);
            const label = document.createElement('span');
            label.textContent = `${context.team_name || ''} ${context.route_name || ''} ${point[2]}`.trim();
            const pin = document.createElement('span');
            pin.className = 'cor-ticker-pin';
            pin.style.backgroundColor = color;
            const marker = window.L.marker([point[0], point[1]], { title: label.textContent, icon: window.L.divIcon({ className: 'cor-ticker-marker', html: pin, iconSize: [20, 20], iconAnchor: [10, 10] }) });
            marker.bindPopup(label).on('popupopen', () => team.line.addTo(map)).on('popupclose', () => team.line.remove()).addTo(team.layer);
            bounds.push([point[0], point[1]]);
        }
        const overlays = Object.create(null);
        const groups = routes.size > 1 ? routes : teams;
        if (groups.size > 1) {
            for (const group of groups.values()) {
                const name = document.createElement('span');
                name.textContent = String(group.name);
                overlays[name.outerHTML] = group.layer;
            }
            window.L.control.layers(null, overlays, { collapsed: false }).addTo(map);
        }
        if (bounds.length) map.fitBounds(bounds, { maxZoom: 12, padding: [20, 20] });
        else {
            const center = (container.dataset.center || '').split(',').map(Number);
            if (center.length === 2 && center.every(Number.isFinite) && Math.abs(center[0]) <= 90 && Math.abs(center[1]) <= 180) map.setView(center, 8);
            else map.setView([54.0237934, 9.3754401], 8);
        }
        button.disabled = true;
        return;
    }
    let url;
    if (/^[a-zA-Z0-9_-]{11}$/.test(button.dataset.tickerVideo || '')) {
        url = `https://www.youtube-nocookie.com/embed/${button.dataset.tickerVideo}`;
    } else if (button.dataset.tickerPosition) {
        const parts = button.dataset.tickerPosition.split(',').map(Number);
        if (parts.length !== 2 || !parts.every(Number.isFinite) || Math.abs(parts[0]) > 90 || Math.abs(parts[1]) > 180) return;
        const [lat, lon] = parts;
        url = `https://www.openstreetmap.org/export/embed.html?bbox=${lon - 0.02},${lat - 0.02},${lon + 0.02},${lat + 0.02}&layer=mapnik&marker=${lat},${lon}`;
    }
    if (!url) return;
    const frame = document.createElement('iframe');
    frame.src = url;
    frame.title = button.textContent;
    frame.loading = 'lazy';
    frame.referrerPolicy = 'strict-origin-when-cross-origin';
    frame.allowFullscreen = true;
    container.replaceChild(frame, button);
});
