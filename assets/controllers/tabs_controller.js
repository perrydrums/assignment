import { Controller } from '@hotwired/stimulus';

/*
 * Accessible tabs (arrow keys, Home and End) with a sliding indicator.
 */
export default class extends Controller {
    static targets = ['tab', 'panel', 'indicator'];

    connect() {
        this.element.classList.add('has-tab-indicator');
        this.resizeObserver = new ResizeObserver(() => this.moveIndicator(false));
        this.resizeObserver.observe(this.tabTargets[0].parentElement);
    }

    disconnect() {
        this.resizeObserver?.disconnect();
    }

    select({ params: { name } }) {
        this.show(name);
    }

    showFromEvent({ detail }) {
        if (detail?.tab) {
            this.show(detail.tab);
        }
    }

    navigate(event) {
        const index = this.tabTargets.indexOf(event.currentTarget);
        const last = this.tabTargets.length - 1;
        const next = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: last }[event.key];
        if (next === undefined) {
            return;
        }

        event.preventDefault();
        const tab = this.tabTargets[(next + last + 1) % (last + 1)];
        tab.focus();
        this.show(tab.dataset.tabsNameParam);
    }

    show(name) {
        this.tabTargets.forEach((tab) => {
            const selected = tab.dataset.tabsNameParam === name;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
        this.panelTargets.forEach((panel) => {
            panel.hidden = panel.dataset.name !== name;
        });
        this.moveIndicator(true);
    }

    moveIndicator(animate) {
        const tab = this.tabTargets.find((candidate) => candidate.getAttribute('aria-selected') === 'true');
        if (!this.hasIndicatorTarget || !tab) {
            return;
        }

        this.indicatorTarget.classList.toggle('is-instant', !animate);
        this.indicatorTarget.style.width = `${tab.offsetWidth}px`;
        this.indicatorTarget.style.transform = `translateX(${tab.offsetLeft}px)`;
    }
}
