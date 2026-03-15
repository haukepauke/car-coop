import L from 'leaflet';

const mapEl = document.getElementById('parking-map');
if (mapEl) {

const label    = mapEl.dataset.label;
const hasLocation = mapEl.dataset.lat !== '' && mapEl.dataset.lng !== '';
const defaultLat  = hasLocation ? parseFloat(mapEl.dataset.lat) : 51.505;
const defaultLng  = hasLocation ? parseFloat(mapEl.dataset.lng) : -0.09;
const zoom        = hasLocation ? 17 : 13;

const map = L.map('parking-map').setView([defaultLat, defaultLng], zoom);

L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}).addTo(map);

if (hasLocation) {
    L.marker([defaultLat, defaultLng])
        .addTo(map)
        .bindPopup(label)
        .openPopup();
}

const saveBtn  = document.getElementById('parking-save-btn');
const latInput = document.getElementById('parking-save-lat');
const lngInput = document.getElementById('parking-save-lng');

let clickMarker = null;

map.on('click', function (e) {
    const { lat, lng } = e.latlng;

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
});
}
