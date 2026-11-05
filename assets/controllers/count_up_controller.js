import { Controller } from '@hotwired/stimulus';

/*
 * Counts a server-rendered number up from zero. The final text is the server's, so no-JS and
 * reduced-motion visitors see exactly the same value, and screen readers only get the final value.
 */
export default class extends Controller {
    static values = {
        to: Number,
        decimals: { type: Number, default: 0 },
        duration: { type: Number, default: 1400 },
        delay: { type: Number, default: 250 },
    };

    connect() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const finalText = this.element.textContent;
        const announcement = Object.assign(document.createElement('span'), { className: 'visually-hidden', textContent: finalText });
        this.element.after(announcement);
        this.element.setAttribute('aria-hidden', 'true');

        const start = performance.now() + this.delayValue;
        const tick = (now) => {
            const progress = Math.min(1, Math.max(0, (now - start) / this.durationValue));
            const eased = 1 - (1 - progress) ** 4;
            this.element.textContent = progress < 1 ? formatNumber(this.toValue * eased, this.decimalsValue) : finalText;
            if (progress < 1) {
                this.frame = requestAnimationFrame(tick);
            }
        };

        this.finish = () => {
            cancelAnimationFrame(this.frame);
            this.element.textContent = finalText;
        };
        window.addEventListener('beforeprint', this.finish);

        this.element.textContent = formatNumber(0, this.decimalsValue);
        this.frame = requestAnimationFrame(tick);
    }

    disconnect() {
        cancelAnimationFrame(this.frame);
        window.removeEventListener('beforeprint', this.finish);
    }
}

function formatNumber(value, decimals) {
    return new Intl.NumberFormat(document.documentElement.lang || 'en', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(value);
}
