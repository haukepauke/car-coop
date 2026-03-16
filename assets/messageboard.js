import Quill from 'quill';

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
