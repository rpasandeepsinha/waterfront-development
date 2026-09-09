<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Rules;

use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;
use Waterfront\Support\Helpers\IdnHelper;

/**
 * Custom rule for Dns template's so its not allowed to have @@ as value.
 */
class DnsTemplateFqdn extends AbstractValidator
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        assert(is_string($value));

        if (substr_count($value, '@') > 1) {
            return false;
        }

        if (str_contains($value, '@')) {
            $value = str_replace('@', 'domain.nl', $value);
        }

        $value = IdnHelper::toAscii($value);

        /**
         * This condition is necessary so that only the correct attribute is checked with the strict regex.
         * This is not exactly what we wanted, so a TD has been created for this.
         * Todo : Remove the condition after improvements -> https://yh-jira.atlassian.net/browse/WATER-5989.
         */
        if ($attribute === 'content') {
            return (bool) preg_match('/(?=^.{4,253}$)(^((?!-)[a-z0-9-_]{0,63}\.?)+([a-z]{1,63}\.?)?[^0-9]$)/i', $value);
        }

        return (bool) preg_match('/(?=^.[^ ]{4,253}$)(^((?!-)[a-z0-9-_]{0,62}[a-z0-9_*]\.)+[a-z]{1,63}\.?$)?/i', $value);
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.fqdn');
    }
}
