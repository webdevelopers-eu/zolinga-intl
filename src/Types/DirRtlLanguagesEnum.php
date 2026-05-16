<?php

declare(strict_types=1);

namespace Zolinga\Intl\Types;

/**
 * Enum for languages that are written right-to-left (RTL).
 *
 * @author Daniel Sevcik <sevcik@zolinga.net>
 * @date 2026-05-16
 */
enum DirRtlLanguagesEnum: string
{
    case ARABIC = 'ar';
    case HEBREW = 'he';
    case PERSIAN = 'fa';
    case URDU = 'ur';

    public static function isRtl(string $locale): bool
    {
        $lang = \Locale::getPrimaryLanguage($locale);
        return in_array($lang, array_column(self::cases(), 'value'), true);
    }
}