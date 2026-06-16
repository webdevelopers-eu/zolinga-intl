<?php

declare(strict_types=1);

namespace Zolinga\Intl;

use Zolinga\System\Events\{Event, ServiceInterface};
use Locale;
use NumberFormatter;

use const Zolinga\System\IS_CLI;
use const Zolinga\System\ROOT_DIR;

/**
 * Language and gettext service.
 * 
 * @property string|null $tag The current language tag selected and canonicalized from $api->config['intl']['locales']. E.g. 'en_US@currency=USD'.
 * @property string|null $locale The locale code from the current language tag in format language_REGION. E.g. 'en_US'.
 * @property string|null $jsLocale The locale code from the current language tag in format language-REGION. E.g. 'en-US'.
 * @property string|null $lang The primary language code from the current language tag. E.g. 'en'.
 * @property-read string|null $region The region code from the current language tag. E.g. 'US'.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-15
 */
class LocaleService implements ServiceInterface
{

    /**
     * The current language tag selected and canonicalized from $api->config['intl']['locales'].
     *
     * @var string|null
     */
    private ?string $tag = null;

    /**
     * The primary language code from the current language tag.
     *
     * @var string|null
     */
    private ?string $lang = null;

    /**
     * The locale code from the current language tag in format language_REGION.
     * 
     * Example: en_US
     *
     * @var string|null
     */
    private ?string $locale = null;

    /**
     * The locale code from the current language tag in format language-REGION.
     * 
     * Example: en-US
     *
     * @var string|null
     */
    private ?string $jsLocale = null;

    /**
     * The region code from the current language tag.
     *
     * @var string|null
     */
    private ?string $region = null;

    /**
     * Tracks which domain suffixes have been initialized (idempotency guard).
     *
     * @var array<string, bool>
     */
    private array $domainsInitialized = [];


    /**
     * List of supported language tags. Canonicalized version of $api->config['intl']['locales'].
     * 
     * @var array<string>
     */
    public readonly array $supportedTags;


    /**
     * List of supported locales in the format tag => language_REGION.
     * 
     * Example: [
     *      'en_US@currency=USD' => 'en_US', 
     *      'cs_CZ@UTF8' => 'cs_CZ', 
     *      'de_DE' => 'de_DE'
     * ]
     * 
     * Note: the language tag is canonicalized version of $api->config['intl']['locales'].
     *
     * @var array<string,string> $supportedLocales array of language tag => language_REGION
     */
    public readonly array $supportedLocales;

    /**
     * List of supported languages.
     * 
     * Example: [
     *   'en_US@currency=USD' => 'en',
     *   'cs_CZ@UTF8' => 'cs',
     *   'de_DE' => 'de'
     * ];
     * 
     * @var array<string> $supportedLangs array of language tag
     */
    public readonly array $supportedLangs;

    /**
     * List of supported language names. 
     * 
     * Example: ['en_US' => 'English', 'cs_CZ' => 'čeština', 'de_DE' => 'Deutsch']
     *
     * @var array<string, string> $supportedLangNames array of language tag => localized language name
     */
    public readonly array $supportedLangNames;

    /**
     * List of supported locale names. 
     * 
     * Example: ['en_US' => 'English (United States)', 'cs_CZ' => 'čeština (Česká republika)', 'de_DE' => 'Deutsch (Deutschland)']
     *
     * @var array<string, string> $supportedLocaleNames array of language tag => localized locale name (territory name in parentheses)
     */
    public readonly array $supportedLocaleNames;

    /**
     * List of supported region names. 
     * 
     * Example: ['en_US' => 'United States', 'cs_CZ' => 'Česká republika', 'de_DE' => 'Deutschland']
     *
     * @var array<string, string> $supportedRegionNames array of language tag => localized region name
     */
    public readonly array $supportedRegionNames;

    /**
     * The primary locale (first supported locale) in language_REGION format. E.g. 'en_US'.
     */
    public string $primaryLocale {
        get { return array_values($this->supportedLocales)[0] ?? 'en_US'; }
    }

