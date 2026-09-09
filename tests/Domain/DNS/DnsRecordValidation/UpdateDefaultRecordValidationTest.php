<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\DnsRecordValidation;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Services\DnsRecordsValidationService;
use Waterfront\Domain\DNS\Validators\UpdateDnsRecordValidator;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(UpdateDnsRecordValidator::class)]
class UpdateDefaultRecordValidationTest extends IntegrationTestCase
{
    private Translator $translator;

    private DnsRecordsValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translator = self::resolve(Translator::class);
        $this->validationService = self::resolve(DnsRecordsValidationService::class);
    }

    /**
     * Tests that the validator throws a ValidationException when the type is missing.
     */
    #[Test]
    public function missingType(): void
    {
        $this->expectException(ValidationException::class);

        $data = [
            'new' => [
                'name'     => 'google.com',
                'content'  => 'unknown data format',
                'ttl'      => '600',
                'disabled' => true,
            ],
        ];

        try {
            new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);
        } catch (ValidationException $exception) {
            self::assertSame(['new.type' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]], $exception->errors());
            throw $exception;
        }
    }

    /**
     * Tests that we get a validation error when the type does not use the required format.
     */
    #[Test]
    public function invalidTypeFormat(): void
    {
        $data = [
            'new' => [
                'type'     => 'unknown',
                'name'     => 'google.com',
                'content'  => 'unknown data format',
                'ttl'      => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(['new.type' => [self::resolve(TranslatorInterface::class)->translate('validation.regex')]], $validator->errors()->toArray());
    }

    /**
     * Tests that we get a validation error when the name is missing.
     */
    #[Test]
    public function missingName(): void
    {
        $data = [
            'new' => [
                'type'     => 'UNKNOWN',
                'content'  => 'unknown data format',
                'ttl'      => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(['new.name' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]], $validator->errors()->toArray());
    }

    /**
     * Tests that we get a validation error when the name is not a string.
     */
    #[Test]
    public function invalidNameType(): void
    {
        $data = [
            'new' => [
                'type'     => 'UNKNOWN',
                'name'     => 1,
                'content'  => 'unknown data format',
                'ttl'      => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(['new.name' => [self::resolve(TranslatorInterface::class)->translate('validation.string')]], $validator->errors()->toArray());
    }

    /**
     * Tests that we get a validation error when the content is missing.
     */
    #[Test]
    public function missingContent(): void
    {
        $data = [
            'new' => [
                'type'     => 'UNKNOWN',
                'name'     => 'google.com',
                'ttl'      => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(['new.content' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]], $validator->errors()->toArray());
    }

    /**
     * Tests that we get a validation error when the content is not a string.
     */
    #[Test]
    public function invalidContentType(): void
    {
        $data = [
            'new' => [
                'type'     => 'UNKNOWN',
                'name'     => 'google.com',
                'content'  => 1,
                'ttl'      => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(['new.content' => [self::resolve(TranslatorInterface::class)->translate('validation.string')]], $validator->errors()->toArray());
    }

    /**
     * Tests that we get a validation error when the ttl is missing.
     */
    #[Test]
    public function missingTtl(): void
    {
        $data = [
            'new' => [
                'type'     => 'UNKNOWN',
                'name'     => 'google.com',
                'content'  => 'unknown data format',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(['new.ttl' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]], $validator->errors()->toArray());
    }

    /**
     * Tests that we get a validation error when the ttl is not an integer.
     */
    #[Test]
    public function invalidTtlType(): void
    {
        $data = [
            'new' => [
                'type'     => 'UNKNOWN',
                'name'     => 'google.com',
                'content'  => 'unknown data format',
                'ttl'      => 'text',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(['new.ttl' => [self::resolve(TranslatorInterface::class)->translate('validation.integer')]], $validator->errors()->toArray());
    }

    /**
     * Tests that we get a validation error when the ttl is not within the required range.
     */
    #[Test]
    public function invalidTtlFormat(): void
    {
        $data = [
            'new' => [
                'type'     => 'UNKNOWN',
                'name'     => 'google.com',
                'content'  => 'unknown data format',
                'ttl'      => '-1',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['new.ttl' => [self::resolve(TranslatorInterface::class)->translate('validation.between.numeric', ['min' => 0, 'max' => 2_147_483_647])]],
            $validator->errors()->toArray()
        );
    }

    /**
     * Tests that a missing disabled status is allowed.
     */
    #[Test]
    public function missingDisabled(): void
    {
        $data = [
            'new' => [
                'type'    => 'UNKNOWN',
                'name'    => 'google.com',
                'content' => 'unknown data format',
                'ttl'     => '600',
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertFalse($validator->fails());
    }

    /**
     * Tests that we get a validation error when the disabled parameter is not a boolean.
     */
    #[Test]
    public function invalidDisabledType(): void
    {
        $data = [
            'new' => [
                'type'     => 'UNKNOWN',
                'name'     => 'google.com',
                'content'  => 'unknown data format',
                'ttl'      => '600',
                'disabled' => 'true',
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(['new.disabled' => [self::resolve(TranslatorInterface::class)->translate('validation.boolean')]], $validator->errors()->toArray());
    }
}
