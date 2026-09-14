<?php

declare(strict_types=1);

namespace Tests\Domain\MailManagement\Integration;

use Exception;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\MailController;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingPackageInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\MailManagement\Services\MailManagementPleskService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\PleskClient\DTO\MailAccount;
use Waterfront\Infra\PleskClient\Messages\EmailAccountCreate\EmailAccountCreateResponse;
use Waterfront\Infra\PleskClient\Messages\EmailAccountDelete\EmailAccountDeleteResponse;
use Waterfront\Infra\PleskClient\Messages\EmailGetAccountSettings\Result as EmailAccountResult;
use Waterfront\Infra\PleskClient\Messages\EmailPasswordReset\EmailPasswordResetResponse;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(MailController::class)]
#[CoversClass(MailManagementPleskService::class)]
class MailManagementPleskTest extends IntegrationTestCase
{
    private const string MAIL_DOMAIN = 'mail-domain.nl';
    private const int SITE_ID = 1234;
    private const string MAIL_ACCOUNT = 'info';
    private const string NEW_PASSWORD = 'P4ssw0rd!';

    private LoggerInterface&MockInterface $mockLogger;

    private HostingPackageInterface&MockInterface $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLogger = self::mock(LoggerInterface::class);
        $this->mockClient = self::mock(HostingPackageInterface::class);

