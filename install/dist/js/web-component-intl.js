import WebComponent from '/dist/system/js/web-component.js';

/**
 * IPD Alerts admin pane
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-05-03 
 */
export default class WebComponentIntl extends WebComponent {
    constructor() {
        super();
    }

    rewriteURL(url, type) {
        url = super.rewriteURL(url, type);

        const lang = document.documentElement.lang || 'en-US';
        if (lang !== 'en-US' && lang.match(/^[a-z]{2}-[A-Z]{2}$/)) {
            // Insert lang just before file name extension (if any)
            const o = new URL(url, window.location);
            const path = o.pathname;
            const extIdx = path.lastIndexOf('.');
            if (extIdx > -1) {
                o.pathname = path.substring(0, extIdx) + '.' + lang + path.substring(extIdx);
                url = o.toString();
            }
        }
        
        return url;
    }
}