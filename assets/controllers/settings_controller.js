import { Controller } from '@hotwired/stimulus';

/*
 * Restores the uploaded test: every question counts as scored, and the grading scale returns
 * to the one used for the first analysis.
 */
export default class extends Controller {
    undo(event) {
        event.preventDefault();
        this.element.querySelectorAll('select[name*="[adjustments]"]').forEach((select) => {
            select.value = 'keep';
        });
        this.element.querySelector('[data-action="caesura#reset"]')?.click();
        this.element.requestSubmit();
    }
}
