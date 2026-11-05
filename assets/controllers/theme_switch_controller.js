import { Controller } from '@hotwired/stimulus';

/*
 * Switches the colour theme without reloading, so an analysis on screen is kept. The choice is stored
 * in a cookie that the server reads to render the next page in the same theme.
 */
export default class extends Controller {
    static targets = ['toggle', 'swatch', 'name', 'menu', 'option', 'logo'];

    connect() {
        this.syncThemeColor();
    }

    select({ params: { id, name, brand, logo } }) {
        document.documentElement.dataset.theme = id;
        document.cookie = `theme=${encodeURIComponent(id)}; path=/; max-age=31536000; samesite=lax`;

        this.swatchTarget.dataset.theme = id;
        this.nameTarget.textContent = name;
        this.logoTarget.src = logo;
        this.logoTarget.alt = brand;
        this.optionTargets.forEach((option) => {
            option.setAttribute('aria-pressed', String(option.dataset.themeSwitchIdParam === id));
        });
        this.syncThemeColor();
        this.menuTarget.hidePopover();
        this.toggleTarget.focus();
    }

    position(event) {
        if (event.newState !== 'open') {
            return;
        }

        const bounds = this.toggleTarget.getBoundingClientRect();
        this.menuTarget.style.top = `${Math.round(bounds.bottom + 8)}px`;
        this.menuTarget.style.right = `${Math.max(8, Math.round(document.documentElement.clientWidth - bounds.right))}px`;
    }

    syncThemeColor() {
        const dark = getComputedStyle(document.documentElement).getPropertyValue('--dark').trim();
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', dark);
    }
}