    /**
     * The primary language code (first supported locale) E.g. 'en'.
     */
    public string $primaryLang {
        get { return array_values($this->supportedLangs)[0] ?? 'en'; }
    }

    public function __construct()
    {
        global $api;

        // Format all
        $this->supportedTags = array_values(array_map(fn ($tag) => Locale::canonicalize($tag), $api->config['intl']['locales']));

        $this->supportedLangNames = array_combine(
            $this->supportedTags,
            array_map(fn ($tag) => Locale::getDisplayLanguage($tag, $tag), $this->supportedTags)
        );
        $this->supportedLocaleNames = array_combine(
            $this->supportedTags,
            array_map(fn ($tag) => Locale::getDisplayName($tag, $tag), $this->supportedTags)
        );
        $this->supportedRegionNames = array_combine(
            $this->supportedTags,
            array_map(fn ($tag) => Locale::getDisplayRegion($tag, $tag), $this->supportedTags)
        );
        $this->supportedLocales = array_combine(
            $this->supportedTags,
            array_map(
                fn ($tag) => Locale::getPrimaryLanguage($tag) . '_' . Locale::getRegion($tag),
                $this->supportedTags
            )
        );
        $this->supportedLangs = array_combine(
            $this->supportedTags,
            array_map(
                fn ($tag) => Locale::getPrimaryLanguage($tag),
                $this->supportedTags
            )
        );

        $this->initCurrentLanguage();
    }

    public function onSystemStart(Event $event): void
    {
        // Nothing to do here, gettext domains are initialized lazily when the language is set.
        // We just need to have this method to be able to listen to the system:start event and initialize gettext before any other module tries to use it.
        // Withoug initializing locales in constructor if somebody called gettext() before $api->locale
        // is fully initialized, then gettext would not work. So we must init it always before anything else.
    }

    /**
     * Return localized version of a file if it exists.
     * 
     * E.g. 
     * 
     *   $api->locale->getLocalizedFile('test/my.html'); 
     *   // returns test/my.en-US.html if it exists and language is en_US
     *
     * @param string $file
     * @return string
     */
    public function getLocalizedFile(string $file): string
    {
        $lang = $this->jsLocale;
        $try = preg_replace('/(\.[a-z]+)$/', ".{$lang}\\1", $file);
        $file = file_exists($try) ? $try : $file;
        return $file;
    }

    /**
     * Get localized URLs for the current request path across all supported languages.
     * 
     * Returns an array mapping each supported tag to its localized URL.
     * The current path has its leading language segment replaced with each supported language.
     * 
     * Example:
     * 
     *   // Current URL: /en/contact?foo=bar
     *   $urls = $api->locale->getLocalizedUrls();
     *   // ['en_US' => '/en/contact?foo=bar', 'cs_CZ' => '/cs/contact?foo=bar', 'de_DE' => '/de/contact?foo=bar']
     *
     * @param string|null $path Path to localize (defaults to current REQUEST_URI)
     * @return array<string, string> Map of tag => localized URL
     */
    public function getLocalizedUrls(?string $path = null): array
    {
        $currentUri = $path ?? $_SERVER['REQUEST_URI'] ?? '/';
        $currentPath = parse_url($currentUri, PHP_URL_PATH) ?: '/';
        $currentQuery = parse_url($currentUri, PHP_URL_QUERY) ?: '';

        $pathWithoutLang = $this->stripLangFromUrlPath($currentPath);

        $urls = [];
        foreach ($this->supportedTags as $tag) {
            $langCode = \Locale::getPrimaryLanguage($tag);
            $link = '/' . $langCode . $pathWithoutLang;
            if ($currentQuery) {
                $link .= '?' . $currentQuery;
            }
            $urls[$tag] = $link;
        }

        return $urls;
    }

    /**
     * Remove leading language segment from a URL path if it matches any of the supported languages.
     * 
     * Example:
     * 
     *  $api->locale->stripLangFromUrlPath('/en/contact'); // returns '/contact' if 'en' is a supported language
     *
     * @param string $url
     * @return string
     */
    public function stripLangFromUrlPath(string $url): string
    {
        return preg_replace(
            '#^/(' . implode('|', $this->supportedLangs) . ')(?=/|$)#',
            '',
            $url
        ) ?: '/';
    }

