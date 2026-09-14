<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Customers;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Rfc4122\UuidV4;
use SandwaveIo\LighthouseAuthBase\Enum\AuthenticationMethod;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Session;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Resources\CustomerResource;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;
use Waterfront\Infra\Authentication\DTO\AuthenticatedEmployee;

#[CoversClass(CustomerResource::class)]
class CustomerResourceTest extends IntegrationTestCase
{
    #[Test]
    public function customerAgeWithoutCreateDate(): void
    {
        $customer = new CustomerFactory()
            ->withAddress()
            ->createOne([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'created_at' => null,
            ]);

        $resource = CustomerResource::make($customer);
        $request = Request::create($this->generateRoute('partners.customers.who-am-i'));

        $data = $resource->toArray($request);

        self::assertArrayHasKey('customer_age_in_weeks', $data);
        self::assertSame(0, $data['customer_age_in_weeks']);
    }

    #[Test]
    public function customerAgeWithRecentCreateDate(): void
    {
        $customer = new CustomerFactory()
            ->withAddress()
            ->createOne([
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

        $resource = CustomerResource::make($customer);
        $request = Request::create($this->generateRoute('partners.customers.who-am-i'));

        $data = $resource->toArray($request);

        self::assertArrayHasKey('customer_age_in_weeks', $data);
        self::assertSame(0, $data['customer_age_in_weeks']);
    }

    #[Test]
    public function whoAmIAsActingForWillReturnEmployeeSessionInformation(): void
    {
        $customer = new CustomerFactory()
            ->withAddress()
            ->createOne([
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

        $this->actingAsEmployee();
        $uuid = UuidV4::uuid4();
        $identitySchema = new KratosIdentity(
            $uuid,
            SchemaId::EMPLOYEE,
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
            new Session(
                'aal2',
                true,
                new DateTimeImmutable(),
                [AuthenticationMethod::PASSWORD, AuthenticationMethod::TOTP],
            ),
        );
        $authenticatedEmployee = new AuthenticatedEmployee(
            identitySchema: $identitySchema,
            verified: true,
        );
        $authenticationManager = self::createStub(AuthenticationManager::class);
        $authenticationManager->method('getAuthenticatedEmployee')->willReturn($authenticatedEmployee);
        $authenticationManager->method('getAuthenticatedSubject')->willReturn($authenticatedEmployee);

        $authenticationManager
            ->method('getAuthenticatedCustomer')
            ->willReturn(
                new AuthenticatedCustomer(
                    $customer,
                    $authenticationManager->getAuthenticatedSubject()->identitySchema,
                    true,
                ),
            );

        $this->app->bind(AuthenticationManager::class, fn () => $authenticationManager);
        $data = $this->getJson($this->generateRoute('partners.customers.who-am-i'))->assertOk();

        $data = json_decode($data->content(), true);
        assert(is_array($data));
        self::assertIsArray($data['session_information']);
        $sessionInformation = $data['session_information'];
        self::assertSame(SchemaId::EMPLOYEE->value, $sessionInformation['schema']);
        self::assertSame($uuid->toString(), $sessionInformation['identity_id']);
    }

    #[Test]
    public function customerAgeWithOldCreateDate(): void
    {
        $customer = new CustomerFactory()
            ->withAddress()
            ->createOne([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'created_at' => CarbonImmutable::now()->subDays(16),
            ]);

        $resource = CustomerResource::make($customer);
        $request = Request::create($this->generateRoute('partners.customers.who-am-i'));

        $data = $resource->toArray($request);

        self::assertArrayHasKey('customer_age_in_weeks', $data);
        self::assertSame(2, $data['customer_age_in_weeks']);
    }

    #[Test]
    public function customerHasMicrosoft365Tenant(): void
    {
        $customerWithoutTenant = new CustomerFactory()->withAddress()->createOne();

        $resource = CustomerResource::make($customerWithoutTenant, true);
        $request = Request::create($this->generateRoute('partners.customers.who-am-i'));

        $data = $resource->toArray($request);

        self::assertArrayHasKey('has_microsoft365_tenant', $data);
        self::assertFalse($data['has_microsoft365_tenant']);
        self::assertNull($data['microsoft365_tenant_name']);
        self::assertNull($data['microsoft365_tenant_id']);

        $customerWithTenant = new CustomerFactory()->withAddress()->createOne();
        $customerInfo = new Microsoft365CustomerInfoFactory()->for($customerWithTenant)->createOne();

        $resource = CustomerResource::make($customerWithTenant, true);
        $request = Request::create($this->generateRoute('partners.customers.who-am-i'));

        $data = $resource->toArray($request);

        self::assertArrayHasKey('has_microsoft365_tenant', $data);
        self::assertTrue($data['has_microsoft365_tenant']);
        self::assertSame($customerInfo->tenant_name, $data['microsoft365_tenant_name']);
        self::assertSame($customerInfo->tenant_id, $data['microsoft365_tenant_id']);
    }
}