        $this->app->bind(LoggerInterface::class, fn () => $this->mockLogger);
        $this->app->bind(HostingPackageInterface::class, fn () => $this->mockClient);
    }

    #[Test]
    public function authorizationFailsForRequestsIfNotMailManagement(): void
    {
        $subscription = SubscriptionFactory::new()
            ->for(
                ProductFactory::new()->for(ProductGroupFactory::new()->hosting())->state(fn (): array => [
                    'name' => 'product-without-mail-management',
                    'slug' => 'product-without-mail-management',
                ]),
            )
            ->withCustomer()
            ->createOne(['domain' => self::MAIL_DOMAIN]);

        $this->actingAsCustomer($subscription->customer)
            ->get($this->generateRoute('partners.mail.users', ['domain' => self::MAIL_DOMAIN]))
            ->assertForbidden();

        $this->actingAsCustomer($subscription->customer)
            ->post(
                uri: $this->generateRoute('partners.mail.reset-user', [
                    'domain' => self::MAIL_DOMAIN,
                    'username' => self::MAIL_ACCOUNT,
                ]),
                data: ['password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD],
            )
            ->assertForbidden();

        $this->actingAsCustomer($subscription->customer)
            ->delete(
                uri: $this->generateRoute('partners.mail.delete-user', [
                    'domain' => self::MAIL_DOMAIN,
                    'username' => self::MAIL_ACCOUNT,
                ]),
            )
            ->assertForbidden();
    }

    #[Test]
    public function listEmailAccountsLogAndResponseOnException(): void
    {
        // Subscription without HostingDeployment will throw model not found exception
        $subscription = SubscriptionFactory::new()
            ->for(ProductFactory::new()->emailMax())
            ->withCustomer()
            ->createOne(['domain' => self::MAIL_DOMAIN]);

        $this->mockLogger
            ->shouldReceive('info')
            ->withArgs(
                fn (string $message, array $context): bool => (
                    $message === sprintf(
                        'Hosting deployment not found for subscription [%s - %s]',
                        self::MAIL_DOMAIN,
                        $subscription->uuid,
                    )
                    && array_key_exists('exception', $context)
                    && $context['exception'] instanceof Exception
                ),
            );

        $this->actingAsCustomer($subscription->customer)
            ->get($this->generateRoute('partners.mail.users', ['domain' => self::MAIL_DOMAIN]))
            ->assertUnprocessable();
    }

    #[Test]
    public function listEmailAccounts(): void
    {
        $subscription = SubscriptionFactory::new()
            ->for(ProductFactory::new()->emailMax())
            ->withCustomer()
            ->has(HostingDeploymentFactory::new()->withPleskProvider())
            ->createOne(['domain' => self::MAIL_DOMAIN]);

        $hostingDeployment = $subscription->hostingDeployment;
        self::assertNotNull($hostingDeployment);

        $this->mockClient
            ->shouldReceive('setServer')
            ->withArgs(fn (Server $receivedServer) => $receivedServer->is($hostingDeployment->server));

        $this->mockClient->shouldReceive('getSiteIdByDomain')->with(self::MAIL_DOMAIN)->andReturn(self::SITE_ID);

        $mockEmailResult = self::mock(EmailAccountResult::class);

        $mockEmailResult->shouldReceive('getStatus')->andReturn(EmailAccountResult::STATUS_OK);

        $mockEmailResult
            ->shouldReceive('getEmailAccounts')
            ->andReturn([
                new MailAccount(
                    mailName: self::MAIL_ACCOUNT,
                    mailboxEnabled: true,
                    mailboxUsage: 10,
                    forwarding: false,
                    forwardDestinationAddresses: null,
                ),
                new MailAccount(
                    mailName: 'other',
                    mailboxEnabled: true,
                    mailboxUsage: 10,
                    forwarding: false,
                    forwardDestinationAddresses: null,
                ),
            ]);

        $this->mockClient->shouldReceive('getExistingEmailAccounts')->andReturn($mockEmailResult);

        $this->actingAsCustomer($subscription->customer)
            ->get($this->generateRoute('partners.mail.users', ['domain' => self::MAIL_DOMAIN]))
            ->assertOk()
            ->assertJson([
                'data' => [
                    ['username' => self::MAIL_ACCOUNT],
                    ['username' => 'other'],
                ],
            ]);
    }

    #[Test]
    public function createEmailAccounts(): void
    {
        $newAccount = self::MAIL_ACCOUNT . '_new';
        $subscription = SubscriptionFactory::new()
            ->for(ProductFactory::new()->emailMax())
            ->withCustomer()
            ->has(HostingDeploymentFactory::new()->withPleskProvider())
            ->createOne(['domain' => self::MAIL_DOMAIN]);

        $hostingDeployment = $subscription->hostingDeployment;
        self::assertNotNull($hostingDeployment);

        $this->mockClient
            ->shouldReceive('setServer')
            ->withArgs(fn (Server $receivedServer) => $receivedServer->is($hostingDeployment->server));

        $this->mockClient->shouldReceive('getSiteIdByDomain')->with(self::MAIL_DOMAIN)->andReturn(self::SITE_ID);

        $mockEmailResult = self::mock(Result::class);
        $mockEmailResult->shouldReceive('getStatus')->andReturn(Result::STATUS_OK);

        $mockEmailCreateResponse = self::mock(EmailAccountCreateResponse::class);
        $mockEmailCreateResponse->shouldReceive('getResult')->andReturn($mockEmailResult);

        $mockEmailCreateResponse
            ->shouldReceive('getEmailAccounts')
            ->andReturn([
                new MailAccount(
                    mailName: self::MAIL_ACCOUNT,
                    mailboxEnabled: true,
                    mailboxUsage: 10,
                    forwarding: false,
                    forwardDestinationAddresses: null,
                ),
                new MailAccount(
                    mailName: 'other',
                    mailboxEnabled: true,
                    mailboxUsage: 10,
                    forwarding: false,
                    forwardDestinationAddresses: null,
                ),
            ]);

        $this->mockClient->shouldReceive('getExistingEmailAccounts')->andReturn($mockEmailCreateResponse);

        $mockEmailCreateResponse = self::mock(EmailAccountCreateResponse::class);
        $mockEmailCreateResponse->shouldReceive('getResult')->andReturn($mockEmailResult);

        $this->mockLogger->shouldReceive('info')->with(
            'Mail createUser - Creating mail user',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::PRODUCT_SLUG => $subscription->product->slug,
                LoggingContextKeys::PRODUCT_ID => $subscription->product->id,
                LoggingContextKeys::DOMAIN_NAME => self::MAIL_DOMAIN,
                LoggingContextKeys::META => [
                    'hostname' => $hostingDeployment->server?->hostname,
                    'domain' => self::MAIL_DOMAIN,
                    'domainUsername' => $hostingDeployment->plesk_customer_username,
                    'mailUser' => $newAccount,
                    'limit' => 0,
                    'quota' => 0,
                    'driver' => ProviderSlug::PLESK->value,
                ],
            ],
        );

        $this->mockClient
            ->shouldReceive('createEmailAccount')
            ->with(self::MAIL_DOMAIN, $newAccount, self::NEW_PASSWORD)
            ->andReturn($mockEmailCreateResponse);

        $this->mockLogger->shouldReceive('notice')->with('Template not found for slug: email-account-created');

        $this->actingAsCustomer($subscription->customer)
            ->post($this->generateRoute('partners.mail.users', ['domain' => self::MAIL_DOMAIN]), [
                'username' => $newAccount,
                'password' => self::NEW_PASSWORD,
            ])
            ->assertCreated()
            ->assertJson([
                'data' => [
                    'user' => $newAccount,
                    'status' => true,
                ],
            ]);
    }

    #[Test]
    public function resetPasswordEmailAccount(): void
    {
        $subscription = SubscriptionFactory::new()
            ->for(ProductFactory::new()->emailMax())
            ->withCustomer()
            ->has(HostingDeploymentFactory::new()->withPleskProvider())
            ->createOne(['domain' => self::MAIL_DOMAIN]);

        $hostingDeployment = $subscription->hostingDeployment;
        $server = $hostingDeployment?->server;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);
        self::assertInstanceOf(Server::class, $server);

        $this->mockClient
            ->shouldReceive('setServer')
            ->withArgs(fn (Server $receivedServer) => $receivedServer->is($server));

        $this->mockClient->shouldReceive('getSiteIdByDomain')->with(self::MAIL_DOMAIN)->andReturn(self::SITE_ID);

        $mockEmailResult = self::mock(EmailAccountResult::class);

        $mockEmailResult->shouldReceive('getStatus')->andReturn(EmailAccountResult::STATUS_OK);

        $mockEmailResult
            ->shouldReceive('getEmailAccounts')
            ->andReturn([
                new MailAccount(
                    mailName: self::MAIL_ACCOUNT,
                    mailboxEnabled: true,
                    mailboxUsage: 10,
                    forwarding: false,
                    forwardDestinationAddresses: null,
                ),
                new MailAccount(
                    mailName: 'other',
                    mailboxEnabled: true,
                    mailboxUsage: 10,
                    forwarding: false,
                    forwardDestinationAddresses: null,
                ),
            ]);

        $this->mockClient->shouldReceive('getExistingEmailAccounts')->andReturn($mockEmailResult);

        $this->mockLogger->shouldReceive('info')->with('Mail account - Resetting password for mail only user', [
            LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            LoggingContextKeys::PRODUCT_SLUG => $subscription->product->slug,
            LoggingContextKeys::PRODUCT_ID => $subscription->product->id,
            LoggingContextKeys::DOMAIN_NAME => self::MAIL_DOMAIN,
            LoggingContextKeys::META => [
                'hostname' => $server->hostname,
                'domainUsername' => $hostingDeployment->plesk_customer_username,
                'mailUser' => self::MAIL_ACCOUNT,
                'quota' => 0,
            ],
        ]);

        $mockOkResult = new Result();
        $mockOkResult->setStatus(Result::STATUS_OK);

        $mockPasswordResetResponse = self::mock(EmailPasswordResetResponse::class);
        $mockPasswordResetResponse->shouldReceive('getResult')->andReturn($mockOkResult);

        $this->mockClient
            ->shouldReceive('resetEmailPassword')
            ->with(self::MAIL_DOMAIN, self::MAIL_ACCOUNT, self::NEW_PASSWORD)
            ->andReturn($mockPasswordResetResponse);

        $this->actingAsCustomer($subscription->customer)
            ->post(
                uri: $this->generateRoute('partners.mail.reset-user', [
                    'domain' => self::MAIL_DOMAIN,
                    'username' => self::MAIL_ACCOUNT,
                ]),
                data: ['password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD],
            )
            ->assertOk()
            ->assertJson(['status' => true]);
    }

    #[Test]
    public function deleteEmailAccount(): void
    {
        $subscription = SubscriptionFactory::new()
            ->for(ProductFactory::new()->emailMax())
            ->withCustomer()
            ->has(HostingDeploymentFactory::new()->withPleskProvider())
            ->createOne(['domain' => self::MAIL_DOMAIN]);

        $hostingDeployment = $subscription->hostingDeployment;
        $server = $hostingDeployment?->server;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);
        self::assertInstanceOf(Server::class, $server);

        $this->mockClient
            ->shouldReceive('setServer')
            ->withArgs(fn (Server $receivedServer) => $receivedServer->is($server));

        $this->mockClient->shouldReceive('getSiteIdByDomain')->with(self::MAIL_DOMAIN)->andReturn(self::SITE_ID);

        $mockEmailResult = self::mock(EmailAccountResult::class);

        $mockEmailResult->shouldReceive('getStatus')->andReturn(EmailAccountResult::STATUS_OK);

        $mockEmailResult
            ->shouldReceive('getEmailAccounts')
            ->andReturn([
                new MailAccount(
                    mailName: self::MAIL_ACCOUNT,
                    mailboxEnabled: true,
                    mailboxUsage: 10,
                    forwarding: false,
                    forwardDestinationAddresses: null,
                ),
                new MailAccount(
                    mailName: 'other',
                    mailboxEnabled: true,
                    mailboxUsage: 10,
                    forwarding: false,
                    forwardDestinationAddresses: null,
                ),
            ]);

        $this->mockClient->shouldReceive('getExistingEmailAccounts')->andReturn($mockEmailResult);

        $this->mockLogger->shouldReceive('info')->with('Deleting mail user account', [
            LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            LoggingContextKeys::PRODUCT_SLUG => $subscription->product->slug,
            LoggingContextKeys::PRODUCT_ID => $subscription->product->id,
            LoggingContextKeys::DOMAIN_NAME => self::MAIL_DOMAIN,
            LoggingContextKeys::META => [
                'hostname' => $server->hostname,
                'domainUsername' => $hostingDeployment->plesk_customer_username,
                'mailUser' => self::MAIL_ACCOUNT,
            ],
        ]);

        $mockOkResult = new Result();
        $mockOkResult->setStatus(Result::STATUS_OK);

        $mockEmailDeleteResponse = self::mock(EmailAccountDeleteResponse::class);
        $mockEmailDeleteResponse->shouldReceive('getResult')->andReturn($mockOkResult);

        $this->mockClient
            ->shouldReceive('deleteEmailAccount')
            ->with(self::MAIL_DOMAIN, self::MAIL_ACCOUNT)
            ->andReturn($mockEmailDeleteResponse);

        $this->actingAsCustomer($subscription->customer)
            ->delete(
                uri: $this->generateRoute('partners.mail.delete-user', [
                    'domain' => self::MAIL_DOMAIN,
                    'username' => self::MAIL_ACCOUNT,
                ]),
            )
            ->assertOk()
            ->assertJson(['status' => true]);
    }
}