    public function __get(string $name): ?string
    {
        switch ($name) {
            case 'tag':
            case 'lang':
            case 'locale':
            case 'jsLocale':
            case 'region':
                return $this->$name;
            default:
                throw new \Exception("The property $name does not exist.");
        }
    }

    public function __set(string $name, mixed $value): void
    {
        switch ($name) {
            case 'locale':
            case 'jsLocale':
            case 'tag':
                $this->setTag($value);
                break;
            case 'lang':
                $matches = array_filter(
                    $this->supportedLocales, 
                    fn ($lang) => Locale::filterMatches($lang, $value, false)
                );
                if (!count($matches)) {
                    throw new \Exception("The language '{$value}' is not supported. Supported tags: " . implode(', ', $this->supportedTags));
                } elseif (count($matches) > 1) {
                    throw new \Exception("The language '{$value}' is ambiguous. Supported tags: " . implode(', ', $this->supportedTags));
                }
                $this->setTag(current($matches));
                break;
            default:
                throw new \Exception("The property $name is read-only.");
        }
    }

    /**
     * Convert string to float using current or given locale.
     * 
     * Example:
     * 
     *  echo $api->locale->stringToFloat('1,234.567', 'en_US'); // will return 1234.567
     *  echo $api->locale->stringToFloat('1.234,567', 'cs_CZ'); // will return 1234.567
     * 
     *  echo $api->locale->stringToFloat('1,234', 'en_US'); // will return 1234
     *  echo $api->locale->stringToFloat('1,234', 'cs_CZ'); // will return 1.234
     *
     * @param string $string
     * @param string|null $fromLang
     * @return float
     */
    public function stringToFloat(string|int|float $string, ?string $fromLang = null): float
    {
        global $api;

        if (!is_string($string)) { // already a number or float
            return $string;
        }

        if (class_exists('NumberFormatter')) {
            $fmt = new NumberFormatter($fromLang ?? $api->locale->lang ?? setlocale(LC_NUMERIC, 0), NumberFormatter::DECIMAL);
            $decimalSeparator = $fmt->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
        } else {
            $fmt = false;
            $decimalSeparator = '.';
        }

        // Remove all chars except digits, decimal separator and minus
        $string = preg_replace('/[^0-9eE.,-]/', '', $string);

        // Heuristics - we leave just the last comma or dot, as those are for sure the decimal separators
        $string = preg_replace('/\.(?=.*,)|,(?=.*\.)/', '', $string, count: $count);
        /** @disregard */
        if ($count) { // last separator is different then previous => must be decimal separator
            $string = preg_replace('/[.,]/', $decimalSeparator, $string, 1);
        } elseif (strlen(preg_replace('/[^.,]/', '', $string)) > 1) { // there are at least 2 same separators => it is for sure thousands separator 
            $string = preg_replace('/[.,]/', '', $string);
        } elseif (!preg_match('/^\d{1,3}[.,]\d{3}$/', $string)) { // not a thousands separator for sure
            $string = preg_replace('/[.,]/', $decimalSeparator, $string, 1);
        } // else we have one separator and it can be both decimal or thousands separator - let's decide the current locale

        return $fmt ? $fmt->parse($string) : floatval($string);
    }

    /**
     * Try to restore previous language from session or cookie or accept language header.
     *
     * @return void
     */
    private function initCurrentLanguage(): void
    {
        $header = array_filter(array_map(
            fn ($part) => Locale::getPrimaryLanguage($part), 
            explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
        ));

        $preferredTagByPriority = array_filter([
            $_COOKIE['lang'] ?? null, // highest priority, can be set by JS
            $_SESSION['lang'] ?? null, // can be set only by PHP
            ...$header, // browser hints
            $_ENV['LANG'] ?? null, // can be set in CLI, cron, or container environment
        ]);

        $match = null;
        foreach ($preferredTagByPriority as $lang) {
            $match = array_find($this->supportedTags, fn ($tag) => Locale::filterMatches($tag, $lang, true));
            if ($match) break;
        }

        $this->setTag($match ?: $this->supportedTags[0] ?? throw new \Exception("No supported locales configured. Check intl.locales in zolinga.json."));
    }

