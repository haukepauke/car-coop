import { Modal } from 'bootstrap';

function derivePhotoName(url) {
    try {
        const parsedUrl = new URL(url, window.location.origin);
        const segments = parsedUrl.pathname.split('/').filter(Boolean);

        return decodeURIComponent(segments.at(-1) ?? 'photo');
    } catch {
        return 'photo';
    }
}

function getPhotoData(trigger) {
    const url = trigger.dataset.photoUrl || trigger.getAttribute('src') || '';
    const name = trigger.dataset.photoName || derivePhotoName(url);

    return { url, name };
}

export function openPhotoModal(triggerOrUrl, fallbackName = 'photo') {
    const modalEl = document.getElementById('photoModal');
    const modalImg = document.getElementById('photoModalImg');
    const downloadBtn = document.getElementById('photoDownloadBtn');

    if (!(modalEl instanceof HTMLElement) || !(modalImg instanceof HTMLImageElement) || !(downloadBtn instanceof HTMLAnchorElement)) {
        return;
    }

    const photo = typeof triggerOrUrl === 'string'
        ? { url: triggerOrUrl, name: fallbackName }
        : getPhotoData(triggerOrUrl);

    if (photo.url === '') {
        return;
    }

    modalImg.src = photo.url;
    modalImg.alt = photo.name;
    downloadBtn.href = photo.url;
    downloadBtn.download = photo.name;

    new Modal(modalEl).show();
}

function decorateHandbookImages() {
    document.querySelectorAll('.handbook-markdown img').forEach((image) => {
        if (!(image instanceof HTMLImageElement)) {
            return;
        }

        image.classList.add('message-photo-thumb', 'js-photo-modal-trigger');
        image.dataset.photoUrl = image.currentSrc || image.getAttribute('src') || '';
        image.dataset.photoName = image.dataset.photoName || derivePhotoName(image.dataset.photoUrl);
    });
}

function initializePhotoModalTriggers() {
    decorateHandbookImages();

    document.addEventListener('click', (event) => {
        const trigger = event.target instanceof Element
            ? event.target.closest('.js-photo-modal-trigger')
            : null;

        if (!(trigger instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();
        openPhotoModal(trigger);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePhotoModalTriggers, { once: true });
} else {
    initializePhotoModalTriggers();
}

window.openPhotoModal = openPhotoModal;
