<?php

declare(strict_types=1);

namespace Zolinga\Intl\Exceptions;

/**
 * Thrown when a autotranslation gets invalid or inaccurate translation from the AI.
 *
 */
class TranslationException extends \RuntimeException {


    public function __construct(
        string $message, 
        public readonly array $issues = [], 
        int $code = 0, 
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

}