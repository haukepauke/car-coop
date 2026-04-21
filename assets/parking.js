import L from 'leaflet';

const mapEl = document.getElementById('parking-map');
if (mapEl) {
const mapShell = mapEl.closest('.parking-map-shell');

// Fix marker icon broken by ES module bundling
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconUrl: mapEl.dataset.markerIcon,
    iconRetinaUrl: mapEl.dataset.markerIcon,
    shadowUrl: '',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
});

const label    = mapEl.dataset.label;
const hasLocation = mapEl.dataset.lat !== '' && mapEl.dataset.lng !== '';
const defaultLat  = hasLocation ? parseFloat(mapEl.dataset.lat) : 51.505;
const defaultLng  = hasLocation ? parseFloat(mapEl.dataset.lng) : -0.09;
const zoom        = hasLocation ? 17 : 13;

const map = L.map('parking-map').setView([defaultLat, defaultLng], zoom);
let clickMarker = null;

const tileLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}).addTo(map);

function setMapLoading(isLoading) {
    if (!mapShell) {
        return;
    }

    mapShell.classList.toggle('is-loading', isLoading);
}

tileLayer.on('loading', function () {
    setMapLoading(true);
});

tileLayer.on('load', function () {
    setMapLoading(false);
});

tileLayer.on('tileerror', function () {
    setMapLoading(false);
});

requestAnimationFrame(function () {
    map.invalidateSize();
});

if (hasLocation) {
    clickMarker = L.marker([defaultLat, defaultLng], { draggable: true })
        .addTo(map)
        .bindPopup(label)
        .openPopup();

    clickMarker.on('dragend', function () {
        const pos = clickMarker.getLatLng();
        latInput.value = pos.lat;
        lngInput.value = pos.lng;
        saveBtn.classList.remove('d-none');
    });
}

const saveBtn  = document.getElementById('parking-save-btn');
const latInput = document.getElementById('parking-save-lat');
const lngInput = document.getElementById('parking-save-lng');
const currentLocationBtn = document.getElementById('parking-current-location-btn');
const statusEl = document.getElementById('parking-location-status');
const navigateBtn = document.getElementById('parking-navigate-btn');

function updateStatus(message, tone = 'muted') {
    if (!statusEl) {
        return;
    }

    statusEl.textContent = message;
    statusEl.classList.remove('text-muted', 'text-success', 'text-danger');
    statusEl.classList.add(`text-${tone}`);
}

function buildNavigationUrl(lat, lng) {
    const userAgent = navigator.userAgent || '';
    const encodedDestination = `${lat},${lng}`;

    if (/iPhone|iPad|iPod/i.test(userAgent)) {
        return `https://maps.apple.com/?daddr=${encodedDestination}&dirflg=d`;
    }

    if (/Android/i.test(userAgent)) {
        return `google.navigation:q=${encodedDestination}`;
    }

    return `https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route=%3B${encodedDestination}`;
}

function updateNavigationLink(lat, lng) {
    if (!navigateBtn) {
        return;
    }

    navigateBtn.href = buildNavigationUrl(lat, lng);
    navigateBtn.classList.remove('d-none');
}

function setParkingMarker(lat, lng) {
    if (clickMarker) {
        clickMarker.setLatLng([lat, lng]);
    } else {
        clickMarker = L.marker([lat, lng], { draggable: true }).addTo(map);
        clickMarker.on('dragend', function () {
            const pos = clickMarker.getLatLng();
            latInput.value = pos.lat;
            lngInput.value = pos.lng;
        });
    }

    latInput.value = lat;
    lngInput.value = lng;
    saveBtn.classList.remove('d-none');
    updateNavigationLink(lat, lng);
}

if (hasLocation) {
    updateNavigationLink(defaultLat, defaultLng);
}

map.on('click', function (e) {
    const { lat, lng } = e.latlng;
    setParkingMarker(lat, lng);
});

if (currentLocationBtn) {
    currentLocationBtn.addEventListener('click', function () {
        if (!navigator.geolocation) {
            updateStatus(mapEl.dataset.geolocationUnavailableLabel, 'danger');
            return;
        }

        currentLocationBtn.disabled = true;
        updateStatus(mapEl.dataset.locatingLabel);

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                const { latitude, longitude } = pos.coords;

                map.setView([latitude, longitude], Math.max(map.getZoom(), 17));
                setParkingMarker(latitude, longitude);
                updateStatus(mapEl.dataset.locationCapturedLabel, 'success');
                document.getElementById('parking-save-form').submit();
            },
            function () {
                updateStatus(mapEl.dataset.locationErrorLabel, 'danger');
                currentLocationBtn.disabled = false;
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });
}
}
