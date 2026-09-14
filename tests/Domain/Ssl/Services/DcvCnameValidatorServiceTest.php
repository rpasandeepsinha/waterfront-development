<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl\Services;

use Pdp\Rules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\Ssl\Services\DcvCnameValidatorService;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\RtrClient\DTO\DcvDetails;
use Waterfront\Support\Helpers\DnsHelper;

#[CoversClass(DcvCnameValidatorService::class)]
class DcvCnameValidatorServiceTest extends IntegrationTestCase
{
    #[Test]
    public function returnsFalseWhenAuthoritativeZoneHasMatchingCname(): void
    {
        $dnsService = self::createMock(DnsService::class);
        $publicSuffixList = $this->makePublicSuffixListStub();

        $expectedName = '_abc.example.test';
        $expectedValue = 'aaaa.bbbb.sectigo.test';

        $dnsZone = $this->makeZoneMock([
            $this->makeRecord(DnsRecordType::CNAME, "{$expectedName}.", "{$expectedValue}."),
            $this->makeRecord(DnsRecordType::A, 'unrelated.example.test.', '1.2.3.4'),
        ]);

        $dnsService->expects(self::once())->method('getDnsZone')->with('example.test')->willReturn($dnsZone);

        $dnsHelper = self::createMock(DnsHelper::class);
        $dnsHelper->expects(self::never())->method('dnsGetRecord');

        $service = new DcvCnameValidatorService($dnsService, $publicSuffixList, $dnsHelper);

        $dcvDetails = new DcvDetails(
            status: 'suspended',
            caaRecordStatus: 'ok',
            dnsRecord: $expectedName,
            dnsType: DnsRecordType::CNAME->value,
            dnsContent: strtoupper("{$expectedValue}."),
        );

        self::assertFalse($service->isCnameMissingOrIncorrect($dcvDetails));
    }

    #[Test]
    public function returnsTrueWhenAuthoritativeZoneHasNoMatchingCname(): void
    {
        $dnsService = self::createMock(DnsService::class);
        $publicSuffixList = $this->makePublicSuffixListStub();

        $expectedName = '_abc.example.test';
        $expectedValue = 'aaaa.bbbb.sectigo.test';

        $dnsZone = $this->makeZoneMock([
            $this->makeRecord(DnsRecordType::CNAME, 'other.example.test.', 'target.example.test.'),
            $this->makeRecord(DnsRecordType::CNAME, "{$expectedName}.", 'different.target.test.'),
        ]);

        $dnsService->expects(self::once())->method('getDnsZone')->with('example.test')->willReturn($dnsZone);

        $dnsHelper = self::createMock(DnsHelper::class);
        $dnsHelper->expects(self::never())->method('dnsGetRecord');

        $service = new DcvCnameValidatorService($dnsService, $publicSuffixList, $dnsHelper);

        $dcvDetails = new DcvDetails(
            status: 'suspended',
            caaRecordStatus: 'ok',
            dnsRecord: $expectedName,
            dnsType: DnsRecordType::CNAME->value,
            dnsContent: "{$expectedValue}.",
        );

        self::assertTrue($service->isCnameMissingOrIncorrect($dcvDetails));
    }

    #[Test]
    public function returnsTrueWhenAuthoritativeZoneHasNoCnameRecords(): void
    {
        $dnsService = self::createMock(DnsService::class);
        $publicSuffixList = $this->makePublicSuffixListStub();

        $expectedName = '_abc.example.test';
        $expectedValue = 'aaaa.bbbb.sectigo.test';

        $dnsZone = $this->makeZoneMock([
            $this->makeRecord(DnsRecordType::A, "{$expectedName}.", '1.2.3.4'),
            $this->makeRecord(DnsRecordType::TXT, "{$expectedName}.", 'txt=foo'),
        ]);

        $dnsService->expects(self::once())->method('getDnsZone')->with('example.test')->willReturn($dnsZone);

        $dnsHelper = self::createMock(DnsHelper::class);
        $dnsHelper->expects(self::never())->method('dnsGetRecord');

        $service = new DcvCnameValidatorService($dnsService, $publicSuffixList, $dnsHelper);

        $dcvDetails = new DcvDetails(
            status: 'suspended',
            caaRecordStatus: 'ok',
            dnsRecord: "{$expectedName}.",
            dnsType: DnsRecordType::CNAME->value,
            dnsContent: $expectedValue,
        );

        self::assertTrue($service->isCnameMissingOrIncorrect($dcvDetails));
    }

