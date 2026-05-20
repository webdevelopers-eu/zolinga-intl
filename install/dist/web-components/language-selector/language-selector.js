import WebComponent from '/dist/system/js/web-component.js';

// Load component CSS
const cssUrl = new URL('./language-selector.css', import.meta.url);
if (!document.querySelector(`link[href="${cssUrl.pathname}"]`)) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = cssUrl.href;
    document.head.appendChild(link);
}

/**
 * Language selector web component.
 *
 * Displays the current language name inline. On click, shows a popup
 * with available languages using CSS anchor positioning.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-05-15
 */
export default class LanguageSelector extends WebComponent {
    #popup = null;
    #boundClickOutside = null;

    constructor() {
        super();
    }

    connectedCallback() {
        super.connectedCallback?.();
        this.#popup = this.querySelector('.language-popup');
        this.addEventListener('click', () => this.#togglePopup());
        this.ready();
    }

    #togglePopup(state) {
        if (!this.#popup) return;

        if (this.#popup.matches('[open]')) {
            this.#popup.removeAttribute('open');
        } else {
            this.#popup.setAttribute('open', '');
        }
    }

    #handleClickOutside(e) {
        if (!this.#popup.contains(e.target) && e.target !== this) {
            this.#togglePopup(false);
        }
    }
}
