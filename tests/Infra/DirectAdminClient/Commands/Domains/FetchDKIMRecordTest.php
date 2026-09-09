<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands\Domains;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\FetchDkimRecord;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(FetchDkimRecord::class)]
#[AllowMockObjectsWithoutExpectations]
class FetchDKIMRecordTest extends DirectAdminTestCase
{
    private FetchDkimRecord $fetchDkimRecord;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = self::createMock(LoggerInterface::class);
        $this->fetchDkimRecord = new FetchDkimRecord('default.nl', $this->logger);
    }

    #[Test]
    public function fetchDkimRecord(): void
    {
        $response = ['records' => [
            ['type' => 'A', 'name' => 'stmp', 'value' => '92.63.169.145', 'combined' => 'name=smtp&value=92.63.169.145', 'ttl' => '3600'],
            ['type' => 'NS', 'name' => 'default.nl', 'value' => 'ns1.axc.nl.', 'combined' => 'name=default.nl.&value=ns1.axc.nl.'],
            ['type' => 'TXT', 'name' => 'default.nl.', 'value' => '"v=spf1 a mx ip4:92.63.169.145 ~all"', 'combined' => 'name=default.nl.&value="v=spf1 a mx ip4:92.63.169.145 ~all"', 'ttl' => '3600'],
            ['type' => 'TXT', 'name' => 'x._domainkey', 'value' => '"v=DKIM1; fakedkim"', 'combined' => '"name=x._domainkey&value="fakedkim"', 'ttl' => '3600'],
            ['type' => 'AAAA', 'name' => 'default.nl.', 'value' => '2a05:1500:600:8:1c00:b2ff:fe00:1432', 'combined' => 'name=default.nl.&value=2a05:1500:600:8:1c00:b2ff:fe00:1432', 'ttl' => '3600'],
        ]];

        $this->fetchDkimRecord->responseReceived($response);

        $commandData = $this->fetchDkimRecord->getDkimRecord();
        assert(is_array($commandData));

        self::assertTrue($this->fetchDkimRecord->hasSucceeded());
        self::assertSame('TXT', $commandData['type']);
        self::assertSame('x._domainkey', $commandData['name']);
        self::assertSame('"v=DKIM1; fakedkim"', $commandData['value']);
        self::assertSame('"name=x._domainkey&value="fakedkim"', $commandData['combined']);
        self::assertSame('3600', $commandData['ttl']);
    }

    #[Test]
    public function fetchMultipleDkimRecords(): void
    {
        $response = ['records' => [
            ['type' => 'A', 'name' => 'stmp', 'value' => '92.63.169.145', 'combined' => 'name=smtp&value=92.63.169.145', 'ttl' => '3600'],
            ['type' => 'NS', 'name' => 'default.nl', 'value' => 'ns1.axc.nl.', 'combined' => 'name=default.nl.&value=ns1.axc.nl.'],
            ['type' => 'TXT', 'name' => 'default.nl.', 'value' => '"v=spf1 a mx ip4:92.63.169.145 ~all"', 'combined' => 'name=default.nl.&value="v=spf1 a mx ip4:92.63.169.145 ~all"', 'ttl' => '3600'],
            ['type' => 'TXT', 'name' => 'x._domainkey', 'value' => '"v=DKIM1; fakedkim"', 'combined' => '"name=x._domainkey&value="fakedkim"', 'ttl' => '3600'],
            ['type' => 'TXT', 'name' => 'x._domainkey', 'value' => '"v=DKIM1; doubledkim"', 'combined' => '"name=x._domainkey&value="fakedkim"', 'ttl' => '3600'],
            ['type' => 'AAAA', 'name' => 'default.nl.', 'value' => '2a05:1500:600:8:1c00:b2ff:fe00:1432', 'combined' => 'name=default.nl.&value=2a05:1500:600:8:1c00:b2ff:fe00:1432', 'ttl' => '3600'],
        ]];

        $this->logger->expects(self::once())
            ->method('warning')
            ->with(
                'Multiple dkim records found for domain {domain.name}, picking the first one.',
                [
                    LoggingContextKeys::DOMAIN_NAME => 'default.nl',
                ]
            );

        $this->fetchDkimRecord->responseReceived($response);

        $commandData = $this->fetchDkimRecord->getDkimRecord();
        assert(is_array($commandData));

        self::assertTrue($this->fetchDkimRecord->hasSucceeded());
        self::assertSame('TXT', $commandData['type']);
        self::assertSame('x._domainkey', $commandData['name']);
        self::assertSame('"v=DKIM1; fakedkim"', $commandData['value']);
        self::assertSame('"name=x._domainkey&value="fakedkim"', $commandData['combined']);
        self::assertSame('3600', $commandData['ttl']);
    }

    #[Test]
    public function fetchNoDkimRecord(): void
    {
        $response = ['records' => [
            ['type' => 'A', 'name' => 'stmp', 'value' => '92.63.169.145', 'combined' => 'name=smtp&value=92.63.169.145', 'ttl' => '3600'],
            ['type' => 'NS', 'name' => 'default.nl', 'value' => 'ns1.axc.nl.', 'combined' => 'name=default.nl.&value=ns1.axc.nl.'],
            ['type' => 'AAAA', 'name' => 'default.nl.', 'value' => '2a05:1500:600:8:1c00:b2ff:fe00:1432', 'combined' => 'name=default.nl.&value=2a05:1500:600:8:1c00:b2ff:fe00:1432', 'ttl' => '3600'],
        ]];

        $this->fetchDkimRecord->responseReceived($response);

        $commandData = $this->fetchDkimRecord->getDkimRecord();

        self::assertFalse($this->fetchDkimRecord->hasSucceeded());
        self::assertNull($commandData);
    }
}
