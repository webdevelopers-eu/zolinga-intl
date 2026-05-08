<?php

declare(strict_types=1);

namespace Zolinga\Intl\Exceptions;

/**
 * Thrown when a gettext domain name conflicts with a built-in domain.
 *
 * Example: a module named 'default' conflicts with the hard-coded 'default' domain.
 */
class GettextDomainException extends \RuntimeException {}
