<?php

declare(strict_types=1);

namespace Tests\Domain\Redirects\Mappers;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\Redirects\Mappers\RedirectDnsSubscriptionMapper;

#[CoversClass(RedirectDnsSubscriptionMapper::class)]
class RedirectDnsSubscriptionMapperTest extends IntegrationTestCase
{
    private const string SUBSCRIPTION_UUID = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

    private RedirectDnsSubscriptionMapper $mapper;

    public function setUp(): void
    {
        parent::setUp();

        $this->mapper = $this->app->make(RedirectDnsSubscriptionMapper::class);
    }

    #[Test]
    public function returnsEmptyCollectionWhenNoDnsRecords(): void
    {
        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection(),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertCount(0, $result);
    }

    #[Test]
    public function doesNotAssignUuidToNonRedirectRecordTypes(): void
    {
        $txtRecord = new DefaultRecord(DnsRecordType::TXT->value, 'example.com', 'v=spf1', 3600);
        $mxRecord = new DefaultRecord(DnsRecordType::MX->value, 'example.com', 'mail.example.com', 3600);

        $dnsRecords = $this->createDnsRecordCollection([$txtRecord, $mxRecord]);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $dnsRecords,
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertCount(2, $result);
        self::assertNull($result->get(0)?->redirectUuid);
        self::assertNull($result->get(1)?->redirectUuid);
    }

    #[Test]
    public function assignsUuidToMatchingARecord(): void
    {
        $record = new DefaultRecord(DnsRecordType::A->value, 'example.com', '127.0.0.1', 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertCount(1, $result);
        self::assertSame(self::SUBSCRIPTION_UUID, $result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function assignsUuidToMatchingAaaaRecord(): void
    {
        $record = new DefaultRecord(DnsRecordType::AAAA->value, 'example.com', '::1', 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertSame(self::SUBSCRIPTION_UUID, $result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function assignsUuidToMatchingCnameRecord(): void
    {
        $record = new DefaultRecord(DnsRecordType::CNAME->value, 'www.example.com', 'sandwaveio.dev', 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertSame(self::SUBSCRIPTION_UUID, $result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function assignsUuidToMatchingAliasRecord(): void
    {
        $record = new DefaultRecord(DnsRecordType::ALIAS->value, 'example.com', 'sandwaveio.dev', 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertSame(self::SUBSCRIPTION_UUID, $result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function doesNotAssignUuidWhenSourceDoesNotMatch(): void
    {
        $record = new DefaultRecord(DnsRecordType::A->value, 'other.com', '1.2.3.4', 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertNull($result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function doesNotAssignUuidWhenTargetDoesNotMatch(): void
    {
        $record = new DefaultRecord(DnsRecordType::A->value, 'example.com', '5.6.7.8', 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertNull($result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function handlesMixedRecordsWithPartialMatches(): void
    {
        $aRecord = new DefaultRecord(DnsRecordType::A->value, 'example.com', '127.0.0.1', 1200);
        $txtRecord = new DefaultRecord(DnsRecordType::TXT->value, 'example.com', 'v=spf1', 3600);
        $cnameRecord = new DefaultRecord(DnsRecordType::CNAME->value, 'www.example.com', 'sandwaveio.dev', 1200);
        $aNoMatch = new DefaultRecord(DnsRecordType::A->value, 'other.com', '9.9.9.9', 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$aRecord, $txtRecord, $cnameRecord, $aNoMatch]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertCount(4, $result);
        self::assertSame(self::SUBSCRIPTION_UUID, $result->get(0)?->redirectUuid);
        self::assertNull($result->get(1)?->redirectUuid);
        self::assertSame(self::SUBSCRIPTION_UUID, $result->get(2)?->redirectUuid);
        self::assertNull($result->get(3)?->redirectUuid);
    }

    #[Test]
    public function doesNotAssignUuidWhenActiveRedirectsIsEmpty(): void
    {
        $record = new DefaultRecord(DnsRecordType::A->value, 'example.com', '1.2.3.4', 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertNull($result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function matchesOnlyTheFirstMatchingRedirectAndBreaks(): void
    {
        $record = new DefaultRecord(DnsRecordType::A->value, 'example.com', '127.0.0.1', 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertSame(self::SUBSCRIPTION_UUID, $result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function handlesMultipleRedirectTypeRecordsWithDifferentSubscriptions(): void
    {
        $aRecord = new DefaultRecord(DnsRecordType::A->value, 'example.com', '127.0.0.1', 1200);
        $aaaaRecord = new DefaultRecord(DnsRecordType::AAAA->value, 'example.com', '::1', 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$aRecord, $aaaaRecord]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertSame(self::SUBSCRIPTION_UUID, $result->get(0)?->redirectUuid);
        self::assertSame(self::SUBSCRIPTION_UUID, $result->get(1)?->redirectUuid);
    }

    /**
     * For some reason PhpStan gets confused by the type in the collection,
     * so this private method types the collection to the known valid one
     * and is used in each test instead of having many duplicate lines.
     *
     * @param list<DnsRecordInterface> $records
     *
     * @return Collection<int, DnsRecordInterface>
     */
    private function createDnsRecordCollection(array $records = []): Collection
    {
        return new Collection($records);
    }
}
