/* Dedicated uploader script for 3D models */
(function () {
    function initUploader() {
        const form = document.querySelector('.tpq-shortcode-form');
        const dropzone = form ? form.querySelector('.tpq-upload-zone') : null;
        const input = form ? form.querySelector('.tpq-upload-input') : null;

        if (!dropzone || !input) {
            return;
        }

        dropzone.addEventListener('dragover', (event) => {
            event.preventDefault();
            dropzone.classList.add('is-dragging');
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('is-dragging');
        });

        dropzone.addEventListener('drop', (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-dragging');

            const file = event.dataTransfer.files && event.dataTransfer.files[0];
            if (validateSelectedFile(file)) {
                uploadModelFile(file);
            }
        });

        input.addEventListener('change', (event) => {
            const file = event.target.files && event.target.files[0];
            if (validateSelectedFile(file)) {
                uploadModelFile(file);
            }
        });
    }

    function validateSelectedFile(file) {
        if (!file) {
            return false;
        }

        const allowed = Array.isArray(tpq.allowedExtensions) ? tpq.allowedExtensions : ['stl', 'obj', '3mf'];
        const extension = file.name.split('.').pop().toLowerCase();
        const maxMb = tpq.maxUploadSize || 10;

        if (!allowed.includes(extension)) {
            updateUploadStatus(false, (tpq.messages && tpq.messages.unsupportedType) || 'Unsupported file type.');
            return false;
        }

        if (file.size > maxMb * 1024 * 1024) {
            updateUploadStatus(false, (tpq.messages && tpq.messages.sizeExceeded) || 'File exceeds allowed size.');
            return false;
        }

        return true;
    }

    function uploadModelFile(file) {
        const formData = new FormData();
        formData.append('model_file', file);
        formData.append('action', 'tpq_upload_model');
        formData.append('nonce', tpq.nonces && tpq.nonces.upload ? tpq.nonces.upload : '');

        const xhr = new XMLHttpRequest();
        xhr.open('POST', tpq.ajaxUrl, true);

        xhr.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                updateUploadProgress(event.loaded / event.total);
            }
        });

        xhr.addEventListener('load', () => {
            let response = null;

            try {
                response = JSON.parse(xhr.responseText);
            } catch (error) {
                handleUploadError('Invalid server response.');
                return;
            }

            handleUploadSuccess(response);
        });

        xhr.addEventListener('error', () => handleUploadError());
        xhr.send(formData);
    }

    function updateUploadProgress(percent) {
        const bar = document.querySelector('.tpq-upload-progress-bar');
        if (bar) {
            bar.style.width = `${Math.round(percent * 100)}%`;
        }
    }

    function handleUploadSuccess(response) {
        if (!response || !response.success) {
            handleUploadError((response && response.data && response.data.message) || 'Upload failed.');
            return;
        }

        const data = response.data || {};

        updateUploadStatus(true, (tpq.messages && tpq.messages.uploadComplete) || 'Upload complete.');

        document.querySelectorAll('.tpq-model-id').forEach((input) => {
            input.value = data.model_id || '';
        });

        document.querySelectorAll('.tpq-model-volume').forEach((input) => {
            input.value = data.volume || '';
        });

        document.querySelectorAll('.tpq-model-size').forEach((input) => {
            input.value = data.size || '';
        });

        document.querySelectorAll('.tpq-model-url').forEach((input) => {
            input.value = data.url || '';
        });

        document.dispatchEvent(new CustomEvent('tpqModelUploaded', { detail: data }));
    }

    function handleUploadError(message) {
        updateUploadStatus(false, message || ((tpq.messages && tpq.messages.uploadFailed) || 'Upload failed. Please try again.'));
    }

    function updateUploadStatus(success, message) {
        const status = document.querySelector('.tpq-upload-status');
        if (!status) {
            return;
        }

        status.textContent = message;
        status.classList.toggle('tpq-is-success', !!success);
    }

    document.addEventListener('DOMContentLoaded', initUploader);
})();