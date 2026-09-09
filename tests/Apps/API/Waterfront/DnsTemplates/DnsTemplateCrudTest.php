<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\DnsTemplates;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\DnsTemplateController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(DnsTemplateController::class)]
class DnsTemplateCrudTest extends IntegrationTestCase
{
    private Customer $customer;

    private DnsCustomerTemplate $dnsCustomerTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        //PHPStan does not seem to like create, using new as alternative.
        $this->dnsCustomerTemplate = new DnsCustomerTemplate([
            'name' => 'example',
            'customer_id' => $this->customer->id,
        ]);
        $this->dnsCustomerTemplate->save();
    }

    #[Test]
    public function index(): void
    {
        $nTemplatesInDb = DnsCustomerTemplate::where('customer_id', $this->customer->id)->count();

        $response = $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute('partners.dns.templates.index')
        )->assertOk();

        $json = $response->json();
        assert(is_array($json));
        $templates = $json['data'];

        self::assertCount($nTemplatesInDb, $templates, 'A unequal number of templates returned from index call for a customer.');
    }

    #[Test]
    public function store(): void
    {
        /** @var string[] $payload */
        $payload = json_decode((string) file_get_contents(__DIR__ . '/data/dns_template.json'), true, 512, JSON_THROW_ON_ERROR);

        $this
            ->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.dns.templates.store'), $payload)
            ->assertCreated();
    }

    #[Test]
    public function storeWithoutRecords(): void
    {
        $this
            ->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.dns.templates.store'),
                [
                    'name' => 'swagplate',
                ]
            )
            ->assertCreated();
    }

    #[Test]
    public function storeRecord(): void
    {
        $payload = json_decode((string) file_get_contents(__DIR__ . '/data/dns_template.json'), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($payload));

        foreach ($payload['records'] as $record) {
            $this->actingAsCustomer($this->customer)->postJson(
                $this->generateRoute('partners.dns.templates.record', ['template' => $this->dnsCustomerTemplate->id]),
                $record
            )->assertCreated();

            self::assertDatabaseHas('dns_customer_template_records', $record);
        }
    }

    #[Test]
    public function storeInvalid(): void
    {
        $payload = json_decode((string) file_get_contents(__DIR__ . '/data/dns_template_invalid.json'), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($payload));

        $response = $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.dns.templates.store'),
            $payload
        )->assertUnprocessable();

        $json = $response->json();

        self::assertIsArray($json);
        self::assertSame('PRIORITY: Dit veld is verplicht. PORT: Dit veld is verplicht.', $json['errors']['records.2'][0]);
    }

    #[Test]
    public function storeInvalidOnAllRecords(): void
    {
        $payload = json_decode((string) file_get_contents(__DIR__ . '/data/dns_template_invalid_all.json'), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($payload));

        $response = $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.dns.templates.store'),
            $payload
        )->assertUnprocessable();

        /** @var string[] $errors */
        $errors = $response->json('errors');
        self::assertSame('CONTENT: Dit veld is geen valide versie 4 ip adres.', $errors['records.0'][0]);
        self::assertSame('TTL: Dit veld is verplicht.', $errors['records.1'][0]);
        self::assertSame('PRIORITY: Dit veld is verplicht. PORT: Dit veld is verplicht.', $errors['records.2'][0]);
        self::assertSame($errors['records.3'][0], sprintf('CONTENT: %s', self::resolve(TranslatorInterface::class)->translate('validation.fqdn')));
        self::assertSame($errors['records.4'][0], sprintf('NAME: %s', self::resolve(TranslatorInterface::class)->translate('validation.fqdn')));
    }

    #[Test]
    public function show(): void
    {
        $response = $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute('partners.dns.templates.show', ['template' => $this->dnsCustomerTemplate->id])
        )->assertOk();

        $content = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($content));
        self::assertSame($this->dnsCustomerTemplate->name, Arr::get($content, 'data.name'));
    }

    #[Test]
    public function showNotFound(): void
    {
        $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute('partners.dns.templates.show', ['template' => $this->dnsCustomerTemplate->id . '222'])
        )->assertNotFound();
    }

    #[Test]
    public function update(): void
    {
        $payload = (array) json_decode((string) file_get_contents(__DIR__ . '/data/dns_template.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->actingAsCustomer($this->customer)->patchJson(
            $this->generateRoute('partners.dns.templates.update', ['template' => $this->dnsCustomerTemplate->id]),
            $payload
        )
            ->assertOk()
            ->assertJson([
                'message' => self::resolve(TranslatorInterface::class)->translate('dns-template.update-success'),
            ]);
    }
}
