<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\DnsRecordValidation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Domain\DNS\Helpers\DnsRecordValidatorHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Services\DnsRecordsValidationService;
use Waterfront\Domain\DNS\Validators\DnsRecordValidator;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(DnsRecordValidator::class)]
#[CoversClass(DnsRecordsValidationService::class)]
class SrvRecordValidationTest extends IntegrationTestCase
{
    use DnsRecordValidatorHelper;

    #[Test]
    public function missingContent(): void
    {
        $data = [
            'type'     => 'SRV',
            'name'     => '_sip._tcp.example.com',
            'priority' => '10',
            'weight'   => 10,
            'port'     => 5000,
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['content' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]], $validator->errors()->toArray());
    }

    #[Test]
    public function invalidContentType(): void
    {
        $data = [
            'type'     => 'SRV',
            'name'     => '_sip._tcp.example.com',
            'content'  => 1,
            'priority' => '10',
            'weight'   => 10,
            'port'     => 5000,
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['content' => [self::resolve(TranslatorInterface::class)->translate('validation.string')]], $validator->errors()->toArray());
    }

    #[Test]
    public function invalidContentFormatWithSpace(): void
    {
        $data = [
            'type'     => 'SRV',
            'name'     => '_sip._tcp.example.com',
            'content'  => 'bigboxexample .com',
            'priority' => '10',
            'weight'   => 10,
            'port'     => 5000,
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['content' => [self::resolve(TranslatorInterface::class)->translate('validation.fqdn')]],
            $validator->errors()->toArray()
        );
    }

    #[Test]
    public function missingPriority(): void
    {
        $data = [
            'type'     => 'SRV',
            'name'     => '_sip._tcp.example.com',
            'content'  => 'bigbox.example.com',
            'weight'   => 10,
            'port'     => 5000,
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['priority' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]], $validator->errors()->toArray());
    }

    #[Test]
    public function invalidPriorityType(): void
    {
        $data = [
            'type'     => 'SRV',
            'name'     => '_sip._tcp.example.com',
            'content'  => 'bigbox.example.com',
            'priority' => 'text',
            'weight'   => 10,
            'port'     => 5000,
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['priority' => [self::resolve(TranslatorInterface::class)->translate('validation.integer')]], $validator->errors()->toArray());
    }

    #[Test]
    public function invalidPriorityFormat(): void
    {
        $data = [
            'type'     => 'SRV',
            'name'     => '_sip._tcp.example.com',
            'content'  => 'bigbox.example.com',
            'priority' => '65536',
            'weight'   => 10,
            'port'     => 5000,
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['priority' => [self::resolve(TranslatorInterface::class)->translate('validation.between.numeric', ['min' => 0, 'max' => 65535])]],
            $validator->errors()->toArray()
        );
    }

    #[Test]
    public function missingWeight(): void
    {
        $data = [
            'type'     => 'SRV',
            'name'     => '_sip._tcp.example.com',
            'content'  => 'bigbox.example.com',
            'priority' => '10',
            'port'     => 5000,
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['weight' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]], $validator->errors()->toArray());
    }

    #[Test]
    public function invalidWeightType(): void
    {
        $data = [
            'type'     => 'SRV',
            'name'     => '_sip._tcp.example.com',
            'content'  => 'bigbox.example.com',
            'priority' => '10',
            'weight'   => 'text',
            'port'     => 5000,
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['weight' => [self::resolve(TranslatorInterface::class)->translate('validation.integer')]], $validator->errors()->toArray());
    }

    #[Test]
    public function invalidWeightFormat(): void
    {
        $data = [
            'type'     => 'SRV',
            'name'     => '_sip._tcp.example.com',
            'content'  => 'bigbox.example.com',
            'priority' => '10',
            'weight'   => '65536',
            'port'     => 5000,
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['weight' => [self::resolve(TranslatorInterface::class)->translate('validation.between.numeric', ['min' => 0, 'max' => 65535])]],
            $validator->errors()->toArray()
        );
    }

    #[Test]
    public function missingPort(): void
    {
        $data = [
            'type'     => 'SRV',
            'name'     => '_sip._tcp.example.com',
            'content'  => 'bigbox.example.com',
            'priority' => '10',
            'weight'   => 10,
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['port' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]], $validator->errors()->toArray());
    }

    #[Test]
    public function invalidPortType(): void
    {
        $data = [
            'type'     => 'SRV',
            'name'     => '_sip._tcp.example.com',
            'content'  => 'bigbox.example.com',
            'priority' => '10',
            'weight'   => 10,
            'port'     => 'text',
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['port' => [self::resolve(TranslatorInterface::class)->translate('validation.integer')]], $validator->errors()->toArray());
    }

    #[Test]
    public function invalidPortFormat(): void
    {
        $data = [
            'type'     => 'SRV',
            'name'     => '_sip._tcp.example.com',
            'content'  => 'bigbox.example.com',
            'priority' => '10',
            'weight'   => 10,
            'port'     => '65536',
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['port' => [self::resolve(TranslatorInterface::class)->translate('validation.between.numeric', ['min' => 0, 'max' => 65535])]],
            $validator->errors()->toArray()
        );
    }
}
