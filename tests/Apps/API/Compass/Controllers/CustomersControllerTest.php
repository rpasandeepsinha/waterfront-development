<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use ReflectionProperty;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Tests\Factories\AuditFactory;
use Tests\Factories\CustomerContactFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\CustomerProductDiscountFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\ProductDiscountFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\CustomersController;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Jobs\AnonymizeCustomerJob;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Customers\Models\CustomerContact;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Invoices\Jobs\DispatchConsolidatedInvoicesForCustomer;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Lighthouse\Exceptions\ResourceNotFoundException;
use Waterfront\Domain\Lighthouse\Services\LighthouseApiService;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

#[CoversClass(CustomersController::class)]
class CustomersControllerTest extends IntegrationTestCase
{
    private Customer $normalCustomer;

    private Subscription $subscription;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->normalCustomer = new CustomerFactory()->withAddress()->createOne();

        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
            'name' => ProductGroupType::HOSTING,
        ]);
        $this->product = new ProductFactory()->for($productGroup)->createOne();

        $this->subscription = new SubscriptionFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->createOne();

        new AuditFactory()->createMany([
            [
                'event' => 'created',
                'auditable_type' => Subscription::class,
                'auditable_id' => $this->subscription->id,
                'old_values' => '[]',
                'new_values' => ['technical_status' => TechnicalStatus::PENDING->value],
                'identity_uuid' => $this->normalCustomer->uuid,
                'identity_metadata' => json_encode([
                    'email' => $this->normalCustomer->email,
                    'schemaId' => SchemaId::CUSTOMER->value,
                ], JSON_THROW_ON_ERROR),
            ],
            [
                'event' => 'updated',
                'auditable_type' => Subscription::class,
                'auditable_id' => $this->subscription->id,
                'old_values' => ['technical_status' => TechnicalStatus::PENDING->value],
                'new_values' => ['technical_status' => TechnicalStatus::OK->value],
                'identity_uuid' => $this->normalCustomer->uuid,
                'identity_metadata' => json_encode([
                    'email' => $this->normalCustomer->email,
                    'schemaId' => SchemaId::CUSTOMER->value,
                ], JSON_THROW_ON_ERROR),
            ],
        ]);
    }

    #[Test]
    public function showCustomerOnExistingCustomer(): void
    {
        $migratedCustomer = MigratedCustomersFactory::new()->createOne([
            'reference_name' => 'versio',
            'administrative_successful' => true,
            'technical_successful' => true,
            'billing_successful' => false,
            'dns_successful' => true,
            'enable_invoicing' => true,
            'migrated_at' => CarbonImmutable::now(),
        ]);

        $this->normalCustomer->migratedCustomers()->attach($migratedCustomer);

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.customers.show', [
            'customer' => $this->normalCustomer->customer_number,
        ]));

        $response->assertOk();
        Assert::isInstanceOf($this->normalCustomer->address, CustomerAddress::class);
        $response->assertExactJson([
            'customer_number' => $this->normalCustomer->customer_number,
            'uuid' => $this->normalCustomer->uuid,
            'id' => $this->normalCustomer->id,
            'is_abuse' => $this->normalCustomer->is_abuse,
            'first_name' => $this->normalCustomer->first_name,
            'full_name' => $this->normalCustomer->contact_name,
            'last_name' => $this->normalCustomer->last_name,
            'email' => $this->normalCustomer->email,
            'phone' => $this->normalCustomer->phone_number,
            'organization' => $this->normalCustomer->organization,
            'locale' => $this->normalCustomer->locale,
            'payment_type' => $this->normalCustomer->payment_type->value,
            'has_direct_debit' => false,
            'department' => $this->normalCustomer->department,
            'payment_term' => $this->normalCustomer->terms_of_payment,
            'vat_number' => $this->normalCustomer->vat_number,
            'vat_rate' => $this->normalCustomer->vat_rate,
            'credit_limit' => $this->normalCustomer->credit_limit,
            'address' => [
                'city' => $this->normalCustomer->address->city,
                'country_code' => $this->normalCustomer->address->country_code,
                'customer_id' => $this->normalCustomer->id,
                'street_name' => $this->normalCustomer->address->street_name,
                'street_number' => $this->normalCustomer->address->street_number,
                'street_number_addition' => $this->normalCustomer->address->street_number_addition,
                'zip_code' => $this->normalCustomer->address->zip_code,
                'type' => null,
                'created_at' => $this->normalCustomer->address->created_at,
                'updated_at' => $this->normalCustomer->address->updated_at,
                'id' => $this->normalCustomer->address->id,
            ],
            'gender' => $this->normalCustomer->gender,
            'migrated_customers' => $this->normalCustomer
                ->migratedCustomers
                ->map(static fn (MigratedCustomer $migratedCustomer): array => [
                    'id' => $migratedCustomer->id,
                    'reference_customer_number' => $migratedCustomer->reference_customer_number,
                    'reference_name' => $migratedCustomer->reference_name,
                    'group_type' => $migratedCustomer->group_type,
                    'successful' => $migratedCustomer->successful,
                    'administrative_successful' => $migratedCustomer->administrative_successful,
                    'technical_successful' => $migratedCustomer->technical_successful,
                    'billing_successful' => $migratedCustomer->billing_successful,
                    'dns_successful' => $migratedCustomer->dns_successful,
                    'enable_invoicing' => $migratedCustomer->enable_invoicing,
                    'migrated_at' => $migratedCustomer->migrated_at?->toJSON(),
                ])
                ->toArray(),
            'available_actions' => [
                'sendInvoicesFromOldBu',
                'canCancelAndCreditSubscriptions',
            ],
            'labels' => [
                'experiments' => [],
            ],
            'coc_number' => null,
        ]);
    }

    #[Test]
    public function testListCustomers(): void
    {
        new CustomerFactory()->createOne();
        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.customers.list'))->assertOk();

        /** @var array<array<string>> $responseArray */
        $responseArray = $response->json();

        self::assertArrayHasKey('data', $responseArray);
        self::assertCount(2, $responseArray['data']);
    }

    #[Test]
    public function showCustomerInvalidIdFail(): void
    {
        $invalidCustomerId = '696969';

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.customers.show', [
            'customer' => $invalidCustomerId,
        ]));

        $response->assertNotFound();
    }

    /**
     * The contents of our audit logs do not really matter to us. What matters is that we return only logs from
     * the desired customer, subscriptions & users. So that's why you'll only see assertions on those ID's.
     */
    #[Test]
    public function indexLogsForCustomer(): void
    {
        $response = $this->actingAsEmployee(Uuid::uuid4())
            ->getJson(
                $this->generateRoute('admin.customers.audit-logs', [
                    'customer' => $this->normalCustomer->customer_number,
                ]),
            )
            ->assertOk();

        /** @var array<array<array<array<string|int>>|int>> $responseArray */
        $responseArray = $response->json();

        self::assertSame(2, $responseArray['meta']['total']);
        self::assertSame(100, $responseArray['meta']['per_page']);

        /** @var string $firstLink */
        $firstLink = $responseArray['links']['first'];
        self::assertStringContainsString('pageSize=100', $firstLink);

        foreach ($responseArray['data'] as $item) {
            assert(is_array($item));
            if ($item['actor']['type'] === 'System') {
                continue;
            }

            switch ($item['subject']['type']) {
                case Customer::class:
                case CustomerAddress::class:
                case CustomerContact::class:
                    self::assertSame($this->normalCustomer->contact_name, $item['subject']['name']);
                    break;
                case Subscription::class:
                    self::assertSame($this->subscription->domain, $item['subject']['name']);
                    self::assertSame($this->normalCustomer->name, $item['actor']['name']);
                    self::assertSame('Customer', $item['actor']['type']);
                    break;
            }
        }
    }

    public function indexLogsForCustomerCustomPageSize(): void
    {
        $response = $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.customers.index.audit-logs', [
                    'customer' => $this->normalCustomer->customer_number,
                    'pageSize' => 5,
                ]),
            )
            ->assertOk();

        /** @var array<array<array<array<string|int>>|int>> $responseArray */
        $responseArray = $response->json();

        self::assertSame(2, $responseArray['meta']['total']);
        self::assertSame(5, $responseArray['meta']['per_page']);

        /** @var string $firstLink */
        $firstLink = $responseArray['links']['first'];
        self::assertStringContainsString('pageSize=5', $firstLink);
    }

    #[test]
    public function storeCustomer(): void
    {
        $email = 'notify@sandwave.io';

        $lighthouseApiServiceMock = self::createMock(LighthouseApiService::class);
        $lighthouseApiServiceMock
            ->expects(self::once())
            ->method('getKratosIdentityByIdentifier')
            ->with($email)
            ->willThrowException(new ResourceNotFoundException());

        $lighthouseApiServiceMock
            ->expects(self::once())
            ->method('createKratosIdentity')
            ->with($email, self::anything());
        $this->app->bind(LighthouseApiService::class, fn () => $lighthouseApiServiceMock);

        $payload = [
            'email' => $email,
            'first_name' => 'Lee',
            'last_name' => 'Towers',
            'gender' => Gender::MALE->value,
            'phone_number' => '+31612345678',
            'street_name' => 'street',
            'street_number' => '1908',
            'street_number_addition' => 'B',
            'zip_code' => '1234AA',
            'city' => 'Rotterdam',
            'country_code' => 'NL',
            'terms' => 'true',
        ];

        $response = $this->actingAsEmployee()->post($this->generateRoute('admin.customers.store', $payload));

        $response->assertSuccessful();
    }

    #[Test]
    public function anonymizeCustomer(): void
    {
        $response = $this->actingAsEmployee()->post($this->generateRoute(
            'admin.customers.anonymize',
            $this->normalCustomer->customer_number,
        ));
        Queue::assertPushed(AnonymizeCustomerJob::class);

        $response->assertSuccessful();
    }

    #[Test]
    public function requestBuInvoicesWithWrongBu(): void
    {
        $customer = new CustomerFactory()->createOne(['email' => 'migrationCustomer@sandwave.io']);
        $migratedCustomer = new MigratedCustomersFactory()->createOne(['reference_name' => 'QDC']);
        $migratedCustomer->customers()->save($customer);

        $guzzleMock = self::createMock(Client::class);
        $guzzleMock->expects(self::never())->method('post');

        $this->app->bind(Client::class, fn () => $guzzleMock);

        $response = $this->actingAsEmployee()->post(
            $this->generateRoute('admin.customers.requestBuInvoices', $customer->customer_number),
            [
                'referenceCustomerNumber' => $migratedCustomer->reference_customer_number,
                'fromDate' => CarbonImmutable::now()->subWeek()->format('Y-m-d'),
                'toDate' => CarbonImmutable::now()->format('Y-m-d'),
            ],
        );

        self::assertStringContainsString('bad value received: QDC', (string) $response->getContent());
    }

    #[Test]
    public function enableInvoicingEnablesInvoicingForTheMigratedCustomer(): void
    {
        $customer = new CustomerFactory()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne([
            'administrative_successful' => false,
            'enable_invoicing' => false,
            'successful' => false,
        ]);
        $migratedCustomer->customers()->attach($customer->id);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.customers.enable.invoicing', [
                'customer' => $customer->customer_number,
            ]))
            ->assertOk()
            ->assertJsonPath('message', 'sidebar.action.enable-invoicing.success');

        $migratedCustomer->refresh();

        self::assertTrue($migratedCustomer->administrative_successful);
        self::assertTrue($migratedCustomer->enable_invoicing);
        self::assertTrue($migratedCustomer->successful);
    }

    #[Test]
    public function enableInvoicingReportsWhenInvoicingIsAlreadyEnabled(): void
    {
        $customer = new CustomerFactory()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['enable_invoicing' => true]);
        $migratedCustomer->customers()->attach($customer->id);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.customers.enable.invoicing', [
                'customer' => $customer->customer_number,
            ]))
            ->assertOk()
            ->assertJsonPath('message', 'sidebar.action.enable-invoicing.already-enabled');
    }

    #[Test]
    public function enableInvoicingIsUnprocessableForANonMigratedCustomer(): void
    {
        $customer = new CustomerFactory()->createOne();

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.customers.enable.invoicing', [
                'customer' => $customer->customer_number,
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'sidebar.action.enable-invoicing.not-migrated');
    }

    #[Test]
    public function markCustomerAsAbusiveConfirmationRequired(): void
    {
        $customer = new CustomerFactory()->createOne(['email' => 'migrationCustomer@sandwave.io']);

        $this->actingAsEmployee()
            ->post($this->generateRoute('admin.customers.mark.as.abuse', $customer->customer_number), [
                'confirmation' => false,
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function listOrdersReturnsAllOrdersForCustomer(): void
    {
        new OrderFactory()->for($this->normalCustomer)->createOne(['status' => OrderStatus::IN_PROGRESS]);
        new OrderFactory()->for($this->normalCustomer)->createOne(['status' => OrderStatus::PROCESSED]);

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.customers.orders.list', [
            'customer' => $this->normalCustomer->customer_number,
        ]));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function listUnprocessedInvoiceLinesReturnsOnlyLinesNotSentToHarbor(): void
    {
        new InvoiceFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null]);
        new InvoiceFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null]);
        $sentLine = new InvoiceFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->sentToHarbor()
            ->createOne();

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.customers.invoice-lines.unprocessed', [
            'customer' => $this->normalCustomer->customer_number,
        ]));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonMissing(['id' => $sentLine->id]);
    }

    #[Test]
    public function listUnprocessedInvoiceLinesOnlyReturnsLinesForTheGivenCustomer(): void
    {
        $otherCustomer = new CustomerFactory()->createOne();
        new InvoiceFactory()
            ->for($otherCustomer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null]);

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.customers.invoice-lines.unprocessed', [
            'customer' => $this->normalCustomer->customer_number,
        ]));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function listUnprocessedInvoiceLinesFailsForUnknownCustomer(): void
    {
        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.customers.invoice-lines.unprocessed', [
            'customer' => '696969',
        ]));

        $response->assertNotFound();
    }

    #[Test]
    public function listUnprocessedInvoiceLinesPaginatesUsingTheGivenPageSize(): void
    {
        new InvoiceFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->count(3)
            ->create(['sent_to_harbor_at' => null]);

        $route = $this->generateRoute('admin.customers.invoice-lines.unprocessed', [
            'customer' => $this->normalCustomer->customer_number,
        ]);

        $response = $this->actingAsEmployee()->getJson($route . '?pageSize=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.per_page', 2);
        $response->assertJsonPath('meta.total', 3);
        $response->assertJsonPath('meta.last_page', 2);
        $response->assertJsonPath('meta.totalInvoiceLines', 3);

        $secondPage = $this->actingAsEmployee()->getJson($route . '?pageSize=2&page=2');

        $secondPage->assertOk();
        $secondPage->assertJsonCount(1, 'data');
    }

    #[Test]
    public function listUnprocessedInvoiceLinesDefaultsToOneHundredPerPage(): void
    {
        new InvoiceFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null]);

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.customers.invoice-lines.unprocessed', [
            'customer' => $this->normalCustomer->customer_number,
        ]));

        $response->assertOk();
        $response->assertJsonPath('meta.per_page', 100);
    }

    #[Test]
    public function listUnprocessedInvoiceLinesSearchesTitleCaseInsensitively(): void
    {
        $matching = new InvoiceFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null, 'title' => 'Webhosting Large', 'description' => 'Verlenging']);
        new InvoiceFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null, 'title' => 'Domeinnaam', 'description' => 'Registratie']);

        $response = $this->actingAsEmployee()->getJson(
            $this->generateRoute('admin.customers.invoice-lines.unprocessed', [
                'customer' => $this->normalCustomer->customer_number,
            ])
                . '?search=webHOSTING',
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matching->id);
        $response->assertJsonPath('meta.total', 1);
    }

    #[Test]
    public function listUnprocessedInvoiceLinesSearchesDescription(): void
    {
        $matching = new InvoiceFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null, 'title' => 'Domeinnaam', 'description' => 'Verlenging 2027']);
        new InvoiceFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null, 'title' => 'Webhosting', 'description' => 'Registratie']);

        $response = $this->actingAsEmployee()->getJson(
            $this->generateRoute('admin.customers.invoice-lines.unprocessed', [
                'customer' => $this->normalCustomer->customer_number,
            ])
                . '?search=verlenging',
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matching->id);
    }

    #[Test]
    public function listUnprocessedInvoiceLinesSearchNeverLeaksAnotherCustomersLines(): void
    {
        $otherCustomer = new CustomerFactory()->createOne();
        new InvoiceFactory()
            ->for($otherCustomer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null, 'title' => 'Webhosting Large']);
        new InvoiceFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null, 'title' => 'Domeinnaam']);

        $response = $this->actingAsEmployee()->getJson(
            $this->generateRoute('admin.customers.invoice-lines.unprocessed', [
                'customer' => $this->normalCustomer->customer_number,
            ])
                . '?search=webhosting',
        );

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function listUnprocessedInvoiceLinesSearchNeverLeaksLinesAlreadySentToHarbor(): void
    {
        new InvoiceFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->sentToHarbor()
            ->createOne(['title' => 'Webhosting Large']);

        $response = $this->actingAsEmployee()->getJson(
            $this->generateRoute('admin.customers.invoice-lines.unprocessed', [
                'customer' => $this->normalCustomer->customer_number,
            ])
                . '?search=webhosting',
        );

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function listUnprocessedInvoiceLinesIgnoresBlankAndOverlongSearchTerms(): void
    {
        new InvoiceFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->count(2)
            ->create(['sent_to_harbor_at' => null]);

        $route = $this->generateRoute('admin.customers.invoice-lines.unprocessed', [
            'customer' => $this->normalCustomer->customer_number,
        ]);

        $this->actingAsEmployee()
            ->getJson($route . '?search=')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->actingAsEmployee()
            ->getJson($route . '?search=%20%20')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->actingAsEmployee()
            ->getJson($route . '?search=' . str_repeat('a', 51))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function propagateInvoiceLinesToHarborDispatchesJobForTheSelectedLines(): void
    {
        $customer = new CustomerFactory()->createOne();
        $firstLine = new InvoiceFactory()
            ->for($customer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null]);
        $secondLine = new InvoiceFactory()
            ->for($customer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null]);

        $response = $this->actingAsEmployee()->postJson(
            $this->generateRoute('admin.customers.invoice-lines.propagate', ['customer' => $customer->customer_number]),
            ['invoiceLineIds' => [$firstLine->id, $secondLine->id]],
        );

        $response->assertOk();
        $response->assertJsonPath(
            'message',
            self::resolve(TranslatorInterface::class)
                ->translate('sidebar.action.send-invoice-lines-to-harbor.propagated-successfully'),
        );

        Queue::assertPushed(
            DispatchConsolidatedInvoicesForCustomer::class,
            fn (DispatchConsolidatedInvoicesForCustomer $job): bool => (
                self::getInvoiceIdsFromJob($job) === [$firstLine->id, $secondLine->id]
            ),
        );
    }

    #[Test]
    public function propagateInvoiceLinesToHarborDefaultsCreateInvoiceInstantlyToFalse(): void
    {
        $customer = new CustomerFactory()->createOne();
        $invoiceLine = new InvoiceFactory()
            ->for($customer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null]);

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.customers.invoice-lines.propagate', [
                    'customer' => $customer->customer_number,
                ]),
                ['invoiceLineIds' => [$invoiceLine->id]],
            )
            ->assertOk();

        Queue::assertPushed(
            DispatchConsolidatedInvoicesForCustomer::class,
            fn (DispatchConsolidatedInvoicesForCustomer $job): bool => (
                self::getCreateInvoiceInstantlyFromJob($job) === false
            ),
        );
    }

    #[Test]
    public function propagateInvoiceLinesToHarborPassesCreateInvoiceInstantlyWhenRequested(): void
    {
        $customer = new CustomerFactory()->createOne();
        $invoiceLine = new InvoiceFactory()
            ->for($customer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null]);

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.customers.invoice-lines.propagate', [
                    'customer' => $customer->customer_number,
                ]),
                ['invoiceLineIds' => [$invoiceLine->id], 'createInvoiceInstantly' => true],
            )
            ->assertOk();

        Queue::assertPushed(
            DispatchConsolidatedInvoicesForCustomer::class,
            fn (DispatchConsolidatedInvoicesForCustomer $job): bool => self::getCreateInvoiceInstantlyFromJob($job),
        );
    }

    #[Test]
    public function propagateInvoiceLinesToHarborDispatchesJobForTheSelectedLinesForMigratedCustomer(): void
    {
        $customer = new CustomerFactory()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne([
            'reference_name' => 'versio',
            'enable_invoicing' => true,
            'migrated_at' => CarbonImmutable::now(),
        ]);
        $customer->migratedCustomers()->attach($migratedCustomer);

        $firstLine = new InvoiceFactory()
            ->for($customer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null]);
        $secondLine = new InvoiceFactory()
            ->for($customer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null]);

        $response = $this->actingAsEmployee()->postJson(
            $this->generateRoute('admin.customers.invoice-lines.propagate', ['customer' => $customer->customer_number]),
            ['invoiceLineIds' => [$firstLine->id, $secondLine->id]],
        );

        $response->assertOk();
        $response->assertJsonPath(
            'message',
            self::resolve(TranslatorInterface::class)
                ->translate('sidebar.action.send-invoice-lines-to-harbor.propagated-successfully'),
        );

        Queue::assertPushed(
            DispatchConsolidatedInvoicesForCustomer::class,
            fn (DispatchConsolidatedInvoicesForCustomer $job): bool => (
                self::getInvoiceIdsFromJob($job) === [$firstLine->id, $secondLine->id]
            ),
        );
    }

    /**
     * InvoiceToHarborDispatcher skips customers that are in an active migration with invoicing
     * disabled. The request still succeeds, exactly as the Nova action does. $this->normalCustomer
     * has such a migration attached in setUp.
     */
    #[Test]
    public function propagateInvoiceLinesToHarborSkipsCustomerInMigrationWithInvoicingDisabled(): void
    {
        $migratedCustomer = MigratedCustomersFactory::new()->createOne([
            'reference_name' => 'versio',
            'enable_invoicing' => false,
            'migrated_at' => CarbonImmutable::now(),
        ]);

        $this->normalCustomer->migratedCustomers()->attach($migratedCustomer);

        $invoiceLine = new InvoiceFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null]);

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.customers.invoice-lines.propagate', [
                    'customer' => $this->normalCustomer->customer_number,
                ]),
                ['invoiceLineIds' => [$invoiceLine->id]],
            )
            ->assertOk();

        Queue::assertNotPushed(DispatchConsolidatedInvoicesForCustomer::class);
    }

    #[Test]
    public function propagateInvoiceLinesToHarborRejectsTheWholeSelectionWhenALineWasAlreadySent(): void
    {
        $unprocessedLine = new InvoiceFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null]);
        $alreadySentLine = new InvoiceFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->sentToHarbor()
            ->createOne();

        $response = $this->actingAsEmployee()->postJson(
            $this->generateRoute('admin.customers.invoice-lines.propagate', [
                'customer' => $this->normalCustomer->customer_number,
            ]),
            ['invoiceLineIds' => [$unprocessedLine->id, $alreadySentLine->id]],
        );

        $response->assertUnprocessable();
        $response->assertJsonPath(
            'message',
            self::resolve(TranslatorInterface::class)
                ->translate('sidebar.action.send-invoice-lines-to-harbor.invoice-lines-already-sent'),
        );

        Queue::assertNotPushed(DispatchConsolidatedInvoicesForCustomer::class);
    }

    #[Test]
    public function propagateInvoiceLinesToHarborRejectsInvoiceLineOfAnotherCustomer(): void
    {
        $ownLine = new InvoiceFactory()
            ->for($this->normalCustomer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null]);
        $otherCustomer = new CustomerFactory()->createOne();
        $otherCustomersLine = new InvoiceFactory()
            ->for($otherCustomer)
            ->for($this->product)
            ->createOne(['sent_to_harbor_at' => null]);

        $response = $this->actingAsEmployee()->postJson(
            $this->generateRoute('admin.customers.invoice-lines.propagate', [
                'customer' => $this->normalCustomer->customer_number,
            ]),
            ['invoiceLineIds' => [$ownLine->id, $otherCustomersLine->id]],
        );

        $response->assertUnprocessable();
        $response->assertJsonPath(
            'message',
            self::resolve(TranslatorInterface::class)
                ->translate('sidebar.action.send-invoice-lines-to-harbor.invoice-lines-not-for-customer'),
        );

        Queue::assertNotPushed(DispatchConsolidatedInvoicesForCustomer::class);
    }

    #[Test]
    public function propagateInvoiceLinesToHarborRejectsUnknownInvoiceLineId(): void
    {
        $response = $this->actingAsEmployee()->postJson(
            $this->generateRoute('admin.customers.invoice-lines.propagate', [
                'customer' => $this->normalCustomer->customer_number,
            ]),
            ['invoiceLineIds' => [696969]],
        );

        $response->assertUnprocessable();
        $response->assertJsonPath(
            'message',
            self::resolve(TranslatorInterface::class)
                ->translate('sidebar.action.send-invoice-lines-to-harbor.invoice-lines-not-for-customer'),
        );

        Queue::assertNotPushed(DispatchConsolidatedInvoicesForCustomer::class);
    }

    #[Test]
    public function propagateInvoiceLinesToHarborRequiresAtLeastOneInvoiceLineId(): void
    {
        $response = $this->actingAsEmployee()->postJson(
            $this->generateRoute('admin.customers.invoice-lines.propagate', [
                'customer' => $this->normalCustomer->customer_number,
            ]),
            ['invoiceLineIds' => []],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('invoiceLineIds');

        Queue::assertNotPushed(DispatchConsolidatedInvoicesForCustomer::class);
    }

    #[Test]
    public function propagateInvoiceLinesToHarborFailsForUnknownCustomer(): void
    {
        $response = $this->actingAsEmployee()->postJson(
            $this->generateRoute('admin.customers.invoice-lines.propagate', ['customer' => '696969']),
            ['invoiceLineIds' => [1]],
        );

        $response->assertNotFound();
    }

    #[Test]
    public function listOrdersFiltersOnStatus(): void
    {
        new OrderFactory()->for($this->normalCustomer)->createOne(['status' => OrderStatus::IN_PROGRESS]);
        new OrderFactory()->for($this->normalCustomer)->createOne(['status' => OrderStatus::PROCESSED]);

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.customers.orders.list', [
            'customer' => $this->normalCustomer->customer_number,
        ])
        . '?status[]='
        . OrderStatus::PROCESSED->value);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['status' => OrderStatus::PROCESSED]);
    }

    #[Test]
    public function listOrdersIgnoresInvalidStatus(): void
    {
        new OrderFactory()->for($this->normalCustomer)->createOne(['status' => OrderStatus::IN_PROGRESS]);

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.customers.orders.list', [
            'customer' => $this->normalCustomer->customer_number,
        ])
            . '?status[]=not_a_real_status');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function listOrdersFiltersOnOrderedByEmployee(): void
    {
        new OrderFactory()->for($this->normalCustomer)->createOne([
            'ordered_by_metadata' => json_encode([
                'schemaId' => 'employee',
                'email' => 'agent@yourhosting.nl',
            ], JSON_THROW_ON_ERROR),
        ]);
        new OrderFactory()->for($this->normalCustomer)->createOne([
            'ordered_by_metadata' => json_encode(['schemaId' => 'customer'], JSON_THROW_ON_ERROR),
        ]);

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.customers.orders.list', [
            'customer' => $this->normalCustomer->customer_number,
        ])
            . '?ordered_by=employee');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function listOrdersFiltersOnOrderedByCustomer(): void
    {
        new OrderFactory()->for($this->normalCustomer)->createOne([
            'ordered_by_metadata' => json_encode([
                'schemaId' => 'employee',
                'email' => 'agent@yourhosting.nl',
            ], JSON_THROW_ON_ERROR),
        ]);
        new OrderFactory()->for($this->normalCustomer)->createOne([
            'ordered_by_metadata' => json_encode(['schemaId' => 'customer'], JSON_THROW_ON_ERROR),
        ]);

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.customers.orders.list', [
            'customer' => $this->normalCustomer->customer_number,
        ])
            . '?ordered_by=customer');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function listOrdersIgnoresInvalidOrderedByValue(): void
    {
        new OrderFactory()->for($this->normalCustomer)->createOne([
            'ordered_by_metadata' => json_encode([
                'schemaId' => 'employee',
                'email' => 'agent@yourhosting.nl',
            ], JSON_THROW_ON_ERROR),
        ]);

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.customers.orders.list', [
            'customer' => $this->normalCustomer->customer_number,
        ])
            . '?ordered_by=system');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function listOrdersSearchesByEmployeeEmail(): void
    {
        new OrderFactory()->for($this->normalCustomer)->createOne([
            'ordered_by_metadata' => json_encode([
                'schemaId' => 'employee',
                'email' => 'agent@yourhosting.nl',
            ], JSON_THROW_ON_ERROR),
        ]);
        new OrderFactory()->for($this->normalCustomer)->createOne([
            'ordered_by_metadata' => json_encode([
                'schemaId' => 'employee',
                'email' => 'other@yourhosting.nl',
            ], JSON_THROW_ON_ERROR),
        ]);

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.customers.orders.list', [
            'customer' => $this->normalCustomer->customer_number,
        ])
            . '?search=agent%40yourhosting.nl');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function listOrdersEmailSearchDoesNotAllowSqlInjection(): void
    {
        new OrderFactory()->for($this->normalCustomer)->createOne([
            'ordered_by_metadata' => json_encode([
                'schemaId' => 'employee',
                'email' => 'agent@yourhosting.nl',
            ], JSON_THROW_ON_ERROR),
        ]);

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.customers.orders.list', [
            'customer' => $this->normalCustomer->customer_number,
        ])
            . "?search=' OR '1'='1");

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function requestBuInvoice(): void
    {
        $customer = new CustomerFactory()->createOne(['email' => 'migrationCustomer@sandwave.io']);
        $migratedCustomer = new MigratedCustomersFactory()->createOne(['reference_name' => 'versio']);
        $migratedCustomer->customers()->save($customer);

        $guzzleMock = self::createMock(Client::class);
        $guzzleMock->expects(self::once())->method('post');

        $this->app->bind(Client::class, fn () => $guzzleMock);

        $this->actingAsEmployee()
            ->post($this->generateRoute('admin.customers.requestBuInvoices', $customer->customer_number), [
                'referenceCustomerNumber' => $migratedCustomer->reference_customer_number,
                'fromDate' => CarbonImmutable::now()->subWeek()->format('Y-m-d'),
                'toDate' => CarbonImmutable::now()->format('Y-m-d'),
            ])
            ->assertOk();
    }

    #[Test]
    public function updateContactUpdatesAndReturnsNoContent(): void
    {
        $contact = new CustomerContactFactory()->for($this->normalCustomer)->createOne([
            'type' => CustomerContactType::DEFAULT->value,
        ]);

        $this->actingAsEmployee()
            ->putJson(
                $this->generateRoute('admin.customers.contacts.update', [
                    'customer' => $this->normalCustomer->customer_number,
                    'customerContact' => $contact->uuid,
                ]),
                [
                    'first_name' => 'Jane',
                    'last_name' => 'Doe',
                    'email' => 'jane.doe@sandwave.io',
                    'type' => CustomerContactType::FINANCIAL->value,
                ],
            )
            ->assertNoContent();

        $contact->refresh();
        self::assertSame('Jane', $contact->first_name);
        self::assertSame('Doe', $contact->last_name);
        self::assertSame('jane.doe@sandwave.io', $contact->email);
        self::assertSame(CustomerContactType::FINANCIAL->value, $contact->type);
    }

    #[Test]
    public function updateContactReturnUnprocessableOnInvalidPayload(): void
    {
        $contact = new CustomerContactFactory()->for($this->normalCustomer)->createOne([
            'type' => CustomerContactType::DEFAULT->value,
        ]);

        $this->actingAsEmployee()
            ->putJson(
                $this->generateRoute('admin.customers.contacts.update', [
                    'customer' => $this->normalCustomer->customer_number,
                    'customerContact' => $contact->uuid,
                ]),
                [
                    'first_name' => 'Jane',
                    'last_name' => 'Doe',
                    'email' => 'not-an-email',
                    'type' => 'invalid-type',
                ],
            )
            ->assertUnprocessable();
    }

    #[Test]
    public function listAvailableVolumeDiscountsReturnsOnlyUnassignedVolumeDiscounts(): void
    {
        $productGroup = new ProductGroupFactory()->volumeDiscount()->createOne();
        $available = new ProductDiscountFactory()->for(
            new ProductFactory()->for($productGroup)->createOne(),
        )->createOne();
        $assigned = new ProductDiscountFactory()->for(
            new ProductFactory()->for($productGroup)->createOne(),
        )->createOne();
        new CustomerProductDiscountFactory()
            ->for($this->normalCustomer)
            ->for($assigned)
            ->createOne();

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.customers.volume-discounts.available', [
            'customer' => $this->normalCustomer->customer_number,
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $available->id);
        $response->assertJsonPath('data.0.name', $available->name);
    }

    #[Test]
    public function addVolumeDiscountAttachesTheDiscountAndCreatesASubscription(): void
    {
        $productGroup = new ProductGroupFactory()->volumeDiscount()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        new ProductPriceComponentFactory()
            ->for($product)
            ->registration()
            ->createOne();
        $volumeDiscount = new ProductDiscountFactory()->for($product)->createOne();

        $response = $this->actingAsEmployee()->postJson(
            $this->generateRoute('admin.customers.add.volume.discount', [
                'customer' => $this->normalCustomer->customer_number,
            ]),
            ['product_discount_id' => $volumeDiscount->id, 'period' => 12],
        );

        $response->assertOk();
        $response->assertJsonPath(
            'message',
            self::resolve(TranslatorInterface::class)->translate('action.add-volume-discount.linked-successfully'),
        );
        self::assertCount(1, $volumeDiscount->customers);
        self::assertTrue(
            $this->normalCustomer->subscriptions()->where('product_uuid', $volumeDiscount->product->uuid)->exists(),
        );
    }

    #[Test]
    public function addVolumeDiscountFailsWhenTheCustomerAlreadyHasAProductDiscount(): void
    {
        $productGroup = new ProductGroupFactory()->volumeDiscount()->createOne();
        $existingDiscount = new ProductDiscountFactory()->for(
            new ProductFactory()->for($productGroup)->createOne(),
        )->createOne();
        new CustomerProductDiscountFactory()
            ->for($this->normalCustomer)
            ->for($existingDiscount)
            ->createOne();
        $volumeDiscount = new ProductDiscountFactory()->for(
            new ProductFactory()->for($productGroup)->createOne(),
        )->createOne();

        $response = $this->actingAsEmployee()->postJson(
            $this->generateRoute('admin.customers.add.volume.discount', [
                'customer' => $this->normalCustomer->customer_number,
            ]),
            ['product_discount_id' => $volumeDiscount->id, 'period' => 12],
        );

        $response->assertUnprocessable();
        $response->assertJsonPath(
            'message',
            self::resolve(TranslatorInterface::class)
                ->translate('action.add-volume-discount.customer-has-volume-discount'),
        );
        self::assertCount(0, $volumeDiscount->customers);
    }

    #[Test]
    public function addVolumeDiscountFailsWhenTheProductDiscountHasNoLinkedProduct(): void
    {
        $volumeDiscount = new ProductDiscountFactory()->createOne();

        $response = $this->actingAsEmployee()->postJson(
            $this->generateRoute('admin.customers.add.volume.discount', [
                'customer' => $this->normalCustomer->customer_number,
            ]),
            ['product_discount_id' => $volumeDiscount->id, 'period' => 12],
        );

        $response->assertUnprocessable();
        $response->assertJsonPath(
            'message',
            self::resolve(TranslatorInterface::class)
                ->translate('action.add-volume-discount.discount-has-no-product-linked'),
        );
        self::assertCount(0, $volumeDiscount->customers);
    }

    #[Test]
    public function addVolumeDiscountRequiresAnExistingProductDiscountAndAPeriod(): void
    {
        $response = $this->actingAsEmployee()->postJson(
            $this->generateRoute('admin.customers.add.volume.discount', [
                'customer' => $this->normalCustomer->customer_number,
            ]),
            ['product_discount_id' => 0],
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['product_discount_id', 'period']);
    }

    /**
     * The job keeps its constructor arguments private, so reach for them via reflection
     * rather than widening the job's public API purely for testing.
     *
     * @return list<int>
     */
    private static function getInvoiceIdsFromJob(DispatchConsolidatedInvoicesForCustomer $job): array
    {
        /** @var array<int, Invoice> $invoices */
        $invoices = new ReflectionProperty($job, 'invoices')->getValue($job);

        return array_values(array_map(fn (Invoice $invoice): int => $invoice->id, $invoices));
    }

    private static function getCreateInvoiceInstantlyFromJob(DispatchConsolidatedInvoicesForCustomer $job): bool
    {
        return (bool) new ReflectionProperty($job, 'createInvoiceInstantly')->getValue($job);
    }
}
