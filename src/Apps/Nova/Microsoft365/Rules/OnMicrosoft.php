<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Microsoft365\Rules;

use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class OnMicrosoft extends AbstractValidator
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        assert(is_string($value));

        return str_ends_with($value, '.onmicrosoft.com');
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.no_onmicrosoft');
    }
}
