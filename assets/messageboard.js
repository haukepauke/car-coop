import Quill from 'quill';
import { Modal } from 'bootstrap';

// ── Quill editor ─────────────────────────────────────────────
const editorEl = document.getElementById('quill-editor');
if (editorEl) {
    const quill = new Quill('#quill-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                ['clean'],
            ],
        },
    });

    const form = document.getElementById('message-form');
    form.addEventListener('submit', () => {
        document.getElementById('message-content').value = quill.root.innerHTML;
    });
}

// ── Attachment upload UI ─────────────────────────────────────
const fileInput   = document.getElementById('message-photos');
const previewArea = document.getElementById('photo-preview-area');
const addBtn      = document.getElementById('photo-add-btn');

if (fileInput && previewArea && addBtn) {
    // Use DataTransfer to maintain the file list across multiple picks
    let dt = new DataTransfer();

    addBtn.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', () => {
        for (const file of fileInput.files) {
            // Avoid duplicates by name+size
            const already = [...dt.files].some(f => f.name === file.name && f.size === file.size);
            if (!already) {
                dt.items.add(file);
            }
        }
        fileInput.files = dt.files;
        renderPreviews();
    });

    function renderPreviews() {
        // Remove all existing preview items (keep the add button)
        previewArea.querySelectorAll('.photo-preview-item').forEach(el => el.remove());

        [...dt.files].forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'photo-preview-item';

            const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

            let previewNode;
            if (extension === 'pdf') {
                const badge = document.createElement('div');
                badge.className = 'photo-preview-file';
                badge.innerHTML = '<i class="fa-regular fa-file-pdf"></i>';
                badge.title = file.name;
                previewNode = badge;
            } else {
                const objectUrl = URL.createObjectURL(file);
                const img = document.createElement('img');
                img.src = objectUrl;
                img.alt = file.name;
                img.onload = () => URL.revokeObjectURL(objectUrl);
                previewNode = img;
            }

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'photo-preview-remove';
            removeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            removeBtn.setAttribute('aria-label', 'Remove');
            removeBtn.addEventListener('click', () => {
                const newDt = new DataTransfer();
                [...dt.files].forEach((f, i) => { if (i !== index) newDt.items.add(f); });
                dt = newDt;
                fileInput.files = dt.files;
                renderPreviews();
            });

            item.appendChild(previewNode);
            item.appendChild(removeBtn);
            previewArea.insertBefore(item, addBtn);
        });
    }
}

// ── Fullscreen photo modal ───────────────────────────────────
window.openPhotoModal = function (el) {
    const url = el.dataset.photoUrl;
    const name = el.dataset.photoName;
    document.getElementById('photoModalImg').src = url;
    const downloadBtn = document.getElementById('photoDownloadBtn');
    downloadBtn.href = url;
    downloadBtn.download = name;
    new Modal(document.getElementById('photoModal')).show();
};
