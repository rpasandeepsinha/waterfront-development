<?php

declare(strict_types=1);

namespace Tests\Domain\Redirects\Mappers;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\LegacyRedirectingServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\Redirects\Mappers\RedirectDnsSubscriptionMapper;

#[CoversClass(RedirectDnsSubscriptionMapper::class)]
class RedirectDnsSubscriptionMapperTest extends IntegrationTestCase
{
    private const string SUBSCRIPTION_UUID = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

    private const string REDIRECT_DNS = 'sandwaveio.dev';

    private const string REDIRECT_IPV4 = '127.0.0.1';

    private const string REDIRECT_IPV6 = '::1';

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
    public function doesNotAssignUuidToARecordPointingAtTheRedirectService(): void
    {
        $record = new DefaultRecord(DnsRecordType::A->value, 'example.com', self::REDIRECT_IPV4, 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertCount(1, $result);
        self::assertNull($result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function doesNotAssignUuidToAaaaRecordPointingAtTheRedirectService(): void
    {
        $record = new DefaultRecord(DnsRecordType::AAAA->value, 'example.com', self::REDIRECT_IPV6, 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertNull($result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function doesNotAssignUuidToARecordPointingAtALegacyRedirectingServer(): void
    {
        $legacyServer = LegacyRedirectingServerFactory::new()->createOne();

        $record = new DefaultRecord(DnsRecordType::A->value, 'example.com', $legacyServer->ipv4, 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertNull($result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function doesNotAssignUuidToAaaaRecordPointingAtALegacyRedirectingServer(): void
    {
        $legacyServer = LegacyRedirectingServerFactory::new()->createOne();
        self::assertNotNull($legacyServer->ipv6);

        $record = new DefaultRecord(DnsRecordType::AAAA->value, 'example.com', $legacyServer->ipv6, 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertNull($result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function assignsUuidToMatchingCnameRecord(): void
    {
        $record = new DefaultRecord(DnsRecordType::CNAME->value, 'www.example.com', self::REDIRECT_DNS, 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertSame(self::SUBSCRIPTION_UUID, $result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function assignsUuidToMatchingAliasRecord(): void
    {
        $record = new DefaultRecord(DnsRecordType::ALIAS->value, 'example.com', self::REDIRECT_DNS, 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertSame(self::SUBSCRIPTION_UUID, $result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function assignsUuidToMatchingCnameRecordWithTrailingDot(): void
    {
        $record = new DefaultRecord(DnsRecordType::CNAME->value, 'www.example.com', self::REDIRECT_DNS . '.', 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertSame(self::SUBSCRIPTION_UUID, $result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function doesNotAssignUuidToARecordWithUnrelatedContent(): void
    {
        $record = new DefaultRecord(DnsRecordType::A->value, 'other.com', '1.2.3.4', 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertNull($result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function doesNotAssignUuidWhenCnameTargetDoesNotMatch(): void
    {
        $record = new DefaultRecord(DnsRecordType::CNAME->value, 'www.example.com', 'other.example.net', 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertNull($result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function handlesMixedRecordsWithPartialMatches(): void
    {
        $aRecordOnRedirectIp = new DefaultRecord(DnsRecordType::A->value, 'example.com', self::REDIRECT_IPV4, 1200);
        $txtRecord = new DefaultRecord(DnsRecordType::TXT->value, 'example.com', 'v=spf1', 3600);
        $cnameRecord = new DefaultRecord(DnsRecordType::CNAME->value, 'www.example.com', self::REDIRECT_DNS, 1200);
        $aNoMatch = new DefaultRecord(DnsRecordType::A->value, 'other.com', '9.9.9.9', 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$aRecordOnRedirectIp, $txtRecord, $cnameRecord, $aNoMatch]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertCount(4, $result);
        self::assertNull($result->get(0)?->redirectUuid);
        self::assertNull($result->get(1)?->redirectUuid);
        self::assertSame(self::SUBSCRIPTION_UUID, $result->get(2)?->redirectUuid);
        self::assertNull($result->get(3)?->redirectUuid);
    }

    #[Test]
    public function doesNotAssignUuidWhenNoLegacyRedirectingServersExist(): void
    {
        $record = new DefaultRecord(DnsRecordType::A->value, 'example.com', '1.2.3.4', 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertNull($result->firstOrFail()->redirectUuid);
    }

    #[Test]
    public function assignsUuidToEveryMatchingRecordInTheCollection(): void
    {
        $cnameRecord = new DefaultRecord(DnsRecordType::CNAME->value, 'www.example.com', self::REDIRECT_DNS, 1200);
        $aliasRecord = new DefaultRecord(DnsRecordType::ALIAS->value, 'example.com', self::REDIRECT_DNS, 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$cnameRecord, $aliasRecord]),
            Uuid::fromString(self::SUBSCRIPTION_UUID),
        );

        self::assertSame(self::SUBSCRIPTION_UUID, $result->get(0)?->redirectUuid);
        self::assertSame(self::SUBSCRIPTION_UUID, $result->get(1)?->redirectUuid);
    }

    #[Test]
    public function assignsTheGivenSubscriptionUuidToEachMatchingRecord(): void
    {
        $otherUuid = Uuid::uuid4();
        $record = new DefaultRecord(DnsRecordType::ALIAS->value, 'example.com', self::REDIRECT_DNS, 1200);

        $result = $this->mapper->addSubscriptionUuidToDnsRecords(
            $this->createDnsRecordCollection([$record]),
            $otherUuid,
        );

        self::assertSame($otherUuid->toString(), $result->firstOrFail()->redirectUuid);
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
