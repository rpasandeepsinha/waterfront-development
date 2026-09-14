<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Customers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Rfc4122\UuidV4;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\Identity;
use SandwaveIo\LighthouseAuthBase\Identity\Metadata\CustomerMetadataPublic;
use SandwaveIo\LighthouseAuthBase\Identity\Traits\IdentityTraits;
use Tests\Apps\API\Middleware\Helpers\JwtHelper;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\CustomersController;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Lighthouse\DTO\IdentityState;
use Waterfront\Domain\Lighthouse\Services\LighthouseApiService;
use Waterfront\Infra\Authentication\OathKeeperService;

#[CoversClass(CustomersController::class)]
class CustomerControllerRegisterTest extends IntegrationTestCase
{
    use JwtHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $oathKeeperService = self::createStub(OathKeeperService::class);

        $oathKeeperService->method('retrieveValidatedJwt')->willReturn($this->getJwtAsArray());

        $this->app->bind(OathKeeperService::class, fn () => $oathKeeperService);
    }

    #[Test]
    public function registerSuccessful(): void
    {
        $email = 'Lee@towers.com';
        $uuid = UuidV4::uuid4();

        $lighthouseApiServiceMock = self::createMock(LighthouseApiService::class);
        $lighthouseApiServiceMock
            ->expects(self::once())
            ->method('getKratosIdentityByIdentifier')
            ->with($uuid)
            ->willReturn(new Identity(
                'sdlaf',
                SchemaId::CUSTOMER,
                'lajkdf',
                IdentityState::ACTIVE->value,
                new IdentityTraits($email, null),
                [],
                [],
                CarbonImmutable::now(),
                CarbonImmutable::now(),
                [],
                new CustomerMetadataPublic([], [], ['waterfront'], null, null, null),
                null,
            ));

        $lighthouseApiServiceMock
            ->expects(self::once())
            ->method('updateKratosIdentity')
            ->with(self::callback(function (Identity $identityObject) {
                self::assertIsArray($identityObject->metadataPublic?->customerNumbers);
                self::assertCount(1, $identityObject->metadataPublic->customerNumbers);

                return true;
            }));

        $this->app->bind(LighthouseApiService::class, fn () => $lighthouseApiServiceMock);

        $payload = [
            'email' => $email,
            'first_name' => 'Lee',
            'last_name' => 'Towers',
            'gender' => Gender::MALE->value,
            'phone_number' => '+31612345678',
            'street_name' => 'street',
            'street_number' => '1908',
            'street_number_addition' => 'A',
            'zip_code' => '1234AA',
            'city' => 'Rotterdam',
            'country_code' => 'NL',
            'terms' => 'true',
        ];

        $headers = [
            'authorization' => 'Bearer tokentokentoken',
        ];

        $this->actingAsUnregisteredCustomer($uuid)
            ->postJson(
                $this->generateRoute('partners.customers.register'),
                $payload,
                $headers,
            )
            ->assertNoContent();

        self::assertDatabaseHas('customers', [
            'first_name' => 'Lee',
            'last_name' => 'Towers',
            'email' => $email,
            'is_verified' => false,
            'payment_type' => PaymentType::DIRECT,
        ]);

        self::assertDatabaseHas('customer_addresses', [
            'street_name' => 'street',
            'street_number' => '1908',
            'street_number_addition' => 'A',
            'zip_code' => '1234AA',
            'city' => 'Rotterdam',
        ]);
    }

    #[Test]
    public function registerSuccessfulWithDifferentCountryPhoneNumber(): void
    {
        $email = 'Lee@towers.com';
        $uuid = UuidV4::uuid4();

        $lighthouseApiServiceMock = self::createMock(LighthouseApiService::class);
        $lighthouseApiServiceMock
            ->expects(self::once())
            ->method('getKratosIdentityByIdentifier')
            ->with($uuid)
            ->willReturn(new Identity(
                'sdlaf',
                SchemaId::CUSTOMER,
                'lajkdf',
                IdentityState::ACTIVE->value,
                new IdentityTraits($email, null),
                [],
                [],
                CarbonImmutable::now(),
                CarbonImmutable::now(),
                [],
                new CustomerMetadataPublic([], [], ['waterfront'], null, null, null),
                null,
            ));

        $lighthouseApiServiceMock
            ->expects(self::once())
            ->method('updateKratosIdentity')
            ->with(self::callback(function (Identity $identityObject) {
                self::assertIsArray($identityObject->metadataPublic?->customerNumbers);
                self::assertCount(1, $identityObject->metadataPublic->customerNumbers);

                return true;
            }));

        $this->app->bind(LighthouseApiService::class, fn () => $lighthouseApiServiceMock);

        $payload = [
            'email' => $email,
            'first_name' => 'Lee',
            'last_name' => 'Towers',
            'gender' => Gender::MALE->value,
            'phone_number' => '+32470123456',
            'street_name' => 'street',
            'street_number' => '1908',
            'zip_code' => '1234AA',
            'city' => 'Rotterdam',
            'country_code' => 'NL',
            'terms' => 'true',
        ];

        $headers = [
            'authorization' => 'Bearer tokentokentoken',
        ];

        $this->actingAsUnregisteredCustomer($uuid)
            ->postJson(
                $this->generateRoute('partners.customers.register'),
                $payload,
                $headers,
            )
            ->assertNoContent();

        $customer = Customer::where('email', $email)->first();

        Assert::assertInstanceOf(Customer::class, $customer);
        Assert::assertSame('NL', $customer->address?->country_code);
        Assert::assertSame('32', $customer->phone_country_code);
    }

    #[Test]
    public function registerFailsWithInvalidCustomerData(): void
    {
        $email = 'Lee@towers.com';
        Http::fake([
            'https://api.lighthouse.sandwaveio.dev/api/login' => Http::response(['data' => [
                'user' => 'test.kees@sandwave.io',
                'token' => 'sandwave',
            ]]),
            sprintf('https://api.lighthouse.sandwaveio.dev/kratos/identities/%s', $email) => Http::response([], 404),
        ]);

        $payload = [
            'email' => $email,
            'gender' => Gender::MALE->value,
            'phone_number' => '+31612345678',
            'street_name' => 'street',
            'street_number' => '1908',
            'zip_code' => '1234AA',
            'city' => 'Rotterdam',
            'country_code' => 'NL',
            'terms' => 'true',
        ];

        $headers = [
            'authorization' => 'Bearer tokentokentoken',
        ];

        $this->actingAsUnregisteredCustomer()
            ->postJson(
                $this->generateRoute('partners.customers.register'),
                $payload,
                $headers,
            )
            ->assertUnprocessable();

        self::assertDatabaseEmpty('customers');
        self::assertDatabaseEmpty('customer_addresses');
    }
}
