<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Orders;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactAnonymousHandleFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Events\CreateDns;
use Waterfront\Domain\Domains\Events\CreateDomain;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCreated;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversNothing]
class OrderDomainTest extends IntegrationTestCase
{
    private Customer $customer;

    private DomainContact $customContact;

    private DomainContact $anonymousContact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $defaultContact = new DomainContactFactory()->for($this->customer)->createOne();

        $this->anonymousContact = DomainContactfactory::new()->for($this->customer)->createOne();

        $anonymousHandleIdentifier = 'anonymized_handle';
        DomainContactAnonymousHandleFactory::new()->createOne([
            'handle' => $anonymousHandleIdentifier,
        ]);

        $domainGroup = new ProductGroupFactory()->createOne([
            'slug' => 'extension',
            'name' => 'Domein',
        ]);
        $product = new ProductFactory()->createOne([
            'product_group_id' => $domainGroup->id,
            'name' => '.com',
            'slug' => 'extension_com',
        ]);
        new ProductPriceComponentFactory()
            ->for($product)
            ->registration()
            ->createOne(['price' => 96]);

        $domainProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);

        $rtrProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::REALTIME_REGISTER,
            'enabled' => true,
            'default' => true,
        ]);

        $this->customContact = DomainContactFactory::new()->for($this->customer)->createOne();

        $this->anonymousContact->providers()->attach($rtrProvider, ['external_contact' => $anonymousHandleIdentifier]);
        $defaultContact->providers()->attach($domainProvider, ['external_contact' => 'handle-default-contact-1']);
        $this->customContact->providers()->attach($domainProvider, ['external_contact' => 'handle-custom-contact-1']);

        $dnsGroup = new ProductGroupFactory()->dns()->createOne(['name' => ProductGroupType::DNS]);
        $dnsProduct = new ProductFactory()->for($dnsGroup)->createOne([
            'name' => 'free-dns',
            'slug' => 'free-dns',
        ]);
        new ProductPriceComponentFactory()
            ->for($dnsProduct)
            ->registration()
            ->createOne(['price' => 0]);
    }

    #[Test]
    public function orderDomainWithContact(): void
    {
        Event::fake([
            CreateDns::class,
            CreateDomain::class,
        ]);

        new TemplateFactory()->createOne([
            'slug' => MailSubscriptionCreated::getTemplateSlug(),
        ]);
        $json = file_get_contents(__DIR__ . '/data/order_payload_domain_contact.json');

        if ($json === false) {
            throw new FileNotFoundException('Unable to load json');
        }

        $payload = (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        // The contact id is created in the database with an auto increment, this will fail in parallel testing mode if we use a hardcoded ID
        assert(is_array($payload['subscriptions']));
        $payload['subscriptions']['extension'][0]['contact_id'] = $this->customContact->id;

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.order.order'),
                $payload,
            )
            ->assertOk();

        $subscription = Subscription::query()
            ->whereProductGroupType(ProductGroupType::EXTENSION)
            ->where('domain', 'domainwithcontact.com')
            ->firstOrFail();

        $dnsSubscription = Subscription::query()
            ->whereProductGroupType(ProductGroupType::DNS)
            ->where('domain', $subscription->domain)
            ->firstOrFail();
        self::assertSame(AdministrativeStatus::ACTIVE->value, $dnsSubscription->administrative_status);

        $domainDeployment = $subscription->domainDeployment()->firstOrFail();

        self::assertNotNull($domainDeployment->contactOwner);

        $domainContactLink = $domainDeployment
            ->contactOwner
            ->providers()
            ->withPivot('external_contact')
            ->where('slug', $domainDeployment->provider->slug)
            ->firstOrFail();

        self::assertSame(
            'handle-custom-contact-1',
            $domainContactLink->pivot->external_contact,
        );

        Event::assertDispatched(CreateDns::class);
        Event::assertDispatched(CreateDomain::class);
    }

    #[Test]
    public function orderDomainWithAnonymousContactFails(): void
    {
        $json = file_get_contents(__DIR__ . '/data/order_payload_domain_contact.json');

        if ($json === false) {
            throw new FileNotFoundException('Unable to load json');
        }

        $payload = (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        assert(is_array($payload['subscriptions']));
        $payload['subscriptions']['extension'][0]['contact_id'] = $this->anonymousContact->id;

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.order.order'),
                $payload,
            )
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'validation.domain_contact.anonymous.rule',
                'errors' => [
                    'subscriptions.extension.0.contact_id' => [
                        'validation.domain_contact.anonymous.rule',
                    ],
                ],
            ]);
    }
}
