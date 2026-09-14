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
class MxRecordValidationTest extends IntegrationTestCase
{
    use DnsRecordValidatorHelper;

    #[Test]
    public function missingContent(): void
    {
        $data = [
            'type' => 'MX',
            'name' => 'google.com',
            'priority' => '10',
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
            'type' => 'MX',
            'name' => 'google.com',
            'content' => 1,
            'priority' => '10',
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
    public function invalidContentFormatWithSpace(): void
    {
        $data = [
            'type' => 'MX',
            'name' => 'google.com',
            'content' => 'mxspamservice .nl',
            'priority' => '10',
            'ttl' => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['content' => [self::resolve(TranslatorInterface::class)->translate('validation.fqdn')]],
            $validator->errors()->toArray(),
        );
    }

    #[Test]
    public function missingPriority(): void
    {
        $data = [
            'type' => 'MX',
            'name' => 'google.com',
            'content' => 'mx.spamservice.nl',
            'ttl' => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['priority' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]],
            $validator->errors()->toArray(),
        );
    }

    #[Test]
    public function invalidPriorityType(): void
    {
        $data = [
            'type' => 'MX',
            'name' => 'google.com',
            'content' => 'mx.spamservice.nl',
            'priority' => 'text',
            'ttl' => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['priority' => [self::resolve(TranslatorInterface::class)->translate('validation.integer')]],
            $validator->errors()->toArray(),
        );
    }

    #[Test]
    public function invalidPriorityFormat(): void
    {
        $data = [
            'type' => 'MX',
            'name' => 'google.com',
            'content' => 'mx.spamservice.nl',
            'priority' => '65536',
            'ttl' => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['priority' => [self::resolve(TranslatorInterface::class)
                ->translate('validation.between.numeric', ['min' => 0, 'max' => 65535])]],
            $validator->errors()->toArray(),
        );
    }
}