    /**
     * Set the current language tag.
     *
     * @param string $tag language tag in any format understood by ICU's \Locale class. 
     * @return void
     */
    private function setTag(string $tag): void
    {
        // Check
        $tag = Locale::canonicalize($tag);
        $lang = Locale::getPrimaryLanguage($tag)
            or throw new \Exception("The language is missing in the language tag: '$tag'. Check the zolinga.json's intl.locales values.");
        $region = Locale::getRegion($tag)
            or throw new \Exception("The region is missing in the language tag: '$tag'. Check the zolinga.json's intl.locales values.");

        if ($tag == $this->tag) {
            return;
        }

        // Set
        $this->tag = $tag;
        $this->lang = $lang;
        $this->region = $region;
        $this->locale = $this->lang . "_" . $this->region;
        $this->jsLocale = $this->lang . "-" . $this->region;

        // last setcookie() wins while first $_COOKIE wins
        setcookie('lang', $this->jsLocale, time() + 60 * 60 * 24 * 365, '/');
        $_SESSION['lang'] = $tag;

        $this->initGettext();
    }

    public function reloadGettext(): void
    {
        $this->initGettext(domainSuffix: '', reload: true);
        $this->initGettext(domainSuffix: '.static', reload: true);
    }

    /**
     * Initialize gettext.
     * 
     * Public because gettext compiler needs to re-initialize it.
     *
     * @param string $domainSuffix Optional suffix for domain names (e.g. '.static' for HTML translation)
     * @return void
     */
    public function initGettext(string $domainSuffix = '', bool $reload = false): void
    {
        global $api;

        // Always re-set the locale env — this is what actually switches the language
        $this->setLocaleEnv();

        // Domain binding is idempotent; only do it once per instance per suffix.
        if (isset($this->domainsInitialized[$domainSuffix]) && !$reload) {
            return;
        }
        $this->domainsInitialized[$domainSuffix] = true;

        // 1. Module domains (from manifest)
        foreach ($api->manifest->moduleNames as $moduleName) {
            $this->bindGettextDomain("module://$moduleName/locale", $moduleName . $domainSuffix, $reload);
        }

        // 2. Hard-coded 'default' domain — conflict with a module named 'default' is a real error
        if (in_array('default', $api->manifest->moduleNames)) {
            throw new \Zolinga\Intl\Exceptions\GettextDomainException(
                "A module named 'default' conflicts with the built-in 'default' gettext domain."
            );
        }
        $defaultZPath = 'private://zolinga-intl/default/locale/';
        if (is_dir($defaultZPath)) {
            $this->bindGettextDomain($defaultZPath, 'default' . $domainSuffix, $reload);
        }
    }

    private function bindGettextDomain(string $zPath, string $domain, bool $reload) {
        global $api;

        // If reload is requested, we need to unbind the domain first. Unfortunately there is no built-in way to do it, so we bind it to a non-existing path.
        if ($reload) {
            bindtextdomain($domain, '/dev/null')
                or trigger_error("Cannot unbind text domain '$domain' for reload", E_USER_WARNING);
        }

        $localePath = $api->fs->toPath($zPath);
        if (is_dir($localePath)) {
            bindtextdomain($domain, $localePath)
                or trigger_error("Cannot bind text domain '$domain' to path $localePath", E_USER_WARNING);
            bind_textdomain_codeset($domain, 'UTF-8')
                or trigger_error("Cannot bind text domain codeset: UTF-8", E_USER_WARNING);
            $this->testGettext($domain, $localePath);
        }        
    }

