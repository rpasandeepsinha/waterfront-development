<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\MailManagement;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ProviderSettingsFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Providers\Enums\ProviderSettingKey;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversNothing]
class MailUserCreateTest extends IntegrationTestCase
{
    private Customer $customer;

    private string $domain;

    public function setUp(): void
    {
        parent::setUp();

        $this->domain = 'example.com';
        $this->customer = new CustomerFactory()->createOne();

        $server = new ServerFactory()->createOne([
            'type' => ServerType::DIRECTADMIN_MAIL,
            'hostname' => $this->domain,
        ]);
        $productGroup  = new ProductGroupFactory()->createOne(['slug' => 'hosting']);
        $product = new ProductFactory()->mailOnly($productGroup)->createOne();

        new ProductSpecFactory()
        ->for($product)
        ->createOne([
            'name'  => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
            'value' => '1',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->for($product)->createOne([
            'domain' => 'example.com',
            'contract_period' => 12,
            'gross_price' => 121,
            'net_price' => 100,
        ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'mail_only_server_id'         => $server->id,
            'directadmin_customer_username' => 'goodtest',
        ]);

        $provider = ProviderFactory::new()->createOne(['type' => ProviderType::MAILONLY, 'slug' => ProviderSlug::DIRECTADMIN, 'default' => true, 'enabled' => true]);
        $settings = ProviderSettingsFactory::new()->createMany([
            ['provider_id' => $provider->id, 'key' => ProviderSettingKey::QUOTA],
            ['provider_id' => $provider->id, 'key' => ProviderSettingKey::LIMIT],
        ]);
        $provider->settings()->saveMany($settings);
    }

    #[Test]
    public function createUser(): void
    {
        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.mail.create-user', [
                    'domain' => $this->domain,
                ]),
                [
                    'username' => 'testuser',
                    'password' => 'Sterkwachtwoord123',
                ],
            )
            ->assertCreated()
            ->assertJson([
                'data' => [
                    'user' => 'testuser',
                    'status' => true,
                ],
            ]);
    }

    #[Test]
    public function createUserForUnknownDomain(): void
    {
        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.mail.create-user', [
                    'domain' => 'wrongdomain.com',
                ]),
                [
                    'username' => 'testuser',
                    'password' => 'Sterkwachtwoord123',
                ]
            )->assertForbidden();
    }

    #[Test]
    public function createUserNoDomain(): void
    {
        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.mail.create-user', [
                    'domain' => $this->domain,
                ]),
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'errors' => [
                    'username' => [
                        self::resolve(TranslatorInterface::class)->translate('validation.required'),
                    ],
                    'password' => [
                        self::resolve(TranslatorInterface::class)->translate('validation.required'),
                    ],
                ],
            ]);
    }

    #[Test]
    public function createUserPasswordFiveChars(): void
    {
        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.mail.create-user', [
                    'domain' => $this->domain,
                ]),
                [
                    'username' => 'testuser',
                    'password' => 'aD123',
                ],
            )
            ->assertUnprocessable()
            ->assertJson([
                'errors' => [
                    'password' => [
                        0 => self::resolve(TranslatorInterface::class)->translate('mail-providers.password-min'),
                    ],
                ],
            ]);
    }

    #[Test]
    public function createUserNoAlphanumericPassword(): void
    {
        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.mail.create-user', [
                    'domain' => $this->domain,
                ]),
                [
                    'username' => 'testuser',
                    'password' => '!!!!!!!!',
                ],
            )
            ->assertUnprocessable()
            ->assertJson([
                'errors' => [
                    'password' => [
                        0 => self::resolve(TranslatorInterface::class)->translate('mail-providers.password-regex'),
                    ],
                ],
            ]);
    }

    #[Test]
    public function createUserNoCapitalizedPassword(): void
    {
        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.mail.create-user', [
                    'domain' => $this->domain,
                ]),
                [
                    'username' => 'testuser',
                    'password' => 'blabla12',
                ],
            )
            ->assertUnprocessable()
            ->assertJson([
                'errors' => [
                    'password' => [
                        0 => self::resolve(TranslatorInterface::class)->translate('mail-providers.password-regex'),
                    ],
                ],
            ]);
    }
}
