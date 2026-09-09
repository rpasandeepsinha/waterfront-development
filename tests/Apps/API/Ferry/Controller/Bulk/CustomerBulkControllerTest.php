<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller\Bulk;

use Illuminate\Http\Client\Response as LaravelResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Ferry\Controllers\Bulk\CustomerBulkController;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\Ferry\Jobs\CreateDirectDebitMandateJob;
use Waterfront\Domain\Ferry\Models\MigratedDnsTemplateRecord;
use Waterfront\Domain\Harbor\Services\Message\DebtorBuilder;
use Waterfront\Infra\Configuration\ConfigurationInterface;

#[CoversClass(CustomerBulkController::class)]
class CustomerBulkControllerTest extends IntegrationTestCase
{
    private DebtorBuilder $debtorBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->debtorBuilder = self::resolve(DebtorBuilder::class);

        $productGroupExtension = ProductGroupFactory::new()->extension()->createOne();
        $domainProduct = ProductFactory::new()->for($productGroupExtension)->createOne(['slug' => 'extension_com']);
        new ProductPriceComponentFactory()->for($domainProduct)->prolongation()->createOne([
            'contract_period' => 12,
            'billing_period' => 12,
            'price' => 500,
        ]);
    }

    #[Test]
    public function thatBulkCreateCorporateCustomerReturnsSuccess(): void
    {
        $email = 'example@example.com';
        $email2 = 'bulk@test.com';

        $postData = include __DIR__ . '/data/bulk_customer_valid.php';
        $postData[0]['mandates'] = [
            [
                'type' => 'directdebit',
                'signature_date' => '1993-03-22',
                'consumer_name' => 'John Doe',
                'consumer_account' => 'NL18RABO0123459876',
                'consumer_bic' => 'RABONL2U',
            ],
        ];

        $postData[0]['dnsTemplates'] = [
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
        ];

        Http::fake();

        $configuration = self::resolve(ConfigurationInterface::class);

        $apiKey = $configuration->getAsString('ferry.ferry_azure_data_factory_job_api_key');
        $apiUrl = $configuration->getAsString('ferry.ferry_azure_data_factory_job_api_url');

        $expectedWebhookPayload = include __DIR__ . '/data/customer_webhook.php';
        $expectedWebhookPayload2 = include __DIR__ . '/data/customer_webhook_2.php';
        $expectedUrl = sprintf('%s/api/ConsumeFerryResponse', $apiUrl);

        Http::shouldReceive('post')
            ->withArgs(function ($url, $payload) use ($expectedUrl, $expectedWebhookPayload, $expectedWebhookPayload2) {
                self::assertSame($expectedUrl, $url);

                if ($payload['data']['reference_name'] === 'testNr01') {
                    $expectedWebhookPayload['data']['waterfront_customer_id'] = $payload['data']['waterfront_customer_id'];
                    self::assertSame($expectedWebhookPayload, $payload);
                } else {
                    $expectedWebhookPayload2['data']['waterfront_customer_id'] = $payload['data']['waterfront_customer_id'];
                    self::assertSame($expectedWebhookPayload2, $payload);
                }
            });

        Http::shouldReceive('withHeaders')->andReturnSelf();

        Http::shouldReceive('post')->andReturn(new LaravelResponse(new \GuzzleHttp\Psr7\Response()));

        Queue::fake([CreateDirectDebitMandateJob::class]);

        $response = $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.create.bulk'),
                $postData,
                [
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $response->assertStatus(Response::HTTP_MULTI_STATUS);

        self::assertTrue(Cache::has('vat.number.validation.NL861350480B01'), 'VAT number validation should be cached');

        $customer = Customer::where('email', $email)->firstOrFail();
        $customer2 = Customer::where('email', $email2)->firstOrFail();

        Queue::assertPushed(CreateDirectDebitMandateJob::class, function (CreateDirectDebitMandateJob $job) use ($customer): bool {
            self::assertSame($customer->id, $job->customer->id);
            self::assertSame('John Doe', $job->mollieMandateDirectDebitCreateDTO->consumerName);
            self::assertSame('NL18RABO0123459876', $job->mollieMandateDirectDebitCreateDTO->consumerAccount);
            self::assertSame('RABONL2U', $job->mollieMandateDirectDebitCreateDTO->consumerBic);
            self::assertSame('1993-03-22', $job->mollieMandateDirectDebitCreateDTO->signatureDate);
            self::assertSame('testNr01', $job->migratedCustomer?->reference_customer_number);

            return true;
        });

        $response->assertContent('Successfully created bulk customer jobs');

        // Customer checks
        self::assertSame('John', $customer->first_name);
        self::assertSame('Doe', $customer->last_name);
        self::assertSame('example@example.com', $customer->email);
        self::assertSame('nl-NL', $customer->locale);
        self::assertSame('12345678', $customer->coc_number);
        self::assertSame('NL861350480B01', $customer->vat_number);
        self::assertSame(21.0, $customer->vat_rate);

        $contact = $customer->customerContacts->firstOrFail();

        self::assertSame('John', $contact->first_name);
        self::assertSame('Doe', $contact->last_name);
        self::assertSame('example@example.com', $contact->email);
        self::assertSame(CustomerContactType::FINANCIAL->value, $contact->type);

        $address = $customer->address?->firstOrFail();

        self::assertInstanceOf(CustomerAddress::class, $address);
        self::assertSame('Teststraat', $address->street_name);
        self::assertSame('38', $address->street_number);
        self::assertSame('C', $address->street_number_addition);
        self::assertSame('Amsterdam', $address->city);
        self::assertSame('NL', $address->country_code);

        self::assertSame('(+31) 6-12345678', $customer->phone_number);

        $migratedCustomer = $customer->migratedCustomers->firstOrFail();

        self::assertSame('Test_bu', $migratedCustomer->reference_name);
        self::assertSame('testNr01', $migratedCustomer->reference_customer_number);
        self::assertSame('test_type', $migratedCustomer->group_type);
        self::assertSame(34, $customer->credit_limit);
        self::assertNull($migratedCustomer->migratedSubscriptions()->first());

        $note = $customer->notes->firstOrFail();

        self::assertSame('note', $note->note);

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
        self::assertSame(1, DnsCustomerTemplate::query()->count());

        $migratedDnsTemplates = $migratedCustomer->migratedDnsTemplates;
        $migratedDnsTemplate = $migratedDnsTemplates->firstOrFail();

        self::assertSame('1234', $migratedDnsTemplate->reference_template_id);

        $dnsCustomerTemplate = $migratedDnsTemplate->dnsCustomerTemplate;
        self::assertInstanceOf(DnsCustomerTemplate::class, $dnsCustomerTemplate);
        self::assertSame('DNS template test', $dnsCustomerTemplate->name);

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

        $migratedCustomer = $customer2->migratedCustomers->firstOrFail();
        self::assertSame('test_type', $migratedCustomer->group_type);
        self::assertSame('Test_bu', $migratedCustomer->reference_name);
        self::assertSame('testNr02', $migratedCustomer->reference_customer_number);
        self::assertFalse($migratedCustomer->successful);
        self::assertFalse($migratedCustomer->enable_invoicing);
        self::assertNull($migratedCustomer->migratedSubscriptions()->first());
    }

    #[Test]
    public function thatBulkCreateCorporateCustomerReturnsUnProcessable(): void
    {
        $response = $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.create.bulk'),
                ['something invalid'],
                [
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $response->assertStatus(422);
    }
}
