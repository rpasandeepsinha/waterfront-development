<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Pipes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Pipes\CustomerPipe;

#[CoversClass(CustomerPipe::class)]
class CustomerPipeTest extends IntegrationTestCase
{
    private const string REFERENCE = 'unique_reference_for_adf';

    #[Test]
    public function validateCustomerBadData(): void
    {
        $customer = include(__DIR__ . '/data/customer_bad_data.php');
        $subscriptions = include(__DIR__ . '/data/subscriptions_correct.php');

        $customer['dnsTemplates'] = [
            [
                'name' => 'testname',
                'reference_template_id' => '1234',
                'records' => [
                    [
                        'reference_record_id' => '999',
                        'name' => 'subdomain.@',
                        'type' => 'super_non_existing_type',
                        'content' => null,
                        'priority' => '',
                        'ttl' => 'test',
                    ],
                    [
                        'reference_record_id' => '999',
                        'name' => 'subdomain.@',
                        'type' => DnsRecordType::SRV->value,
                        'content' => null,
                        'priority' => '',
                        'weight' => 'test',
                        'ttl' => 'test',
                    ],
                ],
            ],
        ];

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $customerPipe = self::resolve(CustomerPipe::class);
        $validationPayload = $customerPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame(self::REFERENCE, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::CUSTOMER->value => [
                    [
                        'id' => MigrationValidation::DEFAULT_VALIDATION->value,
                        'message' => [
                            'email' => [
                                'Dit veld dient een geldig emailadres te zijn.',
                            ],
                            'phone' => [
                                'Het ingevoerde telefoonnummer is ongeldig.',
                            ],
                            'dnsTemplates.0.records.0.content' => [
                                'Dit veld is verplicht.',
                            ],
                            'dnsTemplates.0.records.0.ttl' => [
                                'Dit veld dient een geheel getal te zijn.',
                            ],
                            'dnsTemplates.0.records.1.content' => [
                                'Dit veld is verplicht.',
                            ],
                            'dnsTemplates.0.records.1.priority' => [
                                'Dit veld is verplicht.',
                            ],
                            'dnsTemplates.0.records.1.weight' => [
                                'Dit veld dient een geheel getal te zijn.',
                            ],
                            'dnsTemplates.0.records.1.port' => [
                                'Dit veld is verplicht.',
                            ],
                            'dnsTemplates.0.records.1.ttl' => [
                                'Dit veld dient een geheel getal te zijn.',
                            ],
                            'product_group_discounts.0.product_group_type' => [
                                'Geselecteerde product_group_discounts.0.product_group_type is ongeldig.',
                            ],
                            'product_group_discounts.0.discount_percentage' => [
                                'Dit veld dient minimaal 1 te zijn.',
                            ],
                            'dnsTemplates.0.records.0.type' => [
                                'Geselecteerde dnsTemplates.0.records.0.type is ongeldig.',
                            ],
                        ],
                    ],
                    [
                        'id' => MigrationValidation::CUSTOMER_PIPE_PASSED->value,
                        'message' => 'customer reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }

    #[Test]
    public function validateCustomerAlreadyMigrated(): void
    {
        $customerData = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/subscriptions_correct.php');

        $migratedCustomer = MigratedCustomersFactory::new()->createOne([
            'reference_customer_number' => 'identifier',
            'reference_name' => 'testmigration',
            'group_type' => 'testgroup',
            'administrative_successful' => true,
        ]);

        $customerCreated = CustomerFactory::new()->createOne(['email' => 'example@example.com']);
        $customerCreated->migratedCustomers()->attach($migratedCustomer);

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customerData,
            subscriptions: $subscriptions
        );

        $customerPipe = self::resolve(CustomerPipe::class);
        $validationPayload = $customerPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame(self::REFERENCE, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::CUSTOMER->value => [
                    [
                        'id' => MigrationValidation::CUSTOMER_PIPE_PASSED->value,
                        'message' => 'customer reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }
}