    private function testGettext(string $domain, ?string $path = null): bool
    {
        global $api;
        static $warnings = [];

        // Test
        $testString = '';
        if (dgettext($domain, $testString)) { // on purpose we use variable in dgettext("zolinga-rms", ) to avoid extraction by gettext
            // echo "Domain: $domain, Path: $path, Gettext test: " . dgettext($domain, $testString) . "\n";
            return true;
        }

        // Diagnostics
        $osSupported = $this->getSystemSupportedLocales();
        $matches = count(array_filter($osSupported, fn ($locale) => \Locale::filterMatches($locale, $this->locale, true)));
        if (!$matches) {
            trigger_error("GETTEXT: The locale $this->locale is not supported by the OS. OS supported locales: " . implode(', ', $osSupported), E_USER_WARNING);
            return false;
        }

        // Check the files
        $moPath = $path . "/$this->locale/LC_MESSAGES/$domain.mo";
        if (!is_file($moPath) || !is_readable($moPath)) {
            $zMoPath = $api->fs->toZolingaUri($moPath);

            // if (IS_CLI && !isset($warnings[$zMoPath]) && is_readable($path . "/{$this->locale}.po")) {
            //     $warnings[$zMoPath] = true;
            //     // .po exists but .mo not - probably not compiled yet
            //     trigger_error("GETTEXT: The compiled dictionary file $zMoPath is missing or is not readable. $path/{$this->locale}.po exists. Did you compile the dictionary?", E_USER_WARNING);
            // }
            return false;
        }

        $poPath = realpath(rtrim($path, '/') . "/{$this->locale}.po") ?: "{$this->locale}.po";
        $zPath = $api->fs->toZolingaUri($path);
        $zPoPath = $api->fs->toZolingaUri($poPath);
        $safePoPath = escapeshellarg(ltrim(str_replace(ROOT_DIR, '', $poPath), '/'));
        $safeMoPath = escapeshellarg(ltrim(str_replace(ROOT_DIR, '', $moPath), '/'));
        trigger_error(
            "The gettext domain '$domain' is not initialized properly for $this->locale ($zPath). " .
                "[💡#1] Is the string '' correctly translated and compiled dictionary files are in the right place? Check $zPoPath" .
                "[💡#2] Did you restart PHP after adding new languages? " . 
                "[💡#3] Try to run `msgfmt --statistics --check $safePoPath` to check the .po file for errors. " .
                "[💡#4] Run `msgunfmt $safeMoPath` to inspect the .mo file. ",
            E_USER_WARNING
        );

        return false;
    }

    /**
     * Set the locale environment.
     *
     * @return void
     */
    private function setLocaleEnv(): void
    {
        putenv("LC_ALL={$this->locale}");

        $try = array(
            "{$this->locale}.UTF-8",
            "{$this->locale}.ISO-8859-2",
            "{$this->locale}.ISO-8859-1",
            "{$this->locale}.ISO-8859-15",
            "{$this->locale}",
            "{$this->lang}.UTF-8",
            "{$this->lang}.ISO-8859-2",
            "{$this->lang}.ISO-8859-1",
            "{$this->lang}.ISO-8859-15",
            "{$this->lang}",
        );

        setlocale(LC_ALL, $try)
            or trigger_error("Cannot set locale: " . $this->locale . " to LC_ALL. Tried " . implode(", ", $try) . ". OS supported locales: " . implode(', ', $this->getSystemSupportedLocales()), E_USER_WARNING);
    }

    /**
     * Get the system supported locales.
     *
     * @return array<string>
     */
    private function getSystemSupportedLocales()
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $output = @shell_exec('locale -a 2> /dev/null') or trigger_error("Cannot get system supported locales", E_USER_WARNING);

        $localeGen = '/etc/locale.gen';
        if (!$output && is_file($localeGen) && is_readable($localeGen)) {
            if (!$output = file_get_contents($localeGen)) {
                return $cache = [];
            }
        }

        $lines = explode("\n", $output ?: '');
        // Remove comments
        return $cache = array_filter(array_map('trim', $lines), fn ($line): bool => (bool) preg_match('/^[^#]/', $line));
    }
}
