import { Controller } from '@hotwired/stimulus';

/*
 * Tooltips and selection for the server-rendered SVG charts. Selecting an element dispatches the window
 * event named in its data-chart-event-name-param; the tabs and tables listen for those events.
 */
export default class extends Controller {
    static targets = ['frame', 'tooltip'];

    show(event) {
        const element = event.target.closest?.('[data-tooltip]');
        if (!element || !this.frameTarget.contains(element)) {
            return;
        }

        const anchor = element.querySelector('[data-tooltip-anchor]') ?? element;
        const frame = this.frameTarget.getBoundingClientRect();
        const box = anchor.getBoundingClientRect();

        this.tooltipTarget.textContent = element.dataset.tooltip;
        this.tooltipTarget.style.left = `${box.left + box.width / 2 - frame.left}px`;
        this.tooltipTarget.style.top = `${box.top - frame.top}px`;
        this.tooltipTarget.hidden = false;
    }

    hide(event) {
        if (!event.relatedTarget?.closest?.('[data-tooltip]')) {
            this.tooltipTarget.hidden = true;
        }
    }

    select({ currentTarget, params: { eventName, detail } }) {
        this.clearSelection();
        currentTarget.classList.add('is-selected');
        window.dispatchEvent(new CustomEvent(eventName, { detail }));
    }

    selectWithKeyboard(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            this.select(event);
        }
    }

    clearSelection() {
        this.element.querySelectorAll('.is-selected').forEach((element) => element.classList.remove('is-selected'));
    }
}
