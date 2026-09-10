<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Services;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Domains\DTO\Domain;
use Waterfront\Domain\Domains\DTO\HandleParameters;
use Waterfront\Domain\Domains\DTO\Handles;
use Waterfront\Domain\Domains\DTO\RetrieveResult;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Domains\Services\OpenSrsService;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\OpenSrsClient\Exceptions\OpenSrsResultException;
use Waterfront\Infra\OpenSrsClient\OpenSrsClient;
use Waterfront\Support\Exceptions\NotImplementedException;

#[CoversClass(OpenSrsService::class)]
#[AllowMockObjectsWithoutExpectations]
class OpenSrsServiceTest extends TestCase
{
    private OpenSrsClient&MockObject $client;

    private OpenSrsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(OpenSrsClient::class);

        $this->service = new OpenSrsService(
            $this->client,
            $this->createStub(DnsDeploymentRepository::class),
            $this->createStub(ConfigurationInterface::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    #[Test]
    public function checkDelegatesToTheClient(): void
    {
        $expected = new CheckResult('example.org', CheckResult::STATUS_FREE);
        $this->client->expects(self::once())->method('checkDomain')->with('example.org')->willReturn($expected);

        self::assertSame($expected, $this->service->check('example.org'));
    }

    #[Test]
    public function checkRejectsWwwDomains(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->check('www.example.org');
    }

    #[Test]
    public function checkWrapsClientFailuresInARuntimeException(): void
    {
        $this->client->method('checkDomain')->willThrowException(new OpenSrsResultException('boom', 400));

        $this->expectException(RuntimeException::class);

        $this->service->check('example.org');
    }

    #[Test]
    public function modifyReturnsTrueOnSuccess(): void
    {
        $this->client->expects(self::once())->method('modifyDomain');

        self::assertTrue($this->service->modify('example.org', ['autoRenew' => true]));
    }

    #[Test]
    public function modifyWrapsClientFailures(): void
    {
        $this->client->method('modifyDomain')->willThrowException(new OpenSrsResultException('nope', 400));

        $this->expectException(DomainModificationFailedException::class);

        $this->service->modify('example.org', ['isLocked' => true]);
    }

    #[Test]
    public function updateNameServersReturnsTrueOnSuccess(): void
    {
        $this->client->expects(self::once())
            ->method('updateNameServers')
            ->with('example.org', [new Nameserver('ns1.example.com')]);

        self::assertTrue($this->service->updateNameServers('example.org', [new Nameserver('ns1.example.com')]));
    }

    #[Test]
    public function fetchDomainMapsTheRetrieveResult(): void
    {
        $result = new RetrieveResult();
        $result->setDomain(new Domain('example.org'));
        $result->setHandles(new Handles('owner@example.org'));
        $result->setAutoRenew(true);
        $result->setNameServers([['name' => 'ns1.example.com'], ['name' => 'ns2.example.com']]);
        $result->setExpirationDate('2027-01-01 00:00:00');

        $this->client->method('retrieveDomain')->with('example.org')->willReturn($result);

        $details = $this->service->fetchDomain('example.org');

        self::assertSame('example.org', $details->domainName);
        self::assertSame('owner@example.org', $details->registrant);
        self::assertSame(['ns1.example.com', 'ns2.example.com'], $details->ns);
        self::assertTrue($details->autoRenew);
    }

    #[Test]
    public function contactHandleOperationsAreNotSupported(): void
    {
        $this->expectException(NotImplementedException::class);

        $this->service->createContact($this->createStub(HandleParameters::class));
    }

    #[Test]
    public function suspendIsNotSupported(): void
    {
        $this->expectException(NotImplementedException::class);

        $this->service->suspend('example.org');
    }

    #[Test]
    public function dnssecAndZoneChecksReportUnsupported(): void
    {
        self::assertFalse($this->service->isDnssecSupported('example.org'));
        self::assertFalse($this->service->hasZone('example.org'));
        self::assertFalse($this->service->nameserversAreRequired('example.org'));
        self::assertSame([], $this->service->retrieveDnssecKeys('example.org'));
    }
}
