<?php

declare(strict_types=1);

namespace Tests\Domain\Redirects\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Redirects\Enums\DnsRedirectProvisionOption;
use Waterfront\Domain\Redirects\Services\CustomerSharedRedirectService;
use Waterfront\Domain\Redirects\Services\RedirectDnsServiceInterface;
use Waterfront\Domain\Redirects\Services\RedirectsDatabase\Redirect;
use Waterfront\Domain\Redirects\Services\RedirectServiceInterface;
use Waterfront\Infra\Common\PublicSuffixList;

#[CoversClass(CustomerSharedRedirectService::class)]
class CustomerSharedRedirectServiceTest extends TestCase
{
    private Customer $customer;

    private RedirectDnsServiceInterface&MockObject $redirectDnsService;

    private RedirectServiceInterface&MockObject $redirects;

    private PublicSuffixList&MockObject $publicSuffixList;

    private CustomerSharedRedirectService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new Customer();
        $this->customer->id = 1;
        $this->redirectDnsService = self::createMock(RedirectDnsServiceInterface::class);
        $this->redirects = self::createMock(RedirectServiceInterface::class);
        $this->publicSuffixList = self::createMock(PublicSuffixList::class);

        $this->service = new CustomerSharedRedirectService(
            redirectDnsService: $this->redirectDnsService,
            redirects: $this->redirects,
            publicSuffixList: $this->publicSuffixList,
        );
    }

    public function testAddWithPathAndQueryProvisionsDnsForHost(): void
    {
        $source = 'test.caddy-redirect.nl/test?utm_source=newsletter';
        $sourceHost = 'test.caddy-redirect.nl';
        $baseDomain = 'caddy-redirect.nl';
        $target = 'https://google.nl';
        $type = RedirectType::PERMANENT;
        $redirect = new Redirect($this->customer->id, $baseDomain, 'nl', $source, $target, $type->value);

        $this->redirects
            ->expects(self::once())
            ->method('add')
            ->with($this->customer->id, $source, $target, $type)
            ->willReturn($redirect);

        $this->publicSuffixList
            ->expects(self::once())
            ->method('getHostFromUrlOrDomain')
            ->with($source)
            ->willReturn($sourceHost);

        $this->publicSuffixList
            ->expects(self::once())
            ->method('getRegistrableDomain')
            ->with($sourceHost)
            ->willReturn($baseDomain);

        $this->redirectDnsService
            ->expects(self::once())
            ->method('provisionDnsRecords')
            ->with($baseDomain, $sourceHost, DnsRedirectProvisionOption::OVERRIDE);

        self::assertSame($redirect, $this->service->add($this->customer, $source, $target, $type));
    }

    public function testRemoveWithPathAndQueryCleansDnsForHost(): void
    {
        $source = 'test.caddy-redirect.nl/test?utm_source=newsletter';
        $sourceHost = 'test.caddy-redirect.nl';
        $baseDomain = 'caddy-redirect.nl';

        $this->publicSuffixList
            ->expects(self::once())
            ->method('getHostFromUrlOrDomain')
            ->with($source)
            ->willReturn($sourceHost);

        $this->publicSuffixList
            ->expects(self::once())
            ->method('getRegistrableDomain')
            ->with($sourceHost)
            ->willReturn($baseDomain);

        $this->redirectDnsService
            ->expects(self::once())
            ->method('cleanupDnsRecords')
            ->with($baseDomain, $sourceHost);

        $this->redirects
            ->expects(self::once())
            ->method('remove')
            ->with($this->customer->id, $source);

        $this->service->remove($this->customer, $source);
    }
}
