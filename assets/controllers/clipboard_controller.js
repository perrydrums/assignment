import { Controller } from '@hotwired/stimulus';

/*
 * Copies a text to the clipboard and briefly confirms it in the button label.
 */
export default class extends Controller {
    static targets = ['label'];
    static values = { text: String };

    async copy() {
        try {
            await navigator.clipboard.writeText(this.textValue);
        } catch {
            // The Clipboard API is only available on HTTPS (and localhost).
            copyWithSelection(this.textValue);
        }

        this.confirm();
    }

    confirm() {
        this.originalLabel ??= this.labelTarget.textContent;
        this.labelTarget.textContent = 'Copied';
        this.element.classList.add('is-copied');

        clearTimeout(this.timeout);
        this.timeout = setTimeout(() => {
            this.labelTarget.textContent = this.originalLabel;
            this.element.classList.remove('is-copied');
        }, 1800);
    }

    disconnect() {
        clearTimeout(this.timeout);
    }
}

function copyWithSelection(text) {
    const field = Object.assign(document.createElement('textarea'), { value: text, readOnly: true });
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.append(field);
    field.select();
    document.execCommand('copy');
    field.remove();
}
