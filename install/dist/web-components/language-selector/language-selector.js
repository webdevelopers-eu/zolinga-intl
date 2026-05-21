import WebComponent from '/dist/system/js/web-component.js';

// Load component CSS
const cssUrl = new URL('./language-selector.css?rev=' + (document.documentElement.dataset.revision ?? (new Date).getTime()) , import.meta.url);
if (!document.querySelector(`link[href="${cssUrl.pathname}"], #language-selector-style`)) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = cssUrl.href;
    link.setAttribute('id', 'language-selector-style');
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
        this.#updateStoredLanguages();
        this.#updateLanguageOrder();
    }

    connectedCallback() {
        super.connectedCallback?.();
        this.#popup = this.querySelector('.language-popup');
        this.addEventListener('click', () => this.#togglePopup());
        this.ready();
    }

    disconnectedCallback() {
        super.disconnectedCallback?.();
        this.removeEventListener('click', () => this.#togglePopup());
    }

    #getStoredLangauges() {
        const stored = localStorage.getItem('chosenLanguages') || '[]';
        return JSON.parse(stored);
    }

    #updateStoredLanguages() {
        const newLanguage = document.documentElement.lang;
        const storedLanguages = this.#getStoredLangauges();
        const updatedLanguages = [newLanguage, ...storedLanguages.filter(lang => lang !== newLanguage)];
        localStorage.setItem('chosenLanguages', JSON.stringify(updatedLanguages));
    }

    #updateLanguageOrder() {
        const storedLanguages = this.#getStoredLangauges();
        storedLanguages.forEach(lang => {
            const link = this.querySelector(`.language[data-locale="${lang}"]`);
            if (link) {
                link.style.order = storedLanguages.indexOf(lang) - storedLanguages.length;
            }
        });
    }

    #togglePopup(state) {
        if (!this.#popup) return;

        this.#popup.removeAttribute('hidden');
        this.#popup.togglePopover();
    }

    #handleClickOutside(e) {
        if (!this.#popup.contains(e.target) && e.target !== this) {
            this.#togglePopup(false);
        }
    }
}
