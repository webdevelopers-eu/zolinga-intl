<?php

declare(strict_types=1);

namespace Zolinga\Intl\Types;

/**
 * File type constants for gettext extraction.
 *
 * Bitmask values for specifying which file types to process.
 *
 * Usage:
 *   FileTypes::PHP | FileTypes::HTML  // process both PHP and HTML
 *   FileTypes::getGlobs(FileTypes::JAVASCRIPT)  // ['*.js', '*.mjs']
 */
class FileTypes
{
    const PHP        = 1 << 0;  // 1
    const JAVASCRIPT = 1 << 1;  // 2
    const HTML       = 1 << 2;  // 4
    const ALL        = self::PHP | self::JAVASCRIPT | self::HTML;  // 7

    /**
     * Return glob patterns for the given file type bitmask.
     *
     * @param int $fileTypes bitmask of FileTypes constants
     * @return array<string>
     */
    public static function getGlobs(int $fileTypes): array
    {
        $globs = [];
        if ($fileTypes & self::PHP) {
            $globs[] = '*.php';
        }
        if ($fileTypes & self::JAVASCRIPT) {
            $globs = array_merge($globs, ['*.js', '*.mjs']);
        }
        if ($fileTypes & self::HTML) {
            $globs[] = '*.html';
        }
        return $globs;
    }
}
