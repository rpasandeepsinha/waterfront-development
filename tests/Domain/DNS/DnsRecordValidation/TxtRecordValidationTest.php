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
class TxtRecordValidationTest extends IntegrationTestCase
{
    use DnsRecordValidatorHelper;

    #[Test]
    public function missingContent(): void
    {
        $data = [
            'type'     => 'TXT',
            'name'     => 'google.com',
            'disabled' => true,
            'ttl'      => '600',
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['content' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]], $validator->errors()->toArray());
    }

    #[Test]
    public function invalidContentType(): void
    {
        $data = [
            'type'     => 'TXT',
            'name'     => 'google.com',
            'content'  => 1,
            'disabled' => true,
            'ttl'      => '600',
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['content' => [self::resolve(TranslatorInterface::class)->translate('validation.string')]], $validator->errors()->toArray());
    }

    #[Test]
    public function emptyContent(): void
    {
        $data = [
            'type'     => 'TXT',
            'name'     => 'google.com',
            'content'  => '',
            'disabled' => true,
            'ttl'      => '600',
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['content' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]], $validator->errors()->toArray());
    }
}
