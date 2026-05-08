<?php

declare(strict_types=1);

namespace Zolinga\Intl\Types;

/**
 * Enum for gettext modes: 'translate', 'cherry-pick' and 'replace'.
 *
 * @author Daniel Sevcik <sevcik@zolinga.net>
 * 
 */
enum GettextModeEnum: string
{
    // Source file meant to be translated
    case TRANSLATE = 'translate';
    // Target translated file that should not be replaced but instead have the translatable nodes cherry-picked and translated
    case CHERRY_PICK = 'cherry-pick';
    // Target file that should be fully replaced with the new translated content
    case REPLACE = 'replace';
    // Protected file that should not be translated at all
    case PROTECT = 'protect';
}