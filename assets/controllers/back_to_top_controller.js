import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.toggle();
    }

    toggle() {
        this.element.classList.toggle('is-visible', window.scrollY > 640);
    }

    scrollUp() {
        const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: smooth ? 'smooth' : 'auto' });
    }
}
