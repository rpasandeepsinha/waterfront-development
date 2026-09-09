<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Microsoft365\Rules;

use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class KpnCustomerId extends AbstractValidator
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        assert(is_string($value));
        return str_starts_with($value, 'CID');
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.no_cid_kpn_customer_id');
    }
}
