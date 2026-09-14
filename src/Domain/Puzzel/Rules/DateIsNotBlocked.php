<?php

declare(strict_types=1);

namespace Waterfront\Domain\Puzzel\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Waterfront\Domain\Puzzel\Repositories\PuzzelBlockedDateRepository;
use Waterfront\Infra\Translation\Translator;

class DateIsNotBlocked implements ValidationRule
{
    public function __construct(
        private readonly Translator $translator,
        private readonly PuzzelBlockedDateRepository $blockedDateRepository,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.date', $attribute);

            return;
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $value);

        if ($date === null) {
            $fail('validation.date', $attribute);

            return;
        }

        if ($this->blockedDateRepository->dateIsBlocked($date)) {
            $fail($this->translator->translate('validation.puzzel-date-blocked'));
        }
    }
}
