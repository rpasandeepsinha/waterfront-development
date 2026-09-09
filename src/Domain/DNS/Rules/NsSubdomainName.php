<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

/**
 * Custom rule for Dns template's to check if ns name has a valid subdomain.
 */
class NsSubdomainName extends AbstractValidator implements DataAwareRule
{
    protected array $data = []; // @phpstan-ignore-line comes from laravel request so can't type it

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    // @phpstan-ignore-next-line Laravel interface implementation
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        $domain = $this->data['domain'] ?? null;

        if (! is_string($domain) || ! is_string($value)) {
            return false;
        }

        $domain = rtrim(strtolower($domain), '.');
        $nsFqdn = rtrim(strtolower($value), '.');

        $domainSuffix = '.' . $domain;

        if (! str_ends_with($nsFqdn, $domainSuffix)) {
            return false;
        }

        $subdomainPart = substr($nsFqdn, 0, -strlen($domainSuffix));

        if ($subdomainPart === '') {
            return false;
        }

        $subdomainLabels = explode('.', $subdomainPart);

        $hasWildcardLabel = false;

        foreach ($subdomainLabels as $labelIndex => $label) {
            if ($label === '') {
                return false;
            }

            if ($label === '*') {
                if ($labelIndex !== 0 || $hasWildcardLabel) {
                    return false;
                }
                $hasWildcardLabel = true;
                continue;
            }

            if (str_starts_with($label, '*')) {
                return false;
            }

            $isValidLabel = (bool) preg_match('/^[a-z0-9_](?:[a-z0-9_-]{0,59}[a-z0-9_])?$/i', $label);

            if (! $isValidLabel) {
                return false;
            }
        }

        return true;
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.ns');
    }
}
