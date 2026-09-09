<?php

declare(strict_types=1);

namespace Waterfront\Domain\Puzzel\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Ramsey\Uuid\Exception\InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Puzzel\Repositories\PuzzelCallbackTimeslotRepository;
use Waterfront\Infra\Translation\Translator;

class TimeslotExistsWithCapacity implements ValidationRule
{
    public function __construct(
        private readonly Translator $translator,
        private readonly PuzzelCallbackTimeslotRepository $timeslotRepository
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.uuid', $attribute);
            return;
        }

        try {
            $uuid = Uuid::fromString($value);
        } catch (InvalidArgumentException) {
            $fail('validation.uuid', $attribute);
            return;
        }

        $timeslot = $this->timeslotRepository->getByUuid($uuid);

        if ($timeslot === null) {
            $fail('validation.exists', $attribute);
            return;
        }

        if (! $this->timeslotRepository->hasAvailableCapacity($timeslot)) {
            $fail($this->translator->translate('validation.puzzel-timeslot-full'));
        }
    }
}
