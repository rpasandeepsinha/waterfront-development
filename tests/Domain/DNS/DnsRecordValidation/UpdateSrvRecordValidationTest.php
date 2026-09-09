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
class UpdateSrvRecordValidationTest extends IntegrationTestCase
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
                'type'     => 'SRV',
                'name'     => '_sip._tcp.example.com',
                'priority' => '10',
                'weight'   => 10,
                'port'     => 5000,
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
                'type'     => 'SRV',
                'name'     => '_sip._tcp.example.com',
                'content'  => 1,
                'priority' => '10',
                'weight'   => 10,
                'port'     => 5000,
                'ttl'      => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(['new.content' => [self::resolve(TranslatorInterface::class)->translate('validation.string')]], $validator->errors()->toArray());
    }

    /**
     * Tests that we get a validation error when the priority is missing.
     */
    #[Test]
    public function missingPriority(): void
    {
        $data = [
            'new' => [
                'type'     => 'SRV',
                'name'     => '_sip._tcp.example.com',
                'content'  => 'bigbox.example.com',
                'weight'   => 10,
                'port'     => 5000,
                'ttl'      => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(['new.priority' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]], $validator->errors()->toArray());
    }

    /**
     * Tests that we get a validation error when the priority is not an integer.
     */
    #[Test]
    public function invalidPriorityType(): void
    {
        $data = [
            'new' => [
                'type'     => 'SRV',
                'name'     => '_sip._tcp.example.com',
                'content'  => 'bigbox.example.com',
                'priority' => 'text',
                'weight'   => 10,
                'port'     => 5000,
                'ttl'      => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(['new.priority' => [self::resolve(TranslatorInterface::class)->translate('validation.integer')]], $validator->errors()->toArray());
    }

    /**
     * Tests that we get a validation error when the priority is not within the required range.
     */
    #[Test]
    public function invalidPriorityFormat(): void
    {
        $data = [
            'new' => [
                'type'     => 'SRV',
                'name'     => '_sip._tcp.example.com',
                'content'  => 'bigbox.example.com',
                'priority' => '65536',
                'weight'   => 10,
                'port'     => 5000,
                'ttl'      => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['new.priority' => [self::resolve(TranslatorInterface::class)->translate('validation.between.numeric', ['min' => 0, 'max' => 65535])]],
            $validator->errors()->toArray()
        );
    }

    /**
     * Tests that we get a validation error when the weight is missing.
     */
    #[Test]
    public function missingWeight(): void
    {
        $data = [
            'new' => [
                'type'     => 'SRV',
                'name'     => '_sip._tcp.example.com',
                'content'  => 'bigbox.example.com',
                'priority' => '10',
                'port'     => 5000,
                'ttl'      => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(['new.weight' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]], $validator->errors()->toArray());
    }

    /**
     * Tests that we get a validation error when the weight is not an integer.
     */
    #[Test]
    public function invalidWeightType(): void
    {
        $data = [
            'new' => [
                'type'     => 'SRV',
                'name'     => '_sip._tcp.example.com',
                'content'  => 'bigbox.example.com',
                'priority' => '10',
                'weight'   => 'text',
                'port'     => 5000,
                'ttl'      => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(['new.weight' => [self::resolve(TranslatorInterface::class)->translate('validation.integer')]], $validator->errors()->toArray());
    }

    /**
     * Tests that we get a validation error when the weight is not within the required range.
     */
    #[Test]
    public function invalidWeightFormat(): void
    {
        $data = [
            'new' => [
                'type'     => 'SRV',
                'name'     => '_sip._tcp.example.com',
                'content'  => 'bigbox.example.com',
                'priority' => '10',
                'weight'   => '65536',
                'port'     => 5000,
                'ttl'      => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['new.weight' => [self::resolve(TranslatorInterface::class)->translate('validation.between.numeric', ['min' => 0, 'max' => 65535])]],
            $validator->errors()->toArray()
        );
    }

    /**
     * Tests that we get a validation error when the port is missing.
     */
    #[Test]
    public function missingPort(): void
    {
        $data = [
            'new' => [
                'type'     => 'SRV',
                'name'     => '_sip._tcp.example.com',
                'content'  => 'bigbox.example.com',
                'priority' => '10',
                'weight'   => 10,
                'ttl'      => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(['new.port' => [self::resolve(TranslatorInterface::class)->translate('validation.required')]], $validator->errors()->toArray());
    }

    /**
     * Tests that we get a validation error when the port is not an integer.
     */
    #[Test]
    public function invalidPortType(): void
    {
        $data = [
            'new' => [
                'type'     => 'SRV',
                'name'     => '_sip._tcp.example.com',
                'content'  => 'bigbox.example.com',
                'priority' => '10',
                'weight'   => 10,
                'port'     => 'text',
                'ttl'      => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(['new.port' => [self::resolve(TranslatorInterface::class)->translate('validation.integer')]], $validator->errors()->toArray());
    }

    /**
     * Tests that we get a validation error when the port is not within the required range.
     */
    #[Test]
    public function invalidPortFormat(): void
    {
        $data = [
            'new' => [
                'type'     => 'SRV',
                'name'     => '_sip._tcp.example.com',
                'content'  => 'bigbox.example.com',
                'priority' => '10',
                'weight'   => 10,
                'port'     => '65536',
                'ttl'      => '600',
                'disabled' => true,
            ],
        ];

        $validator = new UpdateDnsRecordValidator($this->validationService, $this->translator, $data, []);

        self::assertTrue($validator->fails());
        self::assertSame(
            ['new.port' => [self::resolve(TranslatorInterface::class)->translate('validation.between.numeric', ['min' => 0, 'max' => 65535])]],
            $validator->errors()->toArray()
        );
    }
}
