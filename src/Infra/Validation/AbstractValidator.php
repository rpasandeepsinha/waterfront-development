<?php

declare(strict_types=1);

namespace Waterfront\Infra\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

abstract class AbstractValidator implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->passes($attribute, $value)) {
            $fail($this->message());
        }
    }

    abstract protected function passes(string $attribute, mixed $value): bool;

    abstract protected function message(): string;
}