    #[Test]
    public function fallsBackToPublicDnsAndReturnsTrueWhenNoAnswers(): void
    {
        $dnsService = self::createMock(DnsService::class);
        $publicSuffixList = $this->makePublicSuffixListStub();

        $expectedName = '_unlikely-nonexistent-sub.example.test';
        $expectedValue = 'target.example.test';

        $dnsService
            ->expects(self::once())
            ->method('getDnsZone')
            ->with('example.test')
            ->willThrowException(new DnsZoneNotFoundException('no zone'));

        $dnsHelper = self::createMock(DnsHelper::class);
        $dnsHelper->expects(self::once())->method('dnsGetRecord')->with($expectedName, DNS_CNAME)->willReturn([]);

        $service = new DcvCnameValidatorService($dnsService, $publicSuffixList, $dnsHelper);

        $dcvDetails = new DcvDetails(
            status: 'suspended',
            caaRecordStatus: 'ok',
            dnsRecord: $expectedName,
            dnsType: DnsRecordType::CNAME->value,
            dnsContent: $expectedValue,
        );

        self::assertTrue($service->isCnameMissingOrIncorrect($dcvDetails));
    }

    #[Test]
    public function fallsBackToPublicDnsAndReturnsFalseWhenMatchingAnswer(): void
    {
        $dnsService = self::createMock(DnsService::class);
        $publicSuffixList = $this->makePublicSuffixListStub();

        $expectedName = '_abc.example.test';
        $expectedValue = 'aaaa.bbbb.sectigo.test';

        $dnsService
            ->expects(self::once())
            ->method('getDnsZone')
            ->with('example.test')
            ->willThrowException(new DnsZoneNotFoundException('no zone'));

        $dnsHelper = self::createMock(DnsHelper::class);
        $dnsHelper
            ->expects(self::once())
            ->method('dnsGetRecord')
            ->with($expectedName, DNS_CNAME)
            ->willReturn([
                ['host' => "{$expectedName}.", 'type' => 'CNAME', 'target' => "{$expectedValue}."],
            ]);

        $service = new DcvCnameValidatorService($dnsService, $publicSuffixList, $dnsHelper);

        $dcvDetails = new DcvDetails(
            status: 'suspended',
            caaRecordStatus: 'ok',
            dnsRecord: $expectedName,
            dnsType: DnsRecordType::CNAME->value,
            dnsContent: strtoupper($expectedValue) . '.',
        );

        self::assertFalse($service->isCnameMissingOrIncorrect($dcvDetails));
    }

    private function makeRealRules(): Rules
    {
        $publicSuffixData = <<<PSL
        test
        PSL;

        return Rules::fromString($publicSuffixData);
    }

    private function makePublicSuffixListStub(): PublicSuffixList
    {
        $publicSuffixList = self::createStub(PublicSuffixList::class);
        $publicSuffixList->method('getRules')->willReturn($this->makeRealRules());

        return $publicSuffixList;
    }

    /**
     * @param array<int, DnsRecordInterface> $records
     */
    private function makeZoneMock(array $records): DnsZone
    {
        $dnsZone = self::createStub(DnsZone::class);
        $dnsZone->method('getRecords')->willReturn($records);

        return $dnsZone;
    }

    private function makeRecord(DnsRecordType $type, string $name, string $content): DnsRecordInterface
    {
        $record = self::createStub(DnsRecordInterface::class);
        $record->method('getType')->willReturn($type->value);
        $record->method('getName')->willReturn($name);
        $record->method('getContent')->willReturn($content);

        return $record;
    }
}
