<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Services;

use UnexpectedValueException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Redirects\Enums\DnsRedirectProvisionOption;
use Waterfront\Domain\Redirects\Services\RedirectsDatabase\Redirect;
use Waterfront\Infra\Common\PublicSuffixList;

class CustomerSharedRedirectService
{
    public function __construct(
        private readonly RedirectDnsServiceInterface $redirectDnsService,
        private readonly RedirectServiceInterface $redirects,
        private readonly PublicSuffixList $publicSuffixList
    ) {
    }

    /** @return Redirect[] */
    public function list(Customer $customer, string $domain): array
    {
        return $this->redirects->index($customer->id, $domain);
    }

    public function add(Customer $customer, string $source, string $target, RedirectType $type): Redirect
    {
        $redirect = $this->redirects->add($customer->id, $source, $target, $type);
        $sourceHost = $this->getRedirectSourceHost($source);
        $this->redirectDnsService->provisionDnsRecords($this->getBaseDomainFromHost($sourceHost), $sourceHost, DnsRedirectProvisionOption::OVERRIDE);
        return $redirect;
    }

    public function remove(Customer $customer, string $source): void
    {
        $sourceHost = $this->getRedirectSourceHost($source);
        $this->redirectDnsService->cleanupDnsRecords($this->getBaseDomainFromHost($sourceHost), $sourceHost);
        $this->redirects->remove($customer->id, $source);
    }

    public function update(Customer $customer, string $oldSource, string $newSource, string $newTarget, RedirectType $type): Redirect
    {
        if ($oldSource === $newSource) {
            return $this->redirects->update($customer->id, $newSource, $newTarget, $type);
        }
        /**
         * In the case that the sources differ, the DNS should also be updated. This is handled by $this->add and
         * $this->remove that are reused here.
         */
        $this->remove($customer, $oldSource);
        return $this->add($customer, $newSource, $newTarget, $type);
    }

    public function isSourceUnique(Customer $customer, string $source): bool
    {
        return $this->redirects->isSourceUnique($customer->id, $source);
    }

    private function getBaseDomainFromHost(string $sourceHost): string
    {
        return $this->publicSuffixList->getRegistrableDomain($sourceHost)
            ?? throw new UnexpectedValueException("Cannot parse domain of redirect source host: $sourceHost");
    }

    private function getRedirectSourceHost(string $source): string
    {
        return $this->publicSuffixList->getHostFromUrlOrDomain($source)
            ?? throw new UnexpectedValueException("Cannot parse host of redirect source: $source");
    }
}
