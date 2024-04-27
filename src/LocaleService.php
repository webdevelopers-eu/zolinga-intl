<?php

declare(strict_types=1);

namespace Zolinga\Intl;

use Zolinga\System\Events\{ServiceInterface, RequestResponseEvent};
use Locale, NumberFormatter;

/**
 * Language and gettext service.
 * 
 * @property string|null $tag The current language tag selected and canonicalized from $api->config['intl']['locales'].
 * @property string|null $locale The locale code from the current language tag in format language_REGION.
 * @property string|null $jsLocale The locale code from the current language tag in format language-REGION.
 * @property string|null $lang The primary language code from the current language tag.
 * @property-read string|null $region The region code from the current language tag.
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
        $try = preg_replace('/(?:\.C)(\.[a-z]+)$/', ".{$lang}\1", $file);
        return file_exists($try) ? $try : $file;
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
        $preferredTag =
            $_COOKIE['lang'] ??
            $_SESSION['lang'] ??
            Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? $this->supportedTags[0]) ?:
            $this->supportedTags[0] or
            throw new \Exception("The language tag is missing in the configuration file zolinga.json's intl.locales values.");

        $selectedTag = Locale::lookup($this->supportedTags, $preferredTag, false, $this->supportedTags[0]);
        $this->setTag($selectedTag);
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

    /**
     * Initialize gettext.
     * 
     * Public because gettext compiler needs to re-initialize it.
     *
     * @return void
     */
    public function initGettext()
    {
        global $api;

        $this->setLocaleEnv();

        $paths = array_combine(
            array_map(fn ($path) => basename($path), $api->manifest->modulePaths),
            array_map(fn ($path) => $path . '/locale', $api->manifest->moduleRealPaths)
        );

        // Add custom per-site dictionary
        $paths['custom'] = $api->fs->toPath('private://zolinga-intl/locale');

        foreach ($paths as $domain => $path) {
            if (is_dir($path)) {
                bindtextdomain($domain, $path)
                    or trigger_error("Cannot bind text domain '$domain' to path $path", E_USER_WARNING);
                bind_textdomain_codeset($domain, 'UTF-8')
                    or trigger_error("Cannot bind text domain codeset: UTF-8", E_USER_WARNING);
                $this->testGettext($domain, $path);
            }
        }

        if (is_dir($paths['custom'])) {
            textdomain('custom')
                or trigger_error("Cannot set text domain: custom", E_USER_WARNING);
        }
    }

    private function testGettext(string $domain, ?string $path = null): bool
    {
        // Test
        $testString = '';
        if (dgettext($domain, $testString)) { // on purpose we use variable in dgettext("zolinga-rms", ) to avoid extraction by gettext
            // echo "Domain: $domain, Path: $path, Gettext test: " . dgettext($domain, $testString) . "\n";
            return true;
        }

        // Diagnostics
        $osSupported = $this->getSystemSupportedLocales();
        $matches = count(array_filter($osSupported, fn ($locale) => Locale::filterMatches($locale, $this->locale, true)));
        if (!$matches) {
            trigger_error("GETTEXT: The locale $this->locale is not supported by the OS. OS supported locales: " . implode(', ', $osSupported), E_USER_WARNING);
            return false;
        }

        // Check the files
        $moPath = $path . "/$this->locale/LC_MESSAGES/$domain.mo";
        if (!is_file($moPath) || !is_readable($moPath)) {
            trigger_error("GETTEXT: The compiled dictionary file $moPath is missing or is not readable. Did you compile the dictionary?", E_USER_WARNING);
            return false;
        }

        echo __LINE__ . "\n";
        trigger_error(
            "The gettext domain '$domain' is not initialized properly for $this->locale ($path). " .
                "[1] Is the string '' correctly translated and compiled dictionary files are in the right place? " .
                "[2] Did you restart PHP after adding new languages? ",
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
