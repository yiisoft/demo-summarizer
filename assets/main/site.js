if (document.querySelector('[data-poll="1"]')) {
    const activeStatuses = ['uploaded', 'queued', 'extracting', 'summarizing'];

    const poll = () => {
        document.querySelectorAll('[data-document-row], [data-document-detail]').forEach((target) => {
            const id = target.getAttribute('data-document-row') || target.getAttribute('data-document-detail');
            const statusUrl = target.getAttribute('data-status-url');
            const refreshOnTerminal = target.getAttribute('data-refresh-on-terminal') === '1';
            const previousStatus = target.getAttribute('data-current-status');

            if (!id || !statusUrl) {
                return;
            }

            fetch(statusUrl)
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

                    target.setAttribute('data-current-status', data.status);

                    if (progress) {
                        progress.textContent = `${data.progress}%`;
                    }

                    if (bar) {
                        bar.style.width = `${data.progress}%`;
                    }

                    if (
                        refreshOnTerminal
                        && previousStatus
                        && activeStatuses.includes(previousStatus)
                        && !activeStatuses.includes(data.status)
                    ) {
                        window.location.reload();
                    }
                });
        });
    };

    setInterval(poll, 500);
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
            event.stopPropagation();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'copy';
            }
            dropzone.classList.add('is-drag-over');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            event.stopPropagation();
            dropzone.classList.remove('is-drag-over');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        const droppedFiles = Array.from(event.dataTransfer?.files || []);

        if (droppedFiles.length === 0) {
            return;
        }

        const transfer = new DataTransfer();

        droppedFiles.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', {bubbles: true}));
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
