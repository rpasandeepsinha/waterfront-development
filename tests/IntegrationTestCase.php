<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\MockObject\Exception;
use Ramsey\Uuid\Rfc4122\UuidV4;
use Ramsey\Uuid\UuidInterface;

use function resolve as resolveFromContainer;

use SandwaveIo\LighthouseAuthBase\Enum\AuthenticationMethod;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Metadata\MetadataAdmin;
use SandwaveIo\LighthouseAuthBase\Identity\Metadata\MetadataPublic;
use SandwaveIo\LighthouseAuthBase\Identity\Session;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Tests\Factories\DnsNameserverFactory;
use Tests\Factories\DnsRegionFactory;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Customers\Models\CustomerContact;
use Waterfront\Domain\Customers\Models\CustomerVatError;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplateRecord;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Email\Models\Template;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Mailer\Mailer;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Payments\Models\Mandate;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Domain\Payments\Models\Payment;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Providers\Models\ProviderSetting;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Models\Label;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Models\VolumeDeployment;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;
use Waterfront\Infra\Authentication\DTO\AuthenticatedEmployee;
use Waterfront\Infra\Authentication\DTO\AuthenticatedSystem;
use Waterfront\Infra\Authentication\DTO\AuthenticatedUnregisteredCustomer;
use Waterfront\Infra\PowerDnsClient\PowerDnsClient;
use Waterfront\Infra\Queue\HarborQueue;

abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading();
        Http::preventStrayRequests();

        DB::statement(<<<SQL
            ALTER SEQUENCE customer_number_increment RESTART;
        SQL);

        $this->disableAuditLogging();
        $this->setupFakeS3Disk();
        $this->setUpNameservers();
        $this->setUpMockHarborQueue();
    }

    final public function pdns(PowerDnsClient $client): void
    {
        $this->app->bind(PowerDnsClient::class, fn (): PowerDnsClient => $client);
    }

    public function actingAsCustomer(Customer $customer, bool $verified = true): static
    {
        $identitySchema = new KratosIdentity(
            $customer->uuid,
            SchemaId::CUSTOMER,
            'active',
            null,
            new Traits($customer->email ?? 'pieter@post.nl', null),
            null,
            null,
            null,
            null,
            null,
            new MetadataPublic(['waterfront'], [$customer->customer_number], [], null, null, $this->customerPermissions(), null),
            null,
            null,
        );
        $authenticatedCustomer = new AuthenticatedCustomer(
            customer: $customer,
            identitySchema: $identitySchema,
            verified: $verified,
        );
        $authenticationManager = self::createMock(AuthenticationManager::class);
        $authenticationManager->method('getAuthenticatedCustomer')
            ->willReturn($authenticatedCustomer);
        $authenticationManager->method('getAuthenticatedSubject')
            ->willReturn($authenticatedCustomer);
        $authenticationManager->expects(self::never())
            ->method('getAuthenticatedEmployee');

        $this->app->bind(AuthenticationManager::class, fn () => $authenticationManager);

        return $this;
    }

    public function actingAsUnregisteredCustomer(?UuidInterface $uuid = null): static
    {
        $identitySchema = new KratosIdentity(
            $uuid ?? UuidV4::uuid4(),
            SchemaId::CUSTOMER,
            'active',
            null,
            new Traits('pieter@post.nl', null),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );
        $authenticatedCustomer = new AuthenticatedUnregisteredCustomer(
            identitySchema: $identitySchema,
        );
        $authenticationManager = self::createStub(AuthenticationManager::class);
        $authenticationManager->method('getAuthenticatedEmployee')
            ->willThrowException(new AuthenticationException());
        $authenticationManager->method('getAuthenticatedSystem')
            ->willThrowException(new AuthenticationException());
        $authenticationManager->method('getAuthenticatedSubject')
            ->willReturn($authenticatedCustomer);

        $this->app->bind(AuthenticationManager::class, fn () => $authenticationManager);

        return $this;
    }

    public function actingAsEmployee(?UuidInterface $uuid = null, ?string $email = null): static
    {
        $identitySchema = new KratosIdentity(
            $uuid ?? UuidV4::uuid4(),
            SchemaId::EMPLOYEE,
            'active',
            null,
            new Traits($email ?? 'pieter@post.nl', null),
            null,
            null,
            null,
            null,
            null,
            new MetadataPublic([], [], [], null, null, $this->employeePermissions(), null),
            new MetadataAdmin(['developer']),
            new Session('aal2', true, new DateTimeImmutable(), [AuthenticationMethod::PASSWORD, AuthenticationMethod::TOTP]),
        );
        $authenticatedEmployee = new AuthenticatedEmployee(
            identitySchema: $identitySchema,
            verified: true,
        );
        $authenticationManager = self::createStub(AuthenticationManager::class);
        $authenticationManager->method('getAuthenticatedCustomer')
            ->willThrowException(new AuthenticationException());
        $authenticationManager->method('getAuthenticatedSystem')
            ->willThrowException(new AuthenticationException());
        $authenticationManager->method('getAuthenticatedEmployee')
            ->willReturn($authenticatedEmployee);
        $authenticationManager->method('getAuthenticatedSubject')
            ->willReturn($authenticatedEmployee);

        $this->app->bind(AuthenticationManager::class, fn () => $authenticationManager);

        return $this;
    }

    public function actingAsSystem(?UuidInterface $uuid = null): static
    {
        $identitySchema = new KratosIdentity(
            $uuid ?? UuidV4::uuid4(),
            SchemaId::SYSTEM,
            'active',
            null,
            new Traits('pieter@post.nl', null),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );
        $authenticatedSystem = new AuthenticatedSystem(
            identitySchema: $identitySchema
        );
        $authenticationManager = self::createStub(AuthenticationManager::class);
        $authenticationManager->method('getAuthenticatedCustomer')
            ->willThrowException(new AuthenticationException());
        $authenticationManager->method('getAuthenticatedEmployee')
            ->willThrowException(new AuthenticationException());
        $authenticationManager->method('getAuthenticatedSystem')
            ->willReturn($authenticatedSystem);
        $authenticationManager->method('getAuthenticatedSubject')
            ->willReturn($authenticatedSystem);

        $this->app->bind(AuthenticationManager::class, fn () => $authenticationManager);

        return $this;
    }

    public function setUpMockHarborQueue(): void
    {
        $this->app->bind(HarborQueue::class, fn () => self::createStub(HarborQueue::class));
    }

    public function setUpNameservers(): void
    {
        $regions = new DnsRegionFactory()->createMany(3);
        foreach ($regions as $region) {
            $region->dnsNameservers()->save(new DnsNameserverFactory()->makeOne());
        }
    }

    /**
     * @param array<class-string> $classNames
     *
     * @throws Exception
     */
    public function assertEmailsSend(array $classNames): void
    {
        $assertions = array_map(fn (string $className) => [
            self::anything(),
            self::isInstanceOf($className),
        ], $classNames);

        $firstAssertion = current($assertions);
        assert(is_array($firstAssertion));

        $mailer = self::createMock(Mailer::class);
        $mailer->expects(self::exactly(count($classNames)))
            ->method('send')
            ->with(
                ...self::withConsecutive($firstAssertion, ...array_slice($assertions, 1))
            );
        $this->app->bind(Mailer::class, fn () => $mailer);
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    final protected static function resolve(string $class): mixed
    {
        return resolveFromContainer($class);
    }

    final protected function generateRoute(string $name, mixed $parameters = []): string
    {
        $router = self::resolve(UrlGenerator::class);
        return $router->route($name, $parameters);
    }

    private function setupFakeS3Disk(): void
    {
        Storage::fake($this->getConfiguration()->getAsString('filesystems.cloud'));
    }

    private function disableAuditLogging(): void
    {
        Config::set('audit.enabled', false);

        Customer::disableAuditing();
        CustomerAddress::disableAuditing();
        CustomerContact::disableAuditing();
        CustomerVatError::disableAuditing();
        DnsCustomerTemplate::disableAuditing();
        DnsCustomerTemplateRecord::disableAuditing();
        DomainContact::disableAuditing();
        DomainDeployment::disableAuditing();
        SubscriptionChange::disableAuditing();
        HostingDeployment::disableAuditing();
        Invoice::disableAuditing();
        Label::disableAuditing();
        Provider::disableAuditing();
        ProviderSetting::disableAuditing();
        ManagerDomainDeployment::disableAuditing();
        Mandate::disableAuditing();
        MollieCustomer::disableAuditing();
        Order::disableAuditing();
        OrderLineItem::disableAuditing();
        Payment::disableAuditing();
        Server::disableAuditing();
        SslDeployment::disableAuditing();
        Subscription::disableAuditing();
        Template::disableAuditing();
        VirtualMachineDeployment::disableAuditing();
        VolumeDeployment::disableAuditing();
    }

    /**
     * @return string[]
     */
    private function customerPermissions(): array
    {
        $array = [];
        foreach (Permissions::customerPermissions() as $permission) {
            $array[] = $permission->value;
        }
        return $array;
    }

    /**
     * @return string[]
     */
    private function employeePermissions(): array
    {
        $array = [];
        foreach (Permissions::employeePermissions() as $permission) {
            $array[] = $permission->value;
        }
        return $array;
    }
}
