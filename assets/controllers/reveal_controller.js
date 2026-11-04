import { Controller } from '@hotwired/stimulus';

/*
 * Fades items in (staggered) the first time the element scrolls into view. Charts inside also hold
 * their entrance animations until then.
 */
export default class extends Controller {
    static targets = ['item'];

    connect() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const items = this.hasItemTarget ? this.itemTargets : [...this.element.children];
        items.forEach((item, index) => {
            item.classList.add('reveal-item');
            item.style.setProperty('--reveal-index', String(index));
        });
        this.element.classList.add('reveal');

        this.observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                this.element.classList.add('is-revealed');
                this.observer.disconnect();
            }
        }, { threshold: 0.12 });
        this.observer.observe(this.element);
    }

    disconnect() {
        this.observer?.disconnect();
    }
}
