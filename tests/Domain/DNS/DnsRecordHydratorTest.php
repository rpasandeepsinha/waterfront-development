<?php

declare(strict_types=1);

namespace Tests\Domain\DNS;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\DNS\Entities\DnsRecords\AliasRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\CnameRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\MxRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\NsRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\SrvRecord;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Hydrators\DnsRecordHydrator;

#[CoversClass(DnsRecordHydrator::class)]
class DnsRecordHydratorTest extends TestCase
{
    private Translator&MockInterface $mockTranslator;

    private DnsRecordHydrator $hydrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockTranslator = self::mock(Translator::class);
        $this->app->bind(Translator::class, fn () => $this->mockTranslator);
        $this->hydrator = $this->app->make(DnsRecordHydrator::class);
    }

    #[Test]
    public function invalidData(): void
    {
        $this->expectException(ValidationException::class);
        $translateMessage = 'Dit veld dient een geldig fqdn te zijn.';
        $this->mockTranslator->expects('get')->atLeast()->twice()->andReturn($translateMessage);

        $data = [
            'type' => 'A',
            'name' => 'googlecom',
            'content' => '127.0.0',
            'ttl' => 600,
        ];

        $this->hydrator->hydrate($data);
    }

    #[Test]
    public function bypassValidation(): void
    {
        $data = [
            'type' => 'A',
            'name' => 'googlecom',
            'content' => '127.0.0',
            'ttl' => 600,
        ];

        $record = $this->hydrator->hydrate($data, false);
        self::assertInstanceOf(DefaultRecord::class, $record);

        self::assertSame($data['type'], $record->getType());
        self::assertSame($data['name'], $record->getName());
        self::assertSame($data['name'] . '.', $record->getNamePlusDot());
        self::assertSame($data['content'], $record->getContent());
    }

    #[Test]
    public function hydrateARecord(): void
    {
        $data = [
            'type' => 'A',
            'name' => 'google.com',
            'content' => '127.0.0.1',
            'ttl' => 600,
            'disabled' => true,
        ];
        $record = $this->hydrator->hydrate($data);

        self::assertInstanceOf(DefaultRecord::class, $record);
        self::assertSame($data['type'], $record->getType());
        self::assertSame($data['name'], $record->getName());
        self::assertSame($data['name'] . '.', $record->getNamePlusDot());
        self::assertSame($data['content'], $record->getContent());
        self::assertSame($data['ttl'], $record->getTtl());
        self::assertEquals($data['disabled'], $record->isDisabled());
    }

    #[Test]
    public function dehydrateARecord(): void
    {
        $record = new DefaultRecord('A', 'google.com', '127.0.0.1', 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'type' => 'A',
            'name' => 'google.com',
            'content' => '127.0.0.1',
            'ttl' => 600,
            'disabled' => true,
        ];
        self::assertEqualsCanonicalizing($expected, $data);
    }

    #[Test]
    public function hydrateAaaaRecord(): void
    {
        $data = [
            'type' => 'AAAA',
            'name' => 'google.com',
            'content' => '2001:1460:2:0:1c21:1fff:fe00:1aa',
            'ttl' => 600,
            'disabled' => true,
        ];
        $record = $this->hydrator->hydrate($data);

        self::assertInstanceOf(DefaultRecord::class, $record);
        self::assertSame($data['type'], $record->getType());
        self::assertSame($data['name'], $record->getName());
        self::assertSame($data['name'] . '.', $record->getNamePlusDot());
        self::assertSame($data['content'], $record->getContent());
        self::assertSame($data['ttl'], $record->getTtl());
        self::assertEquals($data['disabled'], $record->isDisabled());
    }

    #[Test]
    public function dehydrateAaaaRecord(): void
    {
        $record = new DefaultRecord('AAAA', 'google.com', '2001:1460:2:0:1c21:1fff:fe00:1aa', 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'type' => 'AAAA',
            'name' => 'google.com',
            'content' => '2001:1460:2:0:1c21:1fff:fe00:1aa',
            'ttl' => 600,
            'disabled' => true,
        ];
        self::assertEqualsCanonicalizing($expected, $data);
    }

    #[Test]
    public function hydrateCnameRecord(): void
    {
        $data = [
            'type' => 'CNAME',
            'name' => 'google.com',
            'content' => 'google.nl',
            'ttl' => 600,
            'disabled' => true,
        ];
        $record = $this->hydrator->hydrate($data);

        self::assertInstanceOf(CnameRecord::class, $record);
        self::assertSame($data['type'], $record->getType());
        self::assertSame($data['name'], $record->getName());
        self::assertSame($data['name'] . '.', $record->getNamePlusDot());
        self::assertSame($data['content'], $record->getContent());
        self::assertSame($data['content'] . '.', $record->getContentPlusDot());
        self::assertSame($data['ttl'], $record->getTtl());
        self::assertEquals($data['disabled'], $record->isDisabled());
    }

    #[Test]
    public function hydrateInvalidCnameRecord(): void
    {
        /*
         * We shouldn't validate when fetching the zone, but only when creating or updating records.
         * @see https://yh-jira.atlassian.net/browse/SWD-9847
         */
        $data = [
            'type' => 'CNAME',
            'name' => 'google.com',
            'content' => 'test,google.nl', // comma is invalid
            'ttl' => 600,
            'disabled' => true,
        ];

        $translateMessage = 'Dit veld dient een geldig fqdn te zijn.';
        $this->mockTranslator->expects('get')->atLeast()->twice()->andReturn($translateMessage);

        $record = $this->hydrator->hydrate($data, false); // don't validate

        self::assertInstanceOf(CnameRecord::class, $record);
        self::assertSame($data['type'], $record->getType());
        self::assertSame($data['name'], $record->getName());
        self::assertSame($data['name'] . '.', $record->getNamePlusDot());
        self::assertSame($data['content'], $record->getContent());
        self::assertSame($data['content'] . '.', $record->getContentPlusDot());
        self::assertSame($data['ttl'], $record->getTtl());
        self::assertEquals($data['disabled'], $record->isDisabled());

        $this->expectException(ValidationException::class);
        self::expectExceptionMessageIs($translateMessage);

        $this->hydrator->hydrate($data); // do validate
    }

    #[Test]
    public function dehydrateCnameRecord(): void
    {
        $record = new CnameRecord('google.com', 'google.nl', 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'type' => 'CNAME',
            'name' => 'google.com',
            'content' => 'google.nl',
            'ttl' => 600,
            'disabled' => true,
        ];
        self::assertEqualsCanonicalizing($expected, $data);
    }

    #[Test]
    public function hydrateCnameRecordWithIdnNameAndContent(): void
    {
        $record = $this->hydrator->hydrate([
            'type' => DnsRecordType::CNAME->value,
            'name' => 'www.sportgemälde.de',
            'content' => 'target.sportgemälde.de',
            'ttl' => 600,
            'disabled' => false,
        ]);

        self::assertInstanceOf(CnameRecord::class, $record);
        self::assertSame('www.xn--sportgemlde-s8a.de', $record->getName());
        self::assertSame('target.xn--sportgemlde-s8a.de', $record->getContent());
    }

    #[Test]
    public function hydrateCnameRecordWithMixedCaseAsciiContent(): void
    {
        $content = 'abbadcf82b8e29d246017fec6c52e8dc.3d763c840eac90b65b883772360a6d25.NXl18ZS0Mzz8EImpRmKr.sectigo.com';
        $record = $this->hydrator->hydrate([
            'type' => DnsRecordType::CNAME->value,
            'name' => '_990def0862163bf175976daceb192883.domain.com',
            'content' => $content,
            'ttl' => 600,
            'disabled' => false,
        ]);

        self::assertInstanceOf(CnameRecord::class, $record);
        self::assertSame($content, $record->getContent());
    }

    #[Test]
    public function hydrateAliasRecord(): void
    {
        $data = [
            'type' => 'ALIAS',
            'name' => 'google.com',
            'content' => 'google.nl',
            'ttl' => 600,
            'disabled' => true,
        ];
        $record = $this->hydrator->hydrate($data);

        self::assertInstanceOf(AliasRecord::class, $record);
        self::assertSame($data['type'], $record->getType());
        self::assertSame($data['name'], $record->getName());
        self::assertSame($data['name'] . '.', $record->getNamePlusDot());
        self::assertSame($data['content'], $record->getContent());
        self::assertSame($data['content'] . '.', $record->getContentPlusDot());
        self::assertSame($data['ttl'], $record->getTtl());
        self::assertEquals($data['disabled'], $record->isDisabled());
    }

    #[Test]
    public function hydrateInvalidAliasRecord(): void
    {
        /*
         * We shouldn't validate when fetching the zone, but only when creating or updating records.
         * @see https://yh-jira.atlassian.net/browse/SWD-9847
         */
        $data = [
            'type' => 'ALIAS',
            'name' => 'google.com',
            'content' => 'test,google.nl', // comma is invalid
            'ttl' => 600,
            'disabled' => true,
        ];
        $translateMessage = 'Dit veld dient een geldig fqdn te zijn.';
        $this->mockTranslator->expects('get')->atLeast()->twice()->andReturn($translateMessage);
        $record = $this->hydrator->hydrate($data, false); // don't validate

        self::assertInstanceOf(AliasRecord::class, $record);
        self::assertSame($data['type'], $record->getType());
        self::assertSame($data['name'], $record->getName());
        self::assertSame($data['name'] . '.', $record->getNamePlusDot());
        self::assertSame($data['content'], $record->getContent());
        self::assertSame($data['content'] . '.', $record->getContentPlusDot());
        self::assertSame($data['ttl'], $record->getTtl());
        self::assertEquals($data['disabled'], $record->isDisabled());

        $this->expectException(ValidationException::class);
        self::expectExceptionMessageIs($translateMessage);

        $this->hydrator->hydrate($data); // do validate
    }

    #[Test]
    public function dehydrateAliasRecord(): void
    {
        $record = new AliasRecord('google.com', 'google.nl', 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'type' => 'ALIAS',
            'name' => 'google.com',
            'content' => 'google.nl',
            'ttl' => 600,
            'disabled' => true,
        ];
        self::assertEqualsCanonicalizing($expected, $data);
    }

    #[Test]
    public function hydrateMxRecord(): void
    {
        $data = [
            'type' => 'MX',
            'name' => 'google.com',
            'content' => 'mx.spamservice.nl',
            'priority' => 10,
            'ttl' => 600,
            'disabled' => true,
        ];
        $record = $this->hydrator->hydrate($data);

        self::assertInstanceOf(MxRecord::class, $record);
        self::assertSame($data['type'], $record->getType());
        self::assertSame($data['name'], $record->getName());
        self::assertSame($data['name'] . '.', $record->getNamePlusDot());
        self::assertSame($data['content'], $record->getContent());
        self::assertSame($data['content'] . '.', $record->getContentPlusDot());
        self::assertSame($data['priority'], $record->getPriority());
        self::assertSame($data['ttl'], $record->getTtl());
        self::assertEquals($data['disabled'], $record->isDisabled());
    }

    #[Test]
    public function dehydrateMxRecord(): void
    {
        $record = new MxRecord('google.com', 'mx.spamservice.nl', 10, 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'name' => 'google.com',
            'content' => 'mx.spamservice.nl',
            'type' => 'MX',
            'ttl' => 600,
            'disabled' => true,
            'priority' => 10,
        ];
        self::assertSame($expected, $data);
    }

    #[Test]
    public function hydrateMxRecordWithIdnNameAndContent(): void
    {
        $record = $this->hydrator->hydrate([
            'type' => DnsRecordType::MX->value,
            'name' => 'sportgemälde.de',
            'content' => 'mail.sportgemälde.de',
            'priority' => 10,
            'ttl' => 600,
            'disabled' => false,
        ]);

        self::assertInstanceOf(MxRecord::class, $record);
        self::assertSame('xn--sportgemlde-s8a.de', $record->getName());
        self::assertSame('mail.xn--sportgemlde-s8a.de', $record->getContent());
    }

    #[Test]
    public function hydrateSpfRecord(): void
    {
        $data = [
            'type' => 'SPF',
            'name' => 'google.com',
            'content' => 'v=spf1 include:spf.spamservice.nl mx a ~all',
            'ttl' => 600,
            'disabled' => true,
        ];
        $record = $this->hydrator->hydrate($data);

        self::assertInstanceOf(DefaultRecord::class, $record);
        self::assertSame($data['type'], $record->getType());
        self::assertSame($data['name'], $record->getName());
        self::assertSame($data['name'] . '.', $record->getNamePlusDot());
        self::assertSame($data['ttl'], $record->getTtl());
        self::assertEquals($data['disabled'], $record->isDisabled());
    }

    #[Test]
    public function dehydrateSpfRecord(): void
    {
        $record = new DefaultRecord('SPF', 'google.com', 'v=spf1 include:spf.spamservice.nl mx a ~all', 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'type' => 'SPF',
            'name' => 'google.com',
            'content' => 'v=spf1 include:spf.spamservice.nl mx a ~all',
            'ttl' => 600,
            'disabled' => true,
        ];
        self::assertEqualsCanonicalizing($expected, $data);
    }

    #[Test]
    public function hydrateSrvRecord(): void
    {
        $data = [
            'type' => 'SRV',
            'name' => '_sip._tcp.example.com',
            'content' => 'bigbox.example.com',
            'priority' => 10,
            'weight' => 10,
            'port' => 5000,
            'ttl' => 600,
            'disabled' => true,
        ];
        $record = $this->hydrator->hydrate($data);

        self::assertInstanceOf(SrvRecord::class, $record);
        self::assertSame($data['type'], $record->getType());
        self::assertSame($data['name'], $record->getName());
        self::assertSame($data['name'] . '.', $record->getNamePlusDot());
        self::assertSame($data['content'], $record->getContent());
        self::assertSame($data['content'] . '.', $record->getContentPlusDot());
        self::assertSame($data['priority'], $record->getPriority());
        self::assertSame($data['weight'], $record->getWeight());
        self::assertSame($data['port'], $record->getPort());
        self::assertSame($data['ttl'], $record->getTtl());
        self::assertEquals($data['disabled'], $record->isDisabled());
    }

    #[Test]
    public function hydrateSrvRecordWithIdnNameAndContent(): void
    {
        $record = $this->hydrator->hydrate([
            'type' => DnsRecordType::SRV->value,
            'name' => '_sip._tcp.sportgemälde.de',
            'content' => 'server.sportgemälde.de',
            'priority' => 10,
            'weight' => 10,
            'port' => 5000,
            'ttl' => 600,
            'disabled' => false,
        ]);

        self::assertInstanceOf(SrvRecord::class, $record);
        self::assertSame('_sip._tcp.xn--sportgemlde-s8a.de', $record->getName());
        self::assertSame('server.xn--sportgemlde-s8a.de', $record->getContent());
    }

    #[Test]
    public function dehydrateSrvRecord(): void
    {
        $record = new SrvRecord('_sip._tcp.example.com', 'bigbox.example.com', 10, 10, 5000, 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'name' => '_sip._tcp.example.com',
            'content' => 'bigbox.example.com',
            'type' => 'SRV',
            'ttl' => 600,
            'disabled' => true,
            'priority' => 10,
            'weight' => 10,
            'port' => 5000,
        ];
        self::assertSame($expected, $data);
    }

    #[Test]
    public function txtRecord(): void
    {
        $data = [
            'type' => 'TXT',
            'name' => 'google.com',
            'content' => 'random text',
            'ttl' => 600,
            'disabled' => true,
        ];
        $record = $this->hydrator->hydrate($data);

        self::assertInstanceOf(DefaultRecord::class, $record);
        self::assertSame($data['type'], $record->getType());
        self::assertSame($data['name'], $record->getName());
        self::assertSame($data['name'] . '.', $record->getNamePlusDot());
        self::assertSame($data['ttl'], $record->getTtl());
        self::assertEquals($data['disabled'], $record->isDisabled());
    }

    #[Test]
    public function hydrateTxtRecordWithIdnNameAndUnicodeContent(): void
    {
        $record = $this->hydrator->hydrate([
            'type' => DnsRecordType::TXT->value,
            'name' => 'txt.sportgemälde.de',
            'content' => 'v=spf1 include:sportgemälde.de ~all',
            'ttl' => 600,
            'disabled' => false,
        ]);

        self::assertInstanceOf(DefaultRecord::class, $record);
        self::assertSame('txt.xn--sportgemlde-s8a.de', $record->getName());
        self::assertSame('v=spf1 include:sportgemälde.de ~all', $record->getContent());
    }

    #[Test]
    public function dehydrateTxtRecord(): void
    {
        $record = new DefaultRecord('TXT', 'google.com', 'random text', 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'type' => 'TXT',
            'name' => 'google.com',
            'content' => 'random text',
            'ttl' => 600,
            'disabled' => true,
        ];
        self::assertEqualsCanonicalizing($expected, $data);
    }

    #[Test]
    public function hydrateNsRecordWithIdnNameAndContent(): void
    {
        $record = $this->hydrator->hydrate([
            'type' => DnsRecordType::NS->value,
            'name' => 'delegated.sportgemälde.de',
            'content' => 'ns1.sportgemälde.de',
            'ttl' => 600,
            'disabled' => false,
        ]);

        self::assertInstanceOf(NsRecord::class, $record);
        self::assertSame('delegated.xn--sportgemlde-s8a.de', $record->getName());
        self::assertSame('ns1.xn--sportgemlde-s8a.de', $record->getContent());
    }

    #[Test]
    public function hydrateUnknownRecordType(): void
    {
        $data = [
            'type' => 'UNKNOWN',
            'name' => 'google.com',
            'content' => 'unknown data format',
            'ttl' => 600,
            'disabled' => true,
        ];
        $record = $this->hydrator->hydrate($data);

        self::assertInstanceOf(DefaultRecord::class, $record);
        self::assertSame($data['type'], $record->getType());
        self::assertSame($data['name'], $record->getName());
        self::assertSame($data['name'] . '.', $record->getNamePlusDot());
        self::assertSame($data['content'], $record->getContent());
        self::assertSame($data['ttl'], $record->getTtl());
        self::assertEquals($data['disabled'], $record->isDisabled());
    }

    #[Test]
    public function dehydrateUnknownRecordType(): void
    {
        $record = new DefaultRecord('UNKNOWN', 'google.com', 'unknown data format', 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'type' => 'UNKNOWN',
            'name' => 'google.com',
            'content' => 'unknown data format',
            'ttl' => 600,
            'disabled' => true,
        ];
        self::assertEqualsCanonicalizing($expected, $data);
    }
}
