/**
 * This file is part of the Zolinga Intl module for Zolinga.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-16
 */

// @todo production use .min.js file
import i18n from '/dist/zolinga-intl/vendor/gettext.js/gettext.esm.js';

// Ideally we should use #DOMAIN but it does not work. It runs the module 
// twice but the import.meta.url is the same. So we use the query string ?DOMAIN .
const domain = import.meta.url.replace(/^.*[?#]/, '');

// Get cookie 'lang'
const lang = (function getLangCookie() {
    const cookies = document.cookie.split(';');
    for (let i = 0; i < cookies.length; i++) {
        const cookie = cookies[i].trim();
        if (cookie.startsWith('lang=')) {
            return cookie.substring(5); // Length of 'lang=' is 5
        }
    }
    return 'en-US'; // Return en-US if "lang" cookie is not found
})();

if (!domain) {
    throw new Error('ZOLINGA GETTEXT: Domain is required. Use import "/dist/zolinga-intl/gettext.js?{MOUDLE_NAME}"');
}


// Download catalog from ${domain}/locale/${lang}
let data;
if (lang == 'en-US') {
    data = {};
} else {
    data = await fetch(`/dist/${domain}/locale/${lang}.json`)
        .then(response => response.json())
        .catch(() => {
            console.error(`ZOLINGA GETTEXT: Catalog for domain ${domain} lang ${lang} not found. Using en-US.`);
            return false;
        });
}

// It wants only lang code...
const intl = i18n({
    "domain": domain,
    "locale": lang.slice(0, 2),
    "plural_forms": "nplurals=2; plural=(n != 1);"
});
if (data) intl.loadJSON(data, domain);

export default intl;

// dcnpgettext(domain, msgctxt, msgid, msgid_plural, n)	
// Translate a potentially pluralizable string, potentially specified by context, 
// and potentially of a different domain (as specified in setMessages or loadJSON).

export function gettext(msgid, ...args) {
    return intl.dcnpgettext(domain, undefined, msgid, undefined, undefined, ...args);
}

export function ngettext(msgid, msgid_plural, n, ...args) {
    return intl.dcnpgettext(domain, undefined, msgid, msgid_plural, n, ...args);
}

export { gettext as __, ngettext as _n };