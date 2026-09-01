<?php

declare(strict_types=1);

namespace App\Rules;

use App\Webhooks\SsrfGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A webhook URL must be https and must resolve only to public addresses.
 * This is the first of two checks: the same range check runs again at send
 * time against a fresh DNS answer.
 */
final readonly class PublicHttpsUrl implements ValidationRule
{
    public function __construct(private SsrfGuard $guard) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->guard->allows($value)) {
            $fail(__('The :attribute must be a public https URL.'));
        }
    }
}
