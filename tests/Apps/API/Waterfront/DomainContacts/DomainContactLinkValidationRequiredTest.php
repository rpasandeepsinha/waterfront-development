<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\DomainContacts;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\DomainContactController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Exceptions\ContactValidationRequiredException;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(DomainContactController::class)]
class DomainContactLinkValidationRequiredTest extends IntegrationTestCase
{
    private const string DOMAIN = 'example.nu';

    private Customer $customer;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::REALTIME_REGISTER,
            'default' => true,
        ]);

        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
        ]);
        $product = new ProductFactory()->for($productGroup)->createOne([
            'name' => '.nu',
            'slug' => 'extension_nu',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
            'domain' => self::DOMAIN,
        ]);

        new DomainDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription->uuid,
        ]);
    }

    #[Test]
    public function linkReturns422WhenContactValidationIsRequired(): void
    {
        $contact = new DomainContactFactory()->createOne([
            'customer_id' => $this->customer->id,
        ]);

        $mockDomainService = self::createMock(DomainService::class);
        $mockDomainService
            ->expects(self::once())
            ->method('linkContactHandle')
            ->willThrowException(new ContactValidationRequiredException(
                sprintf('Contact validation required before linking for [%s]', self::DOMAIN),
            ));

        $this->app->bind(DomainService::class, fn () => $mockDomainService);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-contact.contacts.link', [
                    'contact' => $contact->id,
                    'domains' => [
                        [
                            'domain' => self::DOMAIN,
                            'type' => 'owner',
                        ],
                    ],
                ]),
            )
            ->assertUnprocessable()
            ->assertJsonStructure(['message'])
            ->assertJson([
                'message' => self::resolve(TranslatorInterface::class)
                    ->translate('domain-contact.domain-contacts-link-validation-required'),
            ]);
    }

    #[Test]
    public function linkReturns500WhenGenericExceptionOccurs(): void
    {
        $contact = new DomainContactFactory()->createOne([
            'customer_id' => $this->customer->id,
        ]);

        $mockDomainService = self::createMock(DomainService::class);
        $mockDomainService
            ->expects(self::once())
            ->method('linkContactHandle')
            ->willThrowException(new RuntimeException('Something went wrong'));

        $this->app->bind(DomainService::class, fn () => $mockDomainService);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.domain-contact.contacts.link', [
                    'contact' => $contact->id,
                    'domains' => [
                        [
                            'domain' => self::DOMAIN,
                            'type' => 'owner',
                        ],
                    ],
                ]),
            )
            ->assertInternalServerError()
            ->assertJson([
                'message' => self::resolve(TranslatorInterface::class)
                    ->translate('domain-contact.domain-contacts-link-failure'),
            ]);
    }
}
