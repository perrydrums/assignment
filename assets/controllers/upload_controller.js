import { Controller } from '@hotwired/stimulus';

const EXTENSIONS = ['xlsx', 'xls'];

/*
 * Drag-and-drop selection of the workbook, a quick client-side check and a busy state while the
 * analysis runs. The server validates the upload again.
 */
export default class extends Controller {
    static targets = ['input', 'dropzone', 'selected', 'fileName', 'fileSize', 'error', 'submitLabel'];
    static values = { maxBytes: Number };

    connect() {
        this.update();
    }

    dragOver(event) {
        event.preventDefault();
        this.dropzoneTarget.classList.add('is-dragover');
    }

    dragLeave(event) {
        if (!this.dropzoneTarget.contains(event.relatedTarget)) {
            this.dropzoneTarget.classList.remove('is-dragover');
        }
    }

    drop(event) {
        event.preventDefault();
        this.dropzoneTarget.classList.remove('is-dragover');

        const file = event.dataTransfer?.files?.[0];
        if (!file) {
            return;
        }

        const transfer = new DataTransfer();
        transfer.items.add(file);
        this.inputTarget.files = transfer.files;
        this.update();
    }

    clear() {
        this.inputTarget.value = '';
        this.update();
        this.inputTarget.focus();
    }

    update() {
        const file = this.selectedFile();
        this.element.classList.toggle('has-file', file !== null);
        this.selectedTarget.hidden = file === null;
        this.showError(file ? this.problemWith(file) : null);

        if (file) {
            this.fileNameTarget.textContent = file.name;
            this.fileSizeTarget.textContent = formatBytes(file.size);
        }
    }

    submit(event) {
        const file = this.selectedFile();
        const problem = file ? this.problemWith(file) : 'Choose an Excel workbook (.xlsx or .xls) to analyse.';
        if (problem) {
            event.preventDefault();
            this.showError(problem);
            this.shake(this.dropzoneTarget);

            return;
        }

        this.element.classList.add('is-submitting');
        this.element.setAttribute('aria-busy', 'true');
        this.submitLabelTarget.textContent = 'Analysing…';
    }

    selectedFile() {
        return this.inputTarget.files?.[0] ?? null;
    }

    problemWith(file) {
        const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';
        if (!EXTENSIONS.includes(extension)) {
            return `Only Excel workbooks (.xlsx or .xls) can be analysed, not "${file.name}".`;
        }

        if (this.hasMaxBytesValue && file.size > this.maxBytesValue) {
            return `This file is ${formatBytes(file.size)}; workbooks can be at most ${formatBytes(this.maxBytesValue)}.`;
        }

        return null;
    }

    showError(message) {
        this.errorTarget.hidden = message === null;
        this.errorTarget.textContent = message ?? '';
    }

    shake(element) {
        element.classList.remove('is-shaking');
        void element.offsetWidth;
        element.classList.add('is-shaking');
    }
}

function formatBytes(bytes) {
    if (bytes < 1000) {
        return `${bytes} bytes`;
    }

    return bytes < 1000 * 1000 ? `${(bytes / 1000).toFixed(1)} kB` : `${(bytes / 1000 / 1000).toFixed(1)} MB`;
}
