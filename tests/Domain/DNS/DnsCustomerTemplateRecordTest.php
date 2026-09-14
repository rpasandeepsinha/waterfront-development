<?php

declare(strict_types=1);

namespace Tests\Domain\DNS;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\DNS\Entities\DnsRecords\CaaRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\CnameRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\MxRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\SrvRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\TlsaRecord;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Exceptions\DnsTemplateNotConvertibleException;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplateRecord;
use Waterfront\Domain\DNS\Services\DnsRecordConverter;

#[CoversClass(DnsCustomerTemplateRecord::class)]
class DnsCustomerTemplateRecordTest extends TestCase
{
    private DnsRecordConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->converter = new DnsRecordConverter();
    }

    /**
     *
     * @param class-string $expectedType
     * @param mixed[]      $payload
     * @param mixed[]      $expectedRecordArray
     */
    #[DataProvider('templateRecordDataProvider')]
    #[Test]
    public function records(
        array $payload,
        string $expectedType,
        ?array $expectedRecordArray,
        ?string $exception,
        ?string $exceptionMessage,
    ): void {
        if ($exception !== null && $exceptionMessage !== null) {
            // $expectedRecordArray should be null when expecting an exception
            $this->expectExceptionMessageIs($exceptionMessage);
        }

        $recordModel = new DnsCustomerTemplateRecord()->fill($payload);

        $converted = $this->converter->transformToTypedRecord($recordModel, 'mydomain.com');

        self::assertInstanceOf($expectedType, $converted);
        self::assertEquals($expectedRecordArray, $converted->toArray());
    }

    /**
     * @return mixed[]
     */
    public static function templateRecordDataProvider(): array
    {
        return [
            [
                [
                    'template_id' => 1,
                    'name' => 'test.@',
                    'content' => '127.0.0.1',
                    'type' => 'A',
                    'ttl' => 3600,
                    'disabled' => false,
                ],
                DefaultRecord::class,
                [
                    'name' => 'test.mydomain.com',
                    'content' => '127.0.0.1',
                    'type' => 'A',
                    'ttl' => 3600,
                    'disabled' => false,
                ],
                null,
                null,
            ],
            [
                [
                    'template_id' => 1,
                    'name' => 'bla.test.@',
                    'content' => 'test.@',
                    'type' => 'CNAME',
                    'ttl' => 600,
                    'disabled' => false,
                ],
                CnameRecord::class,
                [
                    'name' => 'bla.test.mydomain.com',
                    'content' => 'test.mydomain.com',
                    'type' => 'CNAME',
                    'ttl' => 600,
                    'disabled' => false,
                ],
                null,
                null,
            ],
            [
                [
                    'template_id' => 1,
                    'name' => 'bla.test.@',
                    'content' => 'test.mydomain.com',
                    'type' => 'SRV',
                    'priority' => 23,
                    'weight' => 55,
                    'port' => 123,
                    'ttl' => 600,
                    'disabled' => false,
                ],
                SrvRecord::class,
                [
                    'name' => 'bla.test.mydomain.com',
                    'content' => 'test.mydomain.com',
                    'type' => 'SRV',
                    'ttl' => 600,
                    'priority' => 23,
                    'disabled' => false,
                    'weight' => 55,
                    'port' => 123,
                ],
                null,
                null,
            ],
            [
                [
                    'template_id' => 1,
                    'name' => 'bla.test.mydomain.com',
                    'content' => 'test.mydomain.com',
                    'type' => 'SRV',
                    'weight' => 55,
                    'ttl' => 600,
                    'disabled' => false,
                ],
                SrvRecord::class,
                null,
                DnsTemplateNotConvertibleException::class,
                'DNS customer template record with name bla.test.mydomain.com not convertible to: SRV because of: No priority set.',
            ],
            [
                [
                    'template_id' => 1,
                    'name' => 'mydomain.com',
                    'content' => 'v=spf1 include:spf.spamservice.nl mx a ~all',
                    'priority' => 20,
                    'type' => 'MX',
                    'ttl' => 600,
                    'disabled' => false,
                ],
                MxRecord::class,
                [
                    'name' => 'mydomain.com',
                    'content' => 'v=spf1 include:spf.spamservice.nl mx a ~all',
                    'type' => 'MX',
                    'ttl' => 600,
                    'priority' => 20,
                    'disabled' => false,
                ],
                null,
                null,
            ],
            [
                [
                    'template_id' => 1,
                    'name' => 'mydomain.com',
                    'content' => '0 issue "comodo.com"',
                    'type' => 'CAA',
                    'ttl' => 600,
                    'disabled' => false,
                ],
                CaaRecord::class,
                [
                    'name' => 'mydomain.com',
                    'content' => '0 issue "comodo.com"',
                    'type' => 'CAA',
                    'ttl' => 600,
                    'disabled' => false,
                ],
                null,
                null,
            ],
            [
                [
                    'template_id' => 1,
                    'name' => 'mydomain.com',
                    'content' => '_25._tcp.mail.one-example.guide 3 1 1 da92d453eed5c0aede4',
                    'type' => 'TLSA',
                    'ttl' => 600,
                    'disabled' => false,
                ],
                TlsaRecord::class,
                [
                    'name' => 'mydomain.com',
                    'content' => '_25._tcp.mail.one-example.guide 3 1 1 da92d453eed5c0aede4',
                    'type' => 'TLSA',
                    'ttl' => 600,
                    'disabled' => false,
                ],
                null,
                null,
            ],
        ];
    }

    #[Test]
    public function idnTemplateRecordName(): void
    {
        $recordModel = new DnsCustomerTemplateRecord()->fill([
            'template_id' => 1,
            'name' => 'www.@',
            'content' => '127.0.0.1',
            'type' => DnsRecordType::A->value,
            'ttl' => 3600,
            'disabled' => false,
        ]);

        $converted = $this->converter->transformToTypedRecord($recordModel, 'sportgemälde.de');

        self::assertInstanceOf(DefaultRecord::class, $converted);
        self::assertSame('www.xn--sportgemlde-s8a.de', $converted->getName());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('fqdnTargetContentDataProvider')]
    #[Test]
    public function idnFqdnTargetRecordContent(array $payload, string $expectedContent): void
    {
        $recordModel = new DnsCustomerTemplateRecord()->fill($payload);

        $converted = $this->converter->transformToTypedRecord($recordModel, 'sportgemälde.de');

        self::assertSame($expectedContent, $converted->getContent());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function fqdnTargetContentDataProvider(): array
    {
        return [
            'cname' => [
                [
                    'template_id' => 1,
                    'name' => 'www.@',
                    'content' => 'target.sportgemälde.de',
                    'type' => DnsRecordType::CNAME->value,
                    'ttl' => 600,
                    'disabled' => false,
                ],
                'target.xn--sportgemlde-s8a.de',
            ],
            'srv' => [
                [
                    'template_id' => 1,
                    'name' => '_sip._tcp.@',
                    'content' => 'server.sportgemälde.de',
                    'type' => DnsRecordType::SRV->value,
                    'priority' => 10,
                    'weight' => 10,
                    'port' => 5000,
                    'ttl' => 600,
                    'disabled' => false,
                ],
                'server.xn--sportgemlde-s8a.de',
            ],
        ];
    }

    #[Test]
    public function preservesMixedCaseAsciiCnameContent(): void
    {
        $content = 'abbadcf82b8e29d246017fec6c52e8dc.3d763c840eac90b65b883772360a6d25.NXl18ZS0Mzz8EImpRmKr.sectigo.com';
        $recordModel = new DnsCustomerTemplateRecord()->fill([
            'template_id' => 1,
            'name' => '_990def0862163bf175976daceb192883.@',
            'content' => $content,
            'type' => DnsRecordType::CNAME->value,
            'ttl' => 600,
            'disabled' => false,
        ]);

        $converted = $this->converter->transformToTypedRecord($recordModel, 'domain.com');

        self::assertSame($content, $converted->getContent());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('literalContentDataProvider')]
    #[Test]
    public function literalContentRecordWithIdnContent(array $payload, string $expectedContent): void
    {
        $recordModel = new DnsCustomerTemplateRecord()->fill($payload);

        $converted = $this->converter->transformToTypedRecord($recordModel, 'sportgemälde.de');

        self::assertSame($expectedContent, $converted->getContent());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function literalContentDataProvider(): array
    {
        return [
            'txt' => [
                [
                    'template_id' => 1,
                    'name' => 'txt.@',
                    'content' => 'v=spf1 include:sportgemälde.de ~all',
                    'type' => DnsRecordType::TXT->value,
                    'ttl' => 600,
                    'disabled' => false,
                ],
                'v=spf1 include:sportgemälde.de ~all',
            ],
            'caa' => [
                [
                    'template_id' => 1,
                    'name' => '@',
                    'content' => '0 issue "sportgemälde.de"',
                    'type' => DnsRecordType::CAA->value,
                    'ttl' => 600,
                    'disabled' => false,
                ],
                '0 issue "sportgemälde.de"',
            ],
        ];
    }
}
