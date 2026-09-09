<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\DnsRecordValidation;

use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Domain\DNS\Helpers\DnsRecordValidatorHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Services\DnsRecordsValidationService;
use Waterfront\Domain\DNS\Validators\DnsRecordValidator;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(DnsRecordValidator::class)]
#[CoversClass(DnsRecordsValidationService::class)]
class DefaultRecordValidationTest extends IntegrationTestCase
{
    use DnsRecordValidatorHelper;

    #[Test]
    public function missingType(): void
    {
        $this->expectException(ValidationException::class);

        $data = [
            'name'     => 'google.com',
            'content'  => 'unknown data format',
            'ttl'      => '600',
            'disabled' => true,
        ];

        try {
            $this->createDnsRecordValidator($data);
        } catch (ValidationException $exception) {
            self::assertSame(['type' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]], $exception->errors());
            throw $exception;
        }
    }

    #[Test]
    public function invalidTypeFormat(): void
    {
        $data = [
            'type'     => 'unknown',
            'name'     => 'google.com',
            'content'  => 'unknown data format',
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['type' => [self::resolve(TranslatorInterface::class)->translate('validation.regex')]], $validator->errors()->toArray());
    }

    #[Test]
    public function missingName(): void
    {
        $data = [
            'type'     => 'UNKNOWN',
            'content'  => 'unknown data format',
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['name' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]], $validator->errors()->toArray());
    }

    #[Test]
    public function invalidNameType(): void
    {
        $data = [
            'type'     => 'UNKNOWN',
            'name'     => 1,
            'content'  => 'unknown data format',
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['name' => [self::resolve(TranslatorInterface::class)->translate('validation.string')]], $validator->errors()->toArray());
    }

    #[Test]
    public function invalidNameFormatWithSpace(): void
    {
        $data = [
            'type'     => 'UNKNOWN',
            'name'     => 'google .com',
            'content'  => 'unknown data format',
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['name' => [self::resolve(TranslatorInterface::class)->translate('validation.fqdn')]], $validator->errors()->toArray());
    }

    #[Test]
    public function missingContent(): void
    {
        $data = [
            'type'     => 'UNKNOWN',
            'name'     => 'google.com',
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
            'type'     => 'UNKNOWN',
            'name'     => 'google.com',
            'content'  => 1,
            'ttl'      => '600',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['content' => [self::resolve(TranslatorInterface::class)->translate('validation.string')]], $validator->errors()->toArray());
    }

    #[Test]
    public function missingTtl(): void
    {
        $data = [
            'type'     => 'UNKNOWN',
            'name'     => 'google.com',
            'content'  => 'unknown data format',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['ttl' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]], $validator->errors()->toArray());
    }

    #[Test]
    public function invalidTtlType(): void
    {
        $data = [
            'type'     => 'UNKNOWN',
            'name'     => 'google.com',
            'content'  => 'unknown data format',
            'ttl'      => 'text',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['ttl' => [self::resolve(TranslatorInterface::class)->translate('validation.integer')]], $validator->errors()->toArray());
    }

    #[Test]
    public function invalidTtlFormat(): void
    {
        $data = [
            'type'     => 'UNKNOWN',
            'name'     => 'google.com',
            'content'  => 'unknown data format',
            'ttl'      => '-1',
            'disabled' => true,
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['ttl' => [self::resolve(TranslatorInterface::class)->translate('validation.between.numeric', ['min' => 0, 'max' => 2_147_483_647])]],
            $validator->errors()->toArray()
        );
    }

    #[Test]
    public function missingDisabled(): void
    {
        $data = [
            'type'    => 'UNKNOWN',
            'name'    => 'google.com',
            'content' => 'unknown data format',
            'ttl'     => '600',
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertFalse($validator->fails());
    }

    #[Test]
    public function invalidDisabledType(): void
    {
        $data = [
            'type'     => 'UNKNOWN',
            'name'     => 'google.com',
            'content'  => 'unknown data format',
            'ttl'      => '600',
            'disabled' => 'true',
        ];

        $validator = $this->createDnsRecordValidator($data);

        self::assertTrue($validator->fails());
        self::assertSame(['disabled' => [self::resolve(TranslatorInterface::class)->translate('validation.boolean')]], $validator->errors()->toArray());
    }
}
