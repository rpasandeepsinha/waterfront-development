<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\Vat\Vat;
use Symfony\Component\HttpFoundation\Response;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Ferry\Controllers\CustomerController;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\Ferry\Jobs\CreateDirectDebitMandateJob;
use Waterfront\Domain\Ferry\Models\MigratedDnsTemplateRecord;
use Waterfront\Domain\Harbor\Services\Message\DebtorBuilder;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Support\Providers\VatApiFakers\VatNumberApiFaker;
use Waterfront\Support\Providers\VatApiFakers\VatRateApiFaker;

#[CoversClass(CustomerController::class)]
class CustomerControllerTest extends IntegrationTestCase
{
    private Product $domainProduct;

    private PriceResolver $priceResolver;

    private DebtorBuilder $debtorBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->priceResolver = self::resolve(PriceResolver::class);

        $this->debtorBuilder = self::resolve(DebtorBuilder::class);

        $productGroupExtension = ProductGroupFactory::new()->extension()->createOne();
        ProductGroupFactory::new()->hosting()->createOne();

        $this->domainProduct = ProductFactory::new()->for($productGroupExtension)->createOne([
            'slug' => 'extension_com',
        ]);
        new ProductPriceComponentFactory()
            ->for($this->domainProduct)
            ->prolongation()
            ->createOne([
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 500,
            ]);
    }

    #[Test]
    public function thatCreateCustomerRequiresAuthorization(): void
    {
        $response = $this->postJson(
            $this->generateRoute('ferry.customers.create'),
            [],
            [
                'Authorization' => 'Bearer fake_testing_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        self::assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function thatCreateCorporateCustomerReturnsSuccess(): void
    {
        $email = 'example@example.com';

        $postData = include __DIR__ . '/data/customer_valid.php';

        $postData['addresses'][1] = [
            'streetName' => 'anotherStreet',
            'streetNumber' => '35',
            'zipCode' => '1521TF',
            'city' => 'Den Haag',
            'countryCode' => 'NL',
            'type' => 'specialType',
        ];

        // Duplicate the address to test the prevention of duping
        $postData['addresses'][2] = [
            'streetName' => 'anotherStreet',
            'streetNumber' => '35',
            'zipCode' => '1521TF',
            'city' => 'Den Haag',
            'countryCode' => 'NL',
        ];

        $postData['mandates'] = [
            [
                'type' => 'directdebit',
                'signature_date' => '1993-03-22',
                'consumer_name' => 'John Doe',
                'consumer_account' => 'NL18RABO0123459876',
                'consumer_bic' => 'RABONL2U',
            ],
            [
                'type' => 'directdebit',
                'signature_date' => '1998-05-17',
                'consumer_name' => 'Jane Doe',
                'consumer_account' => 'NL02ABNA0123456789',
                'consumer_bic' => 'ABNA',
            ],
        ];

        $postData['dnsTemplates'] = [
            [
                'name' => 'DNS template test',
                'reference_template_id' => '1234',
                'records' => [
                    [
                        'reference_record_id' => '111',
                        'name' => '@',
                        'type' => DnsRecordType::AAAA->value,
                        'content' => '::1',
                        'priority' => null,
                        'ttl' => 3600,
                        'disabled' => false,
                    ],
                    [
                        'reference_record_id' => '222',
                        'name' => 'subdomain.@',
                        'type' => DnsRecordType::MX->value,
                        'content' => 'mail.@',
                        'priority' => 10,
                        'ttl' => 600,
                        'disabled' => true,
                    ],
                    [
                        'reference_record_id' => '333',
                        'name' => '_autodiscover._tcp.@',
                        'type' => DnsRecordType::SRV->value,
                        'content' => 'online.service.test',
                        'priority' => 5,
                        'weight' => 15,
                        'port' => 443,
                        'ttl' => 3600,
                        'disabled' => true,
                    ],
                ],
            ],
            [
                'name' => 'DNS template test empty',
                'reference_template_id' => '1234_empty',
                'records' => [],
            ],
            [
                'name' => 'DNS template test no records',
                'reference_template_id' => '1234_no_records',
            ],
        ];

        Queue::fake([
            CreateDirectDebitMandateJob::class,
        ]);

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response->assertOk();

        self::assertTrue(Cache::has('vat.number.validation.NL861350480B01'), 'VAT number validation should be cached');

        $customer = Customer::where('email', $email)->firstOrFail();

        Queue::assertPushed(CreateDirectDebitMandateJob::class, 2);

        $mandateJobs = [];

        // @phpstan-ignore-next-line
        foreach (Queue::pushedJobs()[CreateDirectDebitMandateJob::class] as $job) {
            if (! array_key_exists('job', $job)) {
                continue;
            }

            $mandateJobs[] = $job['job'];
        }

        self::assertContainsOnlyInstancesOf(CreateDirectDebitMandateJob::class, $mandateJobs);
        self::assertSame($customer->id, $mandateJobs[0]->customer->id);
        self::assertSame('John Doe', $mandateJobs[0]->mollieMandateDirectDebitCreateDTO->consumerName);
        self::assertSame('NL18RABO0123459876', $mandateJobs[0]->mollieMandateDirectDebitCreateDTO->consumerAccount);
        self::assertSame('RABONL2U', $mandateJobs[0]->mollieMandateDirectDebitCreateDTO->consumerBic);
        self::assertSame('1993-03-22', $mandateJobs[0]->mollieMandateDirectDebitCreateDTO->signatureDate);
        self::assertSame('testNr01', $mandateJobs[0]->migratedCustomer?->reference_customer_number);

        self::assertSame($customer->id, $mandateJobs[1]->customer->id);
        self::assertSame('Jane Doe', $mandateJobs[1]->mollieMandateDirectDebitCreateDTO->consumerName);
        self::assertSame('NL02ABNA0123456789', $mandateJobs[1]->mollieMandateDirectDebitCreateDTO->consumerAccount);
        self::assertSame('ABNA', $mandateJobs[1]->mollieMandateDirectDebitCreateDTO->consumerBic);
        self::assertSame('1998-05-17', $mandateJobs[1]->mollieMandateDirectDebitCreateDTO->signatureDate);
        self::assertSame('testNr01', $mandateJobs[1]->migratedCustomer?->reference_customer_number);

        $response->assertExactJson([
            'customerId' => $customer->id,
            'customerNumber' => $customer->customer_number,
            'referenceCustomerId' => 'testNr01',
        ]);

        // Customer checks
        self::assertSame('John', $customer->first_name);
        self::assertSame('Doe', $customer->last_name);
        self::assertSame('example@example.com', $customer->email);
        self::assertSame('nl-NL', $customer->locale);
        self::assertSame('12345678', $customer->coc_number);
        self::assertSame('NL861350480B01', $customer->vat_number);
        self::assertSame(21.0, $customer->vat_rate);
        self::assertSame('2021-03-14', $customer->customer_since->format('Y-m-d'));

        $contact = $customer->customerContacts->firstOrFail();

        self::assertSame('John', $contact->first_name);
        self::assertSame('Doe', $contact->last_name);
        self::assertSame('example@example.com', $contact->email);
        self::assertSame('Sandwave', $contact->company);
        self::assertSame(CustomerContactType::FINANCIAL->value, $contact->type);

        $addresses = CustomerAddress::query()->where('customer_id', $customer->id)->get();

        $address = $addresses->get(0);

        self::assertInstanceOf(CustomerAddress::class, $address);
        self::assertSame('Teststraat', $address->street_name);
        self::assertSame('38A', $address->street_number);
        self::assertSame('C', $address->street_number_addition);
        self::assertSame('Amsterdam', $address->city);
        self::assertSame('NL', $address->country_code);
        self::assertNull($address->type);

        $address2 = $addresses->get(1);

        self::assertInstanceOf(CustomerAddress::class, $address2);
        self::assertSame('anotherStreet', $address2->street_name);
        self::assertSame('35', $address2->street_number);
        self::assertNull($address2->street_number_addition);
        self::assertSame('Den Haag', $address2->city);
        self::assertSame('NL', $address2->country_code);
        self::assertSame('specialType', $address2->type);

        self::assertSame('(+31) 6-12345678', $customer->phone_number);

        $migratedCustomer = $customer->migratedCustomers->firstOrFail();

        self::assertSame('Test_bu', $migratedCustomer->reference_name);
        self::assertSame('testNr01', $migratedCustomer->reference_customer_number);
        self::assertSame('test_type', $migratedCustomer->group_type);
        self::assertSame(34, $customer->credit_limit);

        $note = $customer->notes->firstOrFail();

        self::assertSame(
            '7x9F2qP5rKbLmN8sT3vY6wZ1cX4dA0eBfGhJkIoOpQ7uVnRtHyUzM5lW4S9D2x3E6Cv8Bn0m1qJkLpO9iIuYtR5eH2gF4dXs7aV6cQwZ8yT3rK4lM7x9F2qP5rKbLmN8sT3vY6wZ1cX4dA0eBfGhJkIoOpQ7uVnRtHyUzM5lW4S9D2x3E6Cv8Bn0m1qJkLpO9iIuYtR5eH2gF4dXs7aV6cQwZ8yT3rK4lM7x9F2qP5rKbLmN8sT3vY6wZ1cX4dA0eBfGhJkIoOpQ7uVnRtHyUzM5lW4S9D2x3E6Cv8Bn0m1qJkLpO9iIuYtR5eH2gF4dXs7aV6cQwZ8yT3rK4lM',
            $note->note,
        );

        self::assertSame(1, $customer->terms_of_payment);
        self::assertSame('testdepartment', $customer->department);
        self::assertSame('testorganization', $customer->organization);

        $labels = $customer->labels;
        self::assertCount(2, $labels);
        self::assertSame('Main domain', $labels->get(0)?->value);
        self::assertSame('Personal', $labels->get(1)?->value);

        // throws an exception if we can't create the customer in Harbor
        $this->debtorBuilder->fromCustomer($customer);

        // DNS template
        self::assertSame(3, DnsCustomerTemplate::query()->count());

        $migratedDnsTemplates = $migratedCustomer->migratedDnsTemplates;
        $migratedDnsTemplate = $migratedDnsTemplates->where('reference_template_id', '1234')->firstOrFail();
        $migratedDnsTemplateEmpty = $migratedDnsTemplates->where('reference_template_id', '1234_empty')->firstOrFail();
        $migratedDnsTemplateNoRecordKey = $migratedDnsTemplates
            ->where('reference_template_id', '1234_no_records')
            ->firstOrFail();

        self::assertSame('1234', $migratedDnsTemplate->reference_template_id);

        $dnsCustomerTemplate = $migratedDnsTemplate->dnsCustomerTemplate;
        self::assertInstanceOf(DnsCustomerTemplate::class, $dnsCustomerTemplate);
        self::assertSame('DNS template test', $dnsCustomerTemplate->name);

        self::assertSame('1234_no_records', $migratedDnsTemplateNoRecordKey->reference_template_id);

        $dnsCustomerTemplateNoRecordKey = $migratedDnsTemplateNoRecordKey->dnsCustomerTemplate;
        self::assertInstanceOf(DnsCustomerTemplate::class, $dnsCustomerTemplateNoRecordKey);
        self::assertSame('DNS template test no records', $dnsCustomerTemplateNoRecordKey->name);

        self::assertSame([], $dnsCustomerTemplateNoRecordKey->load('records')->records->toArray());

        self::assertSame('1234_empty', $migratedDnsTemplateEmpty->reference_template_id);

        $dnsCustomerTemplateEmpty = $migratedDnsTemplateEmpty->dnsCustomerTemplate;
        self::assertInstanceOf(DnsCustomerTemplate::class, $dnsCustomerTemplateEmpty);
        self::assertSame('DNS template test empty', $dnsCustomerTemplateEmpty->name);

        self::assertSame([], $dnsCustomerTemplateEmpty->load('records')->records->toArray());

        // DNS template record 1
        $migratedRecord1 = $migratedDnsTemplate->migratedDnsTemplateRecords->get(0);
        self::assertInstanceOf(MigratedDnsTemplateRecord::class, $migratedRecord1);
        self::assertSame('111', $migratedRecord1->reference_record_id);

        $record1 = $migratedRecord1->dnsCustomerTemplateRecord;
        self::assertSame('@', $record1->name);
        self::assertSame(DnsRecordType::AAAA->value, $record1->type);
        self::assertSame('::1', $record1->content);
        self::assertNull($record1->priority);
        self::assertNull($record1->weight);
        self::assertNull($record1->port);
        self::assertSame(3600, $record1->ttl);
        self::assertFalse($record1->disabled);

        // DNS template record 2
        $migratedRecord2 = $migratedDnsTemplate->migratedDnsTemplateRecords->get(1);
        self::assertInstanceOf(MigratedDnsTemplateRecord::class, $migratedRecord2);
        self::assertSame('222', $migratedRecord2->reference_record_id);

        $record2 = $migratedRecord2->dnsCustomerTemplateRecord;
        self::assertSame('subdomain.@', $record2->name);
        self::assertSame(DnsRecordType::MX->value, $record2->type);
        self::assertSame('mail.@', $record2->content);
        self::assertSame(10, $record2->priority);
        self::assertNull($record2->weight);
        self::assertNull($record2->port);
        self::assertSame(600, $record2->ttl);
        self::assertTrue($record2->disabled);

        // DNS template record 3
        $migratedRecord3 = $migratedDnsTemplate->migratedDnsTemplateRecords->get(2);
        self::assertInstanceOf(MigratedDnsTemplateRecord::class, $migratedRecord3);
        self::assertSame('333', $migratedRecord3->reference_record_id);

        $record3 = $migratedRecord3->dnsCustomerTemplateRecord;
        self::assertSame('_autodiscover._tcp.@', $record3->name);
        self::assertSame(DnsRecordType::SRV->value, $record3->type);
        self::assertSame('online.service.test', $record3->content);
        self::assertSame(5, $record3->priority);
        self::assertSame(15, $record3->weight);
        self::assertSame(443, $record3->port);
        self::assertSame(3600, $record3->ttl);
        self::assertTrue($record3->disabled);
    }

    #[Test]
    public function thatCreateCorporateCustomerMinimalReturnsSuccess(): void
    {
        CarbonImmutable::setTestNow('now');

        $email = 'example@example.com';

        $postData = include __DIR__ . '/data/customer_minimal_valid.php';
        $postData['mandates'] = [
            [
                'type' => 'directdebit',
                'signature_date' => '1993-03-22',
                'consumer_name' => 'John Doe',
                'consumer_account' => 'NL18RABO0123459876',
                'consumer_bic' => 'RABONL2U',
            ],
        ];

        Queue::fake([
            CreateDirectDebitMandateJob::class,
        ]);

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response->assertOk();

        $customer = Customer::where('email', $email)->firstOrFail();

        Queue::assertPushed(CreateDirectDebitMandateJob::class, function (CreateDirectDebitMandateJob $job) use (
            $customer,
        ): bool {
            self::assertSame($customer->id, $job->customer->id);
            self::assertSame('John Doe', $job->mollieMandateDirectDebitCreateDTO->consumerName);
            self::assertSame('NL18RABO0123459876', $job->mollieMandateDirectDebitCreateDTO->consumerAccount);
            self::assertSame('RABONL2U', $job->mollieMandateDirectDebitCreateDTO->consumerBic);
            self::assertSame('1993-03-22', $job->mollieMandateDirectDebitCreateDTO->signatureDate);
            self::assertSame('testNr01', $job->migratedCustomer?->reference_customer_number);

            return true;
        });

        $response->assertExactJson([
            'customerId' => $customer->id,
            'customerNumber' => $customer->customer_number,
            'referenceCustomerId' => 'testNr01',
        ]);

        // Customer checks
        self::assertSame('J.', $customer->first_name);
        self::assertSame('D', $customer->last_name);
        self::assertSame('example@example.com', $customer->email);
        self::assertSame('nl-NL', $customer->locale);
        self::assertSame('12345678', $customer->coc_number);
        self::assertSame('NL861350480B01', $customer->vat_number);
        self::assertSame(21.0, $customer->vat_rate);
        self::assertEquals(CarbonImmutable::today(), $customer->customer_since);

        $contact = $customer->customerContacts->firstOrFail();

        self::assertSame('J.', $contact->first_name);
        self::assertSame('D', $contact->last_name);
        self::assertSame('example@example.com', $contact->email);
        self::assertSame(CustomerContactType::FINANCIAL->value, $contact->type);

        $address = $customer->address?->firstOrFail();

        self::assertInstanceOf(CustomerAddress::class, $address);
        self::assertSame('Teststraat', $address->street_name);
        self::assertSame('38', $address->street_number);
        self::assertSame("'s-Hertogenbosch", $address->city);
        self::assertSame('NL', $address->country_code);

        self::assertSame('(+31) 6-12345678', $customer->phone_number);

        $migratedCustomer = $customer->migratedCustomers->firstOrFail();

        self::assertSame('Test_bu', $migratedCustomer->reference_name);
        self::assertSame('testNr01', $migratedCustomer->reference_customer_number);
        self::assertSame('test_type', $migratedCustomer->group_type);
        self::assertSame(34, $customer->credit_limit);

        $note = $customer->notes->firstOrFail();

        self::assertSame('note', $note->note);

        self::assertSame(1, $customer->terms_of_payment);
        self::assertSame('t', $customer->department);
        self::assertSame('t@.*', $customer->organization);

        // throws an exception if we can't create the customer in Harbor
        $this->debtorBuilder->fromCustomer($customer);
    }

    #[Test]
    public function thatCreateCorporateCustomerReturnsSuccessWithEmptyDnsTemplates(): void
    {
        $email = 'example@example.com';

        $postData = include __DIR__ . '/data/customer_minimal_valid.php';
        $postData['dnsTemplates'] = [];

        Queue::fake();

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response->assertOk();

        $customer = Customer::where('email', $email)->firstOrFail();

        $response->assertExactJson([
            'customerId' => $customer->id,
            'customerNumber' => $customer->customer_number,
            'referenceCustomerId' => 'testNr01',
        ]);
    }

    #[Test]
    public function thatCreatePrivateCustomerReturnsSuccess(): void
    {
        $email = 'example@example.com';

        $postData = include __DIR__ . '/data/customer_valid.php';
        $postData['organization'] = null;
        $postData['department'] = null;
        $postData['vatNumber'] = null;
        $postData['cocNumber'] = null;
        $postData['purchaseReference'] = null;

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response->assertOk();

        $customer = Customer::where('email', $email)->firstOrFail();

        // Customer checks
        self::assertNull($customer->organization);
        self::assertNull($customer->department);
        self::assertNull($customer->vat_number);
        self::assertNull($customer->coc_number);
        self::assertNull($customer->purchase_reference);
        self::assertSame(21.0, $customer->vat_rate);

        // throws an exception if we can't create the customer in Harbor
        $this->debtorBuilder->fromCustomer($customer);
    }

    #[Test]
    public function thatCreatePrivateCustomerWithDifferentCountryPhoneNumberReturnsSuccess(): void
    {
        $email = 'example@example.com';

        $postData = include __DIR__ . '/data/customer_valid_different_country_phone_number.php';

        Queue::fake();

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response->assertOk();

        $customer = Customer::where('email', $email)->firstOrFail();

        // Dutch country code but Belgian phone number
        self::assertSame('NL', $customer->address?->country_code);
        self::assertSame('32', $customer->phone_country_code);

        // throws an exception if we can't create the customer in Harbor
        $this->debtorBuilder->fromCustomer($customer);
    }

    /**
     * @param array<string, mixed> $postData
     */
    #[DataProvider('providesValidationPayloads')]
    #[Test]
    public function validationReturnsSuccess(array $postData): void
    {
        $email = 'example@example.com';

        Queue::fake();

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response->assertOk();

        $customer = Customer::where('email', $email)->firstOrFail();

        $response->assertExactJson([
            'customerId' => $customer->id,
            'customerNumber' => $customer->customer_number,
            'referenceCustomerId' => '7',
        ]);

        // throws an exception if we can't create the customer in Harbor
        $this->debtorBuilder->fromCustomer($customer);
    }

    /** @return iterable<string, array<mixed>> */
    public static function providesValidationPayloads(): iterable
    {
        /**
         * Laravel FormRequests can't validate if a number is an int or a string.
         * So we use $request->integer in the controller instead.
         */
        yield 'Different types' => [
            'postData' => include __DIR__ . '/data/customer_valid_different_types.php',
        ];

        yield 'No mandates key in payload' => [
            'postData' => include __DIR__ . '/data/customer_without_mandates.php',
        ];
    }

    #[Test]
    public function thatCreateCustomerWithProductDiscountReturnsSuccess(): void
    {
        $email = 'example@example.com';
        $referenceCustomerId = 'testNr01';

        $postData = include __DIR__ . '/data/customer_valid.php';
        $postData['products_discounts'] = [
            [
                'slug' => 'extension_com',
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 100,
            ],
        ];

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response->assertOk();

        $customer = Customer::where('email', $email)->firstOrFail();

        $priceRequest = new PriceRequest([new ProlongationPriceRequest($this->domainProduct)], $customer);
        $list = $this->priceResolver->getPriceList($priceRequest);
        $pricing = $list->getProductPrice(
            $this->domainProduct->slug,
            12,
            12,
        );

        self::assertSame(100, $pricing->calculatedPrice);

        $response->assertExactJson([
            'customerId' => $customer->id,
            'customerNumber' => $customer->customer_number,
            'referenceCustomerId' => $referenceCustomerId,
        ]);

        // throws an exception if we can't create the customer in Harbor
        $this->debtorBuilder->fromCustomer($customer);
    }

    #[Test]
    public function thatCreateCustomerWithProductGroupDiscountReturnsSuccess(): void
    {
        $email = 'example@example.com';

        $postData = include __DIR__ . '/data/customer_valid.php';
        $postData['product_group_discounts'] = [
            [
                'product_group_type' => ProductGroupType::EXTENSION->value,
                'discount_percentage' => 25,
            ],
            [
                'product_group_type' => ProductGroupType::HOSTING->value,
                'discount_percentage' => 77.32,
            ],
        ];

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response->assertOk();

        $customer = Customer::where('email', $email)->firstOrFail();

        $productGroups = $customer->productGroups;

        foreach ($productGroups as $productGroup) {
            /** @var string $discountPercentage */
            $discountPercentage = $productGroup->pivot?->discount;

            if ($productGroup->slug === ProductGroupType::EXTENSION) {
                self::assertSame('25.00', $discountPercentage);
            }

            if ($productGroup->slug === ProductGroupType::HOSTING) {
                self::assertSame('77.32', $discountPercentage);
            }
        }

        $priceRequest = new PriceRequest([new ProlongationPriceRequest($this->domainProduct)], $customer);
        $list = $this->priceResolver->getPriceList($priceRequest);
        $pricing = $list->getProductPrice(
            $this->domainProduct->slug,
            12,
            12,
        );

        // 500 - 25% discount = 375
        self::assertSame(375, $pricing->calculatedPrice, 'Expected price with the product group discount');
    }

    #[Test]
    public function thatCreateCustomerWithProductGroupDiscountReturnsValidationError(): void
    {
        $email = 'example@example.com';

        $postData = include __DIR__ . '/data/customer_valid.php';
        $postData['product_group_discounts'] = [
            [
                'product_group_type' => ProductGroupType::EXTENSION->value,
                'discount_percentage' => 125, // can't be more than 100%
            ],
            [
                'product_group_type' => ProductGroupType::HOSTING->value,
                'discount_percentage' => -25, // can't be less than 0%
            ],
            [
                'product_group_type' => ProductGroupType::HOSTING->value, // no duplicate
                'discount_percentage' => 25,
            ],
            [
                'product_group_type' => 'blue', // not a product group
                'discount_percentage' => 0, // a discount of 0% would not be a discount at all
            ],
        ];

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response->assertUnprocessable();
        $response->assertExactJson([
            'message' => 'Dit veld heeft een dubbele waarde. (and 5 more errors)',
            'errors' => [
                'product_group_discounts.1.product_group_type' => [
                    'Dit veld heeft een dubbele waarde.',
                ],
                'product_group_discounts.2.product_group_type' => [
                    'Dit veld heeft een dubbele waarde.',
                ],
                'product_group_discounts.3.product_group_type' => [
                    'Geselecteerde product_group_discounts.3.product_group_type is ongeldig.',
                ],
                'product_group_discounts.0.discount_percentage' => [
                    'Dit veld mag niet groter zijn dan 100.',
                ],
                'product_group_discounts.1.discount_percentage' => [
                    'Dit veld dient minimaal 1 te zijn.',
                ],
                'product_group_discounts.3.discount_percentage' => [
                    'Dit veld dient minimaal 1 te zijn.',
                ],
            ],
        ]);

        self::assertSame(0, Customer::count());
    }

    #[Test]
    public function thatCreateCustomerWithInvalidDiscountReturnsFailure(): void
    {
        $postData = include __DIR__ . '/data/customer_valid.php';

        $postData['products_discounts'] = [
            [
                'slug' => 'extension_com',
                'contract_period' => 24,
                'billing_period' => 12,
                'price' => 100,
            ],
        ];

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'products_discounts.0',
            ],
        ]);
    }

    #[Test]
    public function thatCreateCustomerWithInvalidDiscountHigherPriceReturnsFailure(): void
    {
        $postData = include __DIR__ . '/data/customer_valid.php';

        $postData['products_discounts'] = [
            [
                'slug' => 'extension_com',
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 10000,
            ],
        ];

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'products_discounts.0',
            ],
        ]);
    }

    #[Test]
    public function thatCreateCustomerWithInvalidBusinessDataReturnsFailure(): void
    {
        $postData = include __DIR__ . '/data/customer_valid.php';

        $postData['cocNumber'] = 5;
        $postData['vatNumber'] = 6;
        $postData['department'] = 6;
        $postData['organization'] = 6;

        $postData['contacts'] = [
            [
                'firstName' => 55,
                'lastName' => '',
                'email' => 'example@example.com',
                'type' => CustomerContactType::FINANCIAL->value,
            ],
        ];

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $data = [
            'message' => 'Dit veld moet een string zijn. (and 4 more errors)',
            'errors' => [
                'cocNumber' => [
                    'Dit veld moet een string zijn.',
                ],
                'vatNumber' => [
                    'Dit veld moet een string zijn.',
                    'Dit veld bevat geen geldig BTW-nummer.',
                ],
                'contacts.0.firstName' => [
                    'Dit veld moet een string zijn.',
                ],
                'contacts.0.lastName' => [
                    'Dit veld is verplicht.',
                ],
            ],
        ];

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertExactJson($data);
    }

    #[Test]
    public function thatCreateCustomerReturnsUnprocessableWhenCustomerExist(): void
    {
        $email = 'example@example.com';
        $postData = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => $email,
            'gender' => Gender::MALE->value,
            'phone' => '+31612345678',
            'department' => 'BuildingNine',
            'addresses' => [
                [
                    'streetName' => 'UIuini',
                    'streetNumber' => '907',
                    'zipCode' => '10774',
                    'city' => 'Amsterdam',
                    'countryCode' => 'Netherlands',
                ],
            ],
            'organization' => '6786',
            'cocNumber' => '3456346',
            'vatNumber' => '67967',
            'vatRate' => 20,
            'creditLimit' => 34,
            'purchaseReference' => '3255',
            'paymentTerms' => '54654',
            'migrationName' => 'testBuN',
            'externalCustomerId' => 'testNr01',
            'groupType' => 'Pesttype',
            'validated' => false,
            'internalNote' => '43577',
            'referenceName' => 'test_reference_name_one',
            'referenceCustomerId' => 'test_reference_customer_nr_one',
            'products_discounts' => [],
            'mandates' => [],
            'wallet_credit_balance' => 1,
        ];

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.create'),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            )
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'addresses.0.countryCode',
                ],
            ]);
    }

    #[Test]
    public function thatCreateCustomerWithValidVatNumberReturnSuccess(): void
    {
        $postData = include __DIR__ . '/data/customer_valid.php';

        $email = 'example@example.com';
        $referenceCustomerId = 'testNr01';

        $response = $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.create'),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            )
            ->assertOk();

        $customer = Customer::where('email', $email)->firstOrFail();

        $response->assertExactJson([
            'customerId' => $customer->id,
            'customerNumber' => $customer->customer_number,
            'referenceCustomerId' => $referenceCustomerId,
        ]);

        $vatService = new Vat(
            null,
            $this->app->make(VatRateApiFaker::class),
            $this->app->make(VatNumberApiFaker::class),
        );

        self::assertSame(21.0, $vatService->europeanVatRate('NL'));
        self::assertSame(21.0, $customer->vat_rate);
    }

    #[Test]
    public function thatCreateCustomerWithInvalidVatNumberAndCountryCodeReturnFailure(): void
    {
        $postData = include __DIR__ . '/data/customer_valid.php';

        $postData['addresses'][0]['countryCode'] = 'XX';
        $postData['vatNumber'] = 'XX861350480B01';

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function thatRetryingGivesNoError(): void
    {
        $postData = include __DIR__ . '/data/customer_valid.php';

        Queue::fake();

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response->assertOk();

        $migratedCustomer = MigratedCustomer::where('reference_customer_number', 'testNr01')->firstOrFail();

        $migratedCustomer->reference_name = 'test_BU'; // change capital letters to test the "ilike" in the existing customer check
        $migratedCustomer->group_type = 'test_type_2'; // change batch name to test existing customer check
        $migratedCustomer->save();

        $customer = $migratedCustomer->customers()->firstOrFail();
        $customer->email = 'ExaMple@examPle.coM'; // capital letters so it should still find this e-mail address
        $customer->save();

        // Try to run it again
        $responseRetry = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $responseRetry->assertOk();

        $responseRetry->assertExactJson([
            'customerId' => $customer->id,
            'customerNumber' => $customer->customer_number,
            'referenceCustomerId' => $migratedCustomer->reference_customer_number,
        ]);

        self::assertDatabaseCount(Customer::class, 1);
        self::assertDatabaseCount(MigratedCustomer::class, 1);

        // Try to run it again, but with a different e-mail address
        $postData['email'] = 'another-email@example.com';

        $responseDifferentEmail = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $responseDifferentEmail->assertOk();

        self::assertDatabaseCount(Customer::class, 2);
        self::assertDatabaseCount(MigratedCustomer::class, 1);

        // Try to run it again, but with the same e-mail address under the same bu should NOT result in new customer
        $postData['email'] = 'another-email@example.com';

        $responseSameEmail = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $responseSameEmail->assertOk();

        self::assertDatabaseCount(Customer::class, 2);
        self::assertDatabaseCount(MigratedCustomer::class, 1);

        // Try to run it again, but with the same e-mail address and a new Bu customer reference. Should get new migrated + normal customer
        $postData['email'] = 'another-email@example.com';
        $postData['referenceCustomerId'] = 'a_different_id_but_for_same_mail_should_be_another_customer_entry';

        $responseSameEmail = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $responseSameEmail->assertOk();

        self::assertDatabaseCount(Customer::class, 3);
        self::assertDatabaseCount(MigratedCustomer::class, 2);
    }

    #[Test]
    public function createCorporateCustomerWithInvalidDnsTemplate(): void
    {
        $email = 'example@example.com';

        $postData = include __DIR__ . '/data/customer_minimal_valid.php';
        $postData['dnsTemplates'] = [
            [
                'name' => 'testname',
                'reference_template_id' => '1234',
                'records' => [
                    [
                        'reference_record_id' => null,
                        'name' => '@',
                        'type' => DnsRecordType::AAAA->value,
                        'content' => null,
                        'ttl' => 3600,
                    ],
                    [
                        'reference_record_id' => null,
                        'name' => '',
                        'type' => DnsRecordType::MX->value,
                        'content' => 0,
                        'priority' => -10,
                        'ttl' => 3600,
                        'disabled' => 'true',
                    ],
                    [
                        'reference_record_id' => '999',
                        'name' => 'subdomain.@',
                        'type' => 'super_non_existing_type',
                        'value' => 'mail.@',
                        'priority' => '',
                        'ttl' => 'test',
                        'disabled' => false,
                    ],
                    [
                        'reference_record_id' => '1010',
                        'name' => 'subdomain.|DOMAIN|', // we replace "@" with the domain so this is invalid
                        'type' => DnsRecordType::MX->value,
                        'content' => 'mail.|DOMAIN|',
                        'priority' => 10,
                        'ttl' => 3600,
                        'disabled' => false,
                    ],
                    [
                        'reference_record_id' => '1011',
                        'name' => 'domain.test', // "@" must be present
                        'type' => DnsRecordType::MX->value,
                        'content' => 'mail.server.test',
                        'priority' => 10,
                        'ttl' => 3600,
                        'disabled' => false,
                    ],
                ],
            ],
        ];

        Queue::fake();

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response->assertUnprocessable();

        $response->assertExactJson([
            'message' => 'Dit veld is verplicht. (and 12 more errors)',
            'errors' => [
                'dnsTemplates.0.records.0.reference_record_id' => [
                    'Dit veld is verplicht.',
                ],
                'dnsTemplates.0.records.0.content' => [
                    'Dit veld is verplicht.',
                ],
                'dnsTemplates.0.records.1.reference_record_id' => [
                    'Dit veld is verplicht.',
                ],
                'dnsTemplates.0.records.1.name' => [
                    'Dit veld is verplicht.',
                ],
                'dnsTemplates.0.records.1.content' => [
                    'Dit veld moet een string zijn.',
                ],
                'dnsTemplates.0.records.1.priority' => [
                    'Dit veld moet tussen 0 en 65535 liggen.',
                ],
                'dnsTemplates.0.records.1.disabled' => [
                    'Dit veld kan enkel true of false zijn.',
                ],
                'dnsTemplates.0.records.2.type' => [
                    'Geselecteerde dnsTemplates.0.records.2.type is ongeldig.',
                ],
                'dnsTemplates.0.records.2.content' => [
                    'Dit veld is verplicht.',
                ],
                'dnsTemplates.0.records.2.ttl' => [
                    'Dit veld dient een geheel getal te zijn.',
                ],
                'dnsTemplates.0.records.3.content' => [
                    'Record content must contain an @ and not contain |DOMAIN|',
                ],
                'dnsTemplates.0.records.3.name' => [
                    'Record name must contain an @ and not contain |DOMAIN|',
                ],
                'dnsTemplates.0.records.4.name' => [
                    'Record name must contain an @ and not contain |DOMAIN|',
                ],
            ],
        ]);
    }

    #[Test]
    public function createCorporateCustomerWithInvalidCocNumber(): void
    {
        $email = 'example@example.com';

        $postData = include __DIR__ . '/data/customer_minimal_valid.php';
        $postData['cocNumber'] = '123456789123456789';

        Queue::fake();

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response->assertUnprocessable();

        $response->assertExactJson([
            'message' => 'Dit veld mag niet groter zijn dan 16 karakters.',
            'errors' => [
                'cocNumber' => [
                    'Dit veld mag niet groter zijn dan 16 karakters.',
                ],
            ],
        ]);
    }

    #[Test]
    public function createWithInvalidReferenceName(): void
    {
        $postData = include __DIR__ . '/data/customer_minimal_valid.php';
        $postData['referenceName'] = 'businessDev';

        Queue::fake();

        $response = $this->actingAsSystem()->postJson(
            $this->generateRoute('ferry.customers.create'),
            $postData,
            [
                'Authorization' => 'Bearer ferry_testing_api_key',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response->assertUnprocessable();

        $response->assertExactJson([
            'message' => 'Reference name kan niet eindigen met de volgende waardes: dev, Dev, dry, Dry, SIT.',
            'errors' => [
                'referenceName' => [
                    'Reference name kan niet eindigen met de volgende waardes: dev, Dev, dry, Dry, SIT.',
                ],
            ],
        ]);
    }
}
