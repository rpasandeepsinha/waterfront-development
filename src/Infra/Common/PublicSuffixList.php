<?php

declare(strict_types=1);

namespace Waterfront\Infra\Common;

use Illuminate\Support\Facades\Cache;
use Pdp\Rules;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class PublicSuffixList
{
    private Rules $rules;

    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly bool $shouldCache,
    ) {
    }

    public function getRules(): Rules
    {
        if (! $this->shouldCache) {
            $this->rules = Rules::fromPath(
                $this->configuration->getAsString('filesystems.disks.private.root') . '/public_suffix_list.dat',
            );

            return $this->rules;
        }

        $this->rules = Cache::remember(
            'pdp_public_suffix',
            86400,
            fn (): Rules => Rules::fromPath($this->configuration->getAsString('pdp.public_suffix_url')),
        );

        return $this->rules;
    }

    public function getRegistrableDomain(string $domain): ?string
    {
        $resolvedDomainName = $this->getRules()->resolve($domain);

        return $resolvedDomainName->registrableDomain()->value();
    }

    public function getHostFromUrlOrDomain(string $value): ?string
    {
        $host = parse_url($value, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return $host;
        }

        $host = parse_url('https://' . $value, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }

    public function isRootDomain(string $domain): bool
    {
        return $domain === $this->getRegistrableDomain($domain);
    }

    /**
     * @return string TLD without starting dot, example: "nl" or "co.uk"
     */
    public function getTld(string $domain): string
    {
        $resolvedDomainName = $this->getRules()->resolve($domain);

        return $resolvedDomainName->suffix()->toString();
    }
}
