<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeRegex implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (str_starts_with($value, '^') || str_ends_with($value, '$')) {
            $fail(__('validation.safe_regex_invalid'));

            return;
        }

        // Limit regex pattern length
        if (strlen($value) > 100) {
            $fail(__('validation.safe_regex_too_long'));

            return;
        }

        // Only allow safe characters
        if (preg_match('/[^a-zA-Z0-9_\-\^\$\[\]\{\}\.\+\*\?\|\\\]/', $value)) {
            $fail(__('validation.safe_regex_not_allowed'));

            return;
        }

        // Remove anchors for validation
        $pattern = self::wrapPattern($value);

        // Check if it's a valid regex
        if (! self::isValidRegex($pattern)) {
            $fail(__('validation.safe_regex_invalid'));
        }
    }

    public static function isValidRegex(string $pattern): bool
    {
        set_error_handler(function () {});
        $isValid = @preg_match($pattern, '') !== false;
        restore_error_handler();

        return $isValid;
    }

    /**
     * Prepare and return a full regex pattern with anchors.
     * Usage: preg_match(SafeRegex::prepare($storedRegex), $input)
     */
    public static function prepare(string $raw): string
    {
        return self::wrapPattern($raw);
    }

    /**
     * Wraps the given pattern with ^ and $ (if not already).
     */
    private static function wrapPattern(string $pattern): string
    {
        $pattern = preg_replace('/^\^|\$$/', '', $pattern); // Remove leading ^ and trailing $

        return '/^'.$pattern.'$/';
    }
}
