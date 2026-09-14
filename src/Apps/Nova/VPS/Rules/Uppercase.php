<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\VPS\Rules;

use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class Uppercase extends AbstractValidator
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return strtoupper($value) === $value;
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.uppercase');
    }
}
