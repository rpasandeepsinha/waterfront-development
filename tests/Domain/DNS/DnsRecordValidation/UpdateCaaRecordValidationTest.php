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
class UpdateCaaRecordValidationTest extends IntegrationTestCase
{
    private Translator $translator;

    private DnsRecordsValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translator = self::resolve(Translator::class);
        $this->validationService = self::resolve(DnsRecordsValidationService::class);
    }

    #[Test]
    public function missingContent(): void
    {
        $data = [
            'new' => [
                'type' => 'CAA',
                'name' => 'google.com',
                'disabled' => true,
                'ttl' => '600',
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['new.content' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]],
            $validator->errors()->toArray(),
        );
    }

    #[Test]
    public function invalidContentFormat(): void
    {
        $data = [
            'new' => [
                'type' => 'CAA',
                'name' => 'google.com',
                'content' => 'testerdetest',
                'disabled' => true,
                'ttl' => '600',
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['new.content' => [self::resolve(TranslatorInterface::class)->translate('validation.caa_content')]],
            $validator->errors()->toArray(),
        );
    }

    #[Test]
    public function validContentFormat(): void
    {
        $data = [
            'new' => [
                'type' => 'CAA',
                'name' => 'google.com',
                'content' => '0 issue "certauth.org"',
                'disabled' => true,
                'ttl' => '600',
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->passes());
    }
}
