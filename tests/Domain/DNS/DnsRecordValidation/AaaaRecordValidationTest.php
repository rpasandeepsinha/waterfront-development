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
class AaaaRecordValidationTest extends IntegrationTestCase
{
    use DnsRecordValidatorHelper;

    #[Test]
    public function missingContent(): void
    {
        $data = [
            'type' => 'AAAA',
            'name' => 'google.com',
            'ttl' => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['content' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]],
            $validator->errors()->toArray(),
        );
    }

    #[Test]
    public function invalidContentType(): void
    {
        $data = [
            'type' => 'AAAA',
            'name' => 'google.com',
            'content' => 1,
            'ttl' => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['content' => [self::resolve(TranslatorInterface::class)->translate('validation.string')]],
            $validator->errors()->toArray(),
        );
    }

    #[Test]
    public function invalidContentFormat(): void
    {
        $data = [
            'type' => 'AAAA',
            'name' => 'google.com',
            'content' => '2001:1460:2:0:1c21:1fff:fe00',
            'ttl' => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['content' => [self::resolve(TranslatorInterface::class)->translate('validation.ipv6')]],
            $validator->errors()->toArray(),
        );
    }
}
