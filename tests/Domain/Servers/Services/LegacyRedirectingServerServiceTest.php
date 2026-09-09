<?php

declare(strict_types=1);

namespace Tests\Domain\Servers\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Servers\DTO\LegacyRedirectingServerDTO;
use Waterfront\Domain\Servers\Models\LegacyRedirectingServer;
use Waterfront\Domain\Servers\Services\LegacyRedirectingServerService;

#[CoversClass(LegacyRedirectingServerService::class)]
class LegacyRedirectingServerServiceTest extends IntegrationTestCase
{
    private LegacyRedirectingServerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = self::resolve(LegacyRedirectingServerService::class);
    }

    #[Test]
    public function itStoresAServerFromTheDataTransferObject(): void
    {
        $legacyRedirectingServer = $this->service->store(new LegacyRedirectingServerDTO(
            hostname: 'legacy-01.example.com',
            ipv4: '198.51.100.10',
            ipv6: '2001:db8::10',
            originalBusinessUnit: 'yourhosting',
        ));

        self::assertDatabaseCount('hosting_redirecting_legacy_servers', 1);

        $stored = LegacyRedirectingServer::query()->findOrFail($legacyRedirectingServer->id);

        self::assertSame('legacy-01.example.com', $stored->hostname);
        self::assertSame('198.51.100.10', $stored->ipv4);
        self::assertSame('2001:db8::10', $stored->ipv6);
        self::assertSame('yourhosting', $stored->original_business_unit);
    }

    #[Test]
    public function itStoresAServerWithoutAnIpv6(): void
    {
        $legacyRedirectingServer = $this->service->store(new LegacyRedirectingServerDTO(
            hostname: 'legacy-02.example.com',
            ipv4: '198.51.100.11',
            ipv6: null,
            originalBusinessUnit: 'versio',
        ));

        self::assertNull($legacyRedirectingServer->ipv6);
    }

    #[Test]
    public function itUpdatesEveryFieldWithoutCreatingARecord(): void
    {
        $legacyRedirectingServer = $this->service->store(new LegacyRedirectingServerDTO(
            hostname: 'legacy-03.example.com',
            ipv4: '198.51.100.12',
            ipv6: '2001:db8::12',
            originalBusinessUnit: 'yourhosting',
        ));

        $this->service->update($legacyRedirectingServer, new LegacyRedirectingServerDTO(
            hostname: 'legacy-03-renamed.example.com',
            ipv4: '198.51.100.13',
            ipv6: null,
            originalBusinessUnit: 'versio',
        ));

        self::assertDatabaseCount('hosting_redirecting_legacy_servers', 1);

        $updated = LegacyRedirectingServer::query()->findOrFail($legacyRedirectingServer->id);

        self::assertSame('legacy-03-renamed.example.com', $updated->hostname);
        self::assertSame('198.51.100.13', $updated->ipv4);
        // Clearing the optional ipv6 must persist as null, not keep the old value.
        self::assertNull($updated->ipv6);
        self::assertSame('versio', $updated->original_business_unit);
    }
}
