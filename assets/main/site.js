if (document.querySelector('[data-poll="1"]')) {
    const poll = () => {
        document.querySelectorAll('[data-document-row]').forEach((row) => {
            const id = row.getAttribute('data-document-row');
            fetch(`/documents/${id}/status`)
                .then((response) => response.ok ? response.json() : null)
                .then((data) => {
                    if (!data) {
                        return;
                    }

                    const status = document.querySelector(`[data-status="${id}"]`);
                    const progress = document.querySelector(`[data-progress="${id}"]`);
                    const bar = document.querySelector(`[data-progress-bar="${id}"]`);

                    if (status) {
                        status.textContent = data.status;
                        status.className = `status status-${data.status}`;
                    }

                    if (progress) {
                        progress.textContent = `${data.progress}%`;
                    }

                    if (bar) {
                        bar.style.width = `${data.progress}%`;
                    }
                });
        });
    };

    setInterval(poll, 2000);
}

document.querySelectorAll('[data-upload-form]').forEach((form) => {
    const dropzone = form.querySelector('[data-upload-dropzone]');
    const input = form.querySelector('[data-upload-input]');
    const fileList = form.querySelector('[data-upload-file-list]');

    if (!dropzone || !input || !fileList) {
        return;
    }

    const updateFileList = () => {
        const files = Array.from(input.files || []);
        fileList.textContent = files.length === 0
            ? 'No files selected.'
            : files.map((file) => file.name).join(', ');
    };

    input.addEventListener('change', updateFileList);

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.add('is-drag-over');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-drag-over');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        const files = event.dataTransfer?.files;

        if (!files || files.length === 0) {
            return;
        }

        input.files = files;
        updateFileList();
    });
});

document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const message = form.getAttribute('data-confirm');

        if (message && !confirm(message)) {
            event.preventDefault();
        }
    });
});
