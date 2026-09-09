<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Rules;

use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class TlsaContent extends AbstractValidator
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        assert(is_string($value));
        $formatCorrect = (bool) preg_match(
            '/^(?P<usage>0|1|2|3) (?P<selector>0|1) (?P<type>0|1|2) (?P<hash>[^ ]+)$/',
            $value,
            $parsed
        );

        if (! $formatCorrect) {
            return false;
        }

        if ($parsed['selector'] === '1' && strlen($parsed['hash']) !== 64) {
            // Not a SHA-256 hash
            return false;
        }

        if ($parsed['selector'] === '2' && strlen($parsed['hash']) !== 128) {
            // Not a SHA-512 hash
            return false;
        }

        return true;
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.tlsa_content');
    }
}
