<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule was violated. The message is already translated and safe to show to the user.
 */
final class DomainRuleViolation extends RuntimeException
{
    /**
     * @param  array<string, string|int>  $replace
     */
    public static function because(string $message, array $replace = []): self
    {
        return new self(__($message, $replace));
    }
}
