<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Rules;

use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class CaaContent extends AbstractValidator
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        assert(is_string($value));
        return (bool) preg_match(
            '/^(?:0|128) [a-zA-z0-9]+ \"[^\"]+\"$/',
            $value
        );
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.caa_content');
    }
}
