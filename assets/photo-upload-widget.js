function initializePhotoUploadWidget(root) {
    if (root.dataset.photoUploadInitialized === 'true') {
        return;
    }

    const inputSelector = root.dataset.photoUploadInput;
    const buttonSelector = root.dataset.photoUploadButton;
    const markdownTargetSelector = root.dataset.photoUploadMarkdownTarget;
    const tokenContainerSelector = root.dataset.photoUploadTokenContainer;
    const tokenFieldName = root.dataset.photoUploadTokenFieldName ?? 'photoTokens[]';
    const fileInput = inputSelector ? document.querySelector(inputSelector) : null;
    const addBtn = buttonSelector ? document.querySelector(buttonSelector) : root.querySelector('.photo-add-btn');
    const markdownTarget = markdownTargetSelector ? document.querySelector(markdownTargetSelector) : null;
    const tokenContainer = tokenContainerSelector ? document.querySelector(tokenContainerSelector) : null;

    if (!(fileInput instanceof HTMLInputElement) || !(addBtn instanceof HTMLElement)) {
        return;
    }

    root.dataset.photoUploadInitialized = 'true';

    const supportsDataTransfer = typeof DataTransfer !== 'undefined';
    let dt = supportsDataTransfer ? new DataTransfer() : null;
    let fileMetadata = [];

    const placeholderUrlFor = token => `handbook-upload://${token}`;
    const escapeRegExp = value => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const normalizeAltText = filename => {
        const withoutExtension = filename.replace(/\.[^.]+$/, '');

        return withoutExtension.replace(/[\[\]\r\n]+/g, ' ').trim() || 'photo';
    };
    const generateToken = () => {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }

        return `photo-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    };

    const getFiles = () => {
        if (dt !== null) {
            return [...dt.files];
        }

        return [...fileInput.files];
    };

    function syncTokenFields() {
        if (!(tokenContainer instanceof HTMLElement)) {
            return;
        }

        tokenContainer.replaceChildren();

        fileMetadata.forEach(({ token }) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = tokenFieldName;
            input.value = token;
            tokenContainer.appendChild(input);
        });
    }

    function appendMarkdownReference({ altText, token }) {
        if (!(markdownTarget instanceof HTMLTextAreaElement)) {
            return;
        }

        const markdown = `![${altText}](${placeholderUrlFor(token)})`;
        const currentValue = markdownTarget.value.trimEnd();

        markdownTarget.value = currentValue === ''
            ? markdown
            : `${currentValue}\n\n${markdown}`;
    }

    function removeMarkdownReference(token) {
        if (!(markdownTarget instanceof HTMLTextAreaElement)) {
            return;
        }

        const pattern = new RegExp(`(?:\\r?\\n){0,2}!\\[[^\\]]*\\]\\(${escapeRegExp(placeholderUrlFor(token))}\\)(?:\\r?\\n)?`, 'g');
        markdownTarget.value = markdownTarget.value
            .replace(pattern, match => match.includes('\n\n') ? '\n\n' : '')
            .replace(/\n{3,}/g, '\n\n')
            .trimEnd();
    }

    function renderPreviews() {
        root.querySelectorAll('.photo-preview-item').forEach(element => element.remove());

        getFiles().forEach((file, index) => {
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
                const image = document.createElement('img');
                image.src = objectUrl;
                image.alt = file.name;
                image.onload = () => URL.revokeObjectURL(objectUrl);
                previewNode = image;
            }

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'photo-preview-remove';
            removeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            removeBtn.setAttribute('aria-label', 'Remove');
            removeBtn.addEventListener('click', () => {
                if (dt !== null) {
                    const nextDt = new DataTransfer();
                    [...dt.files].forEach((existingFile, fileIndex) => {
                        if (fileIndex !== index) {
                            nextDt.items.add(existingFile);
                        }
                    });
                    dt = nextDt;
                    fileInput.files = dt.files;
                    const [removedMetadata] = fileMetadata.splice(index, 1);
                    if (removedMetadata) {
                        removeMarkdownReference(removedMetadata.token);
                    }
                } else {
                    fileInput.value = '';
                    fileMetadata.forEach(({ token }) => removeMarkdownReference(token));
                    fileMetadata = [];
                }
                syncTokenFields();
                renderPreviews();
            });

            item.appendChild(previewNode);
            item.appendChild(removeBtn);
            root.insertBefore(item, addBtn);
        });
    }

    addBtn.addEventListener('click', () => {
        if (!(addBtn instanceof HTMLLabelElement)) {
            fileInput.click();
        }
    });

    addBtn.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            fileInput.click();
        }
    });

    fileInput.addEventListener('change', () => {
        if (dt !== null) {
            for (const file of fileInput.files) {
                const alreadyExists = [...dt.files].some(
                    existingFile => existingFile.name === file.name && existingFile.size === file.size
                );

                if (!alreadyExists) {
                    dt.items.add(file);
                    const metadata = {
                        token: generateToken(),
                        altText: normalizeAltText(file.name),
                    };
                    fileMetadata.push(metadata);
                    appendMarkdownReference(metadata);
                }
            }

            fileInput.files = dt.files;
        } else {
            fileMetadata.forEach(({ token }) => removeMarkdownReference(token));
            fileMetadata = [...fileInput.files].map(file => {
                const metadata = {
                    token: generateToken(),
                    altText: normalizeAltText(file.name),
                };
                appendMarkdownReference(metadata);

                return metadata;
            });
        }

        syncTokenFields();
        renderPreviews();
    });

    renderPreviews();
}

function bootPhotoUploadWidgets() {
    document.querySelectorAll('[data-photo-upload-widget]').forEach(initializePhotoUploadWidget);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootPhotoUploadWidgets, { once: true });
} else {
    bootPhotoUploadWidgets();
}
