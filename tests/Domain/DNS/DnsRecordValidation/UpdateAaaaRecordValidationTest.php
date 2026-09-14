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
class UpdateAaaaRecordValidationTest extends IntegrationTestCase
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
                'type' => 'AAAA',
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
                'type' => 'AAAA',
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
     * Tests that we get a validation error when the content is not a valid ipv6 address.
     */
    #[Test]
    public function invalidContentFormat(): void
    {
        $data = [
            'new' => [
                'type' => 'AAAA',
                'name' => 'google.com',
                'content' => '2001:1460:2:0:1c21:1fff:fe00',
                'ttl' => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['new.content' => [self::resolve(TranslatorInterface::class)->translate('validation.ipv6')]],
            $validator->errors()->toArray(),
        );
    }
}
