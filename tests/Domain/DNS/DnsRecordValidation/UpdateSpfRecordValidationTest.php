<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\DnsRecordValidation;

use Illuminate\Contracts\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Services\DnsRecordsValidationService;
use Waterfront\Domain\DNS\Validators\UpdateDnsRecordValidator;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(UpdateDnsRecordValidator::class)]
class UpdateSpfRecordValidationTest extends IntegrationTestCase
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
     * Tests that we get a validation error when the content is missing.
     */
    #[Test]
    public function missingContent(): void
    {
        $data = [
            'new' => [
                'type' => 'SPF',
                'name' => 'google.com',
                'ttl' => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['new.content' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]],
            $validator->errors()->toArray(),
        );
    }

    /**
     * Tests that we get a validation error when the content is not a string.
     */
    #[Test]
    public function invalidContentType(): void
    {
        $data = [
            'new' => [
                'type' => 'SPF',
                'name' => 'google.com',
                'content' => 1,
                'ttl' => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['new.content' => [self::resolve(TranslatorInterface::class)->translate('validation.string')]],
            $validator->errors()->toArray(),
        );
    }

    /**
     * Tests that we get a validation error when the content is empty.
     */
    #[Test]
    public function emptyContent(): void
    {
        $data = [
            'new' => [
                'type' => 'SPF',
                'name' => 'google.com',
                'content' => '',
                'ttl' => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['new.content' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]],
            $validator->errors()->toArray(),
        );
    }
}
