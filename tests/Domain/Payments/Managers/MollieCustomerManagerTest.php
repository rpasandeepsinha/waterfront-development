<?php

declare(strict_types=1);

namespace Tests\Domain\Payments\Managers;

use DateTimeImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MollieCustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Payments\Managers\MollieCustomerManager;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerMetadataDTO;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerRequestDTO;
use Waterfront\Infra\MollieClient\Exceptions\MollieCustomerApiException;

#[CoversClass(MollieCustomerManager::class)]
class MollieCustomerManagerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    #[Test]
    public function findByMollieCustomerId(): void
    {
        $mollieCustomerId = 'cst_kEn1PlbGa';

        $customer = new CustomerFactory()->createOne();

        $mollieCustomerModel = new MollieCustomerFactory()->for($customer)->createOne([
            'mollie_customer_reference_id' => $mollieCustomerId,
        ]);

        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$mollieCustomerId" => function (Request $request) {
                self::assertSame('GET', $request->method());

                return Http::response(include __DIR__ . '/data/customers/fetch_customer_response.php');
            },
        ]);

        $mollieCustomerRepository = self::resolve(MollieCustomerManager::class);

        $mollieCustomer = $mollieCustomerRepository->findByMollieCustomer($mollieCustomerModel);

        self::assertSame('cst_kEn1PlbGa', $mollieCustomer->id);
        self::assertSame('test', $mollieCustomer->mode);
        self::assertSame('Customer A', $mollieCustomer->name);
        self::assertSame('customer@example.org', $mollieCustomer->email);
        self::assertSame('nl_NL', $mollieCustomer->locale);
        self::assertSame(1234, $mollieCustomer->metadata?->debtorId);
        self::assertSame('2018-04-06 13:23:21', $mollieCustomer->createdAt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function findByMollieCustomerIdNotFound(): void
    {
        $mollieCustomerId = 'cst_kEn1PlbGa';

        $customer = new CustomerFactory()->createOne();

        $mollieCustomerModel = new MollieCustomerFactory()->for($customer)->createOne([
            'mollie_customer_reference_id' => $mollieCustomerId,
        ]);

        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$mollieCustomerId" => function (Request $request) {
                self::assertSame('GET', $request->method());

                return Http::response(include __DIR__ . '/data/customers/errors/not_found.php', 404);
            },
        ]);

        $mollieCustomerRepository = self::resolve(MollieCustomerManager::class);

        self::expectException(MollieCustomerApiException::class);
        self::expectExceptionCode(404);
        self::expectExceptionMessageIs(
            sprintf(
                'Mollie API exception, code: %d, title: %s, detail: %s, field: %s',
                404,
                'Not Found',
                'The resource with the token "cst_kEn1PlbGa" could not be found.',
                '',
            ),
        );

        $mollieCustomerRepository->findByMollieCustomer($mollieCustomerModel);
    }

    #[Test]
    public function findOrCreateNotExists(): void
    {
        $customer = new CustomerFactory()->createOne();

        Http::fake([
            'api.mollie.sandwaveio.test/v2/customers' => function (Request $request) {
                self::assertSame('POST', $request->method());

                return Http::response(include __DIR__ . '/data/customers/create_customer_response.php', 201);
            },
        ]);

        $mollieCustomerRepository = self::resolve(MollieCustomerManager::class);

        $nonExistingMollieCustomer = new MollieCustomerRequestDTO(
            name: 'TestCustomerNonExisting',
            email: 'customer@example.org',
            locale: 'nl_NL',
            metadata: new MollieCustomerMetadataDTO(
                debtorId: 1234,
            ),
        );

        $createdMollieCustomer = $mollieCustomerRepository->findOrCreate($nonExistingMollieCustomer, $customer);

        self::assertSame($nonExistingMollieCustomer->name, $createdMollieCustomer->name);
        self::assertSame($nonExistingMollieCustomer->email, $createdMollieCustomer->email);
        self::assertSame($nonExistingMollieCustomer->locale, $createdMollieCustomer->locale);
        self::assertSame($nonExistingMollieCustomer->metadata?->debtorId, $createdMollieCustomer->metadata?->debtorId);

        $customer->refresh();

        $savedMollieCustomer = $customer->mollieCustomer;

        self::assertInstanceOf(MollieCustomer::class, $savedMollieCustomer);
        self::assertSame('cst_kEn1PlbGa', $savedMollieCustomer->mollie_customer_reference_id);
    }

    #[Test]
    public function findOrCreateMollieCustomerAlreadyExists(): void
    {
        $mollieCustomerId = 'cst_12345';
        $customer = new CustomerFactory()->withMollieCustomer([
            'mollie_customer_reference_id' => $mollieCustomerId,
        ])->createOne();

        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$mollieCustomerId" => function (Request $request) {
                self::assertSame('GET', $request->method());

                return Http::response(include __DIR__ . '/data/customers/fetch_customer_response.php');
            },
        ]);

        $mollieCustomerRepository = self::resolve(MollieCustomerManager::class);

        $existingMollieCustomer = new MollieCustomerRequestDTO(
            name: 'TestCustomer',
            email: 'email@testing.test',
            locale: 'nl_NL',
            metadata: new MollieCustomerMetadataDTO(
                debtorId: 1234,
            ),
        );

        $foundMollieCustomer = $mollieCustomerRepository->findOrCreate($existingMollieCustomer, $customer);

        self::assertSame('cst_kEn1PlbGa', $foundMollieCustomer->id);
        self::assertSame('test', $foundMollieCustomer->mode);
        self::assertSame('Customer A', $foundMollieCustomer->name);
        self::assertSame('customer@example.org', $foundMollieCustomer->email);
        self::assertSame('nl_NL', $foundMollieCustomer->locale);
        self::assertSame(1234, $foundMollieCustomer->metadata?->debtorId);
        self::assertSame('2018-04-06 13:23:21', $foundMollieCustomer->createdAt->format('Y-m-d H:i:s'));

        $customer->refresh();

        $savedMollieCustomer = $customer->mollieCustomer;

        self::assertInstanceOf(MollieCustomer::class, $savedMollieCustomer);
        self::assertSame('cst_12345', $savedMollieCustomer->mollie_customer_reference_id);
    }

    #[Test]
    public function findOrCreateMollieCustomerUnprocessableEntity(): void
    {
        $customer = new CustomerFactory()->createOne();

        Http::fake([
            'api.mollie.sandwaveio.test/v2/customers*' => Http::sequence()->push(
                include __DIR__ . '/data/customers/errors/unprocessable_entity.php',
                422,
            ),
        ]);

        $mollieCustomerRepository = self::resolve(MollieCustomerManager::class);

        $existingMollieCustomer = new MollieCustomerRequestDTO(
            name: 'TestCustomer',
            email: 'thisemailiswrong',
            locale: 'nl_NL',
            metadata: new MollieCustomerMetadataDTO(
                debtorId: 1234,
            ),
        );

        self::expectException(MollieCustomerApiException::class);
        self::expectExceptionCode(422);
        self::expectExceptionMessageIs(
            sprintf(
                'Mollie API exception, code: %d, title: %s, detail: %s, field: %s',
                422,
                'Unprocessable Entity',
                "The email address 'thisemailiswrong' is invalid",
                'email',
            ),
        );

        $mollieCustomerRepository->findOrCreate($existingMollieCustomer, $customer);

        $customer->refresh();
        $savedMollieCustomer = $customer->mollieCustomer;

        self::assertNull($savedMollieCustomer);
    }

    #[Test]
    public function updateCustomer(): void
    {
        $mollieCustomerId = 'cst_kEn1PlbGa';

        $customer = new CustomerFactory()->withMollieCustomer([
            'mollie_customer_reference_id' => $mollieCustomerId,
            'updated_at' => new DateTimeImmutable('yesterday'),
        ])->createOne();

        Http::fake([
            sprintf('api.mollie.sandwaveio.test/v2/customers/%s', $mollieCustomerId) => function (Request $request) {
                self::assertSame('PATCH', $request->method());

                $expectedResponseData = include __DIR__ . '/data/customers/update_customer_response.php';

                return Http::response($expectedResponseData);
            },
        ]);

        $customerUpdateData = new MollieCustomerRequestDTO(
            name: 'Customer B',
            email: 'customerB@example.org',
            locale: 'nl_BE',
            metadata: new MollieCustomerMetadataDTO(debtorId: 5678),
        );

        $mollieCustomer = $customer->mollieCustomer;
        self::assertInstanceOf(MollieCustomer::class, $mollieCustomer);
        $originalUpdatedAt = $mollieCustomer->updated_at;

        $mollieCustomerRepository = self::resolve(MollieCustomerManager::class);
        $updatedMollieCustomer = $mollieCustomerRepository->updateCustomer($mollieCustomer, $customerUpdateData);

        self::assertSame($mollieCustomerId, $updatedMollieCustomer->id);
        self::assertSame('test', $updatedMollieCustomer->mode);
        self::assertSame('Customer B', $updatedMollieCustomer->name);
        self::assertSame('customerB@example.org', $updatedMollieCustomer->email);
        self::assertSame('nl_BE', $updatedMollieCustomer->locale);
        self::assertSame(5678, $updatedMollieCustomer->metadata?->debtorId);
        self::assertSame('2018-04-06 13:23:21', $updatedMollieCustomer->createdAt->format('Y-m-d H:i:s'));

        $customer->refresh();

        $afterUpdateUpdated_at = $customer->mollieCustomer?->updated_at;

        self::assertNotSame($originalUpdatedAt, $afterUpdateUpdated_at);
    }

    #[Test]
    public function updateMollieCustomerIdNotFound(): void
    {
        $customer = new CustomerFactory()->withMollieCustomer([
            'mollie_customer_reference_id' => 'cst_kEn1PlbGa',
            'updated_at' => new DateTimeImmutable('yesterday'),
        ])->createOne();

        $mollieCustomer = $customer->mollieCustomer;
        self::assertInstanceOf(MollieCustomer::class, $mollieCustomer);
        $mollieCustomerId = $mollieCustomer->mollie_customer_reference_id;

        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$mollieCustomerId" => function (Request $request) {
                self::assertSame('PATCH', $request->method());

                return Http::response(include __DIR__ . '/data/customers/errors/not_found.php', 404);
            },
        ]);

        $mollieCustomerRepository = self::resolve(MollieCustomerManager::class);

        self::expectException(MollieCustomerApiException::class);
        self::expectExceptionCode(404);
        self::expectExceptionMessageIs(
            sprintf(
                'Mollie API exception, code: %d, title: %s, detail: %s, field: %s',
                404,
                'Not Found',
                'The resource with the token "cst_kEn1PlbGa" could not be found.',
                '',
            ),
        );

        $customerUpdateData = new MollieCustomerRequestDTO(
            name: 'Customer B',
            email: 'customerB@example.org',
            locale: 'nl_BE',
            metadata: new MollieCustomerMetadataDTO(debtorId: 5678),
        );

        $mollieCustomerRepository->updateCustomer($mollieCustomer, $customerUpdateData);
    }

    #[Test]
    public function updateMollieCustomerUnprocessable(): void
    {
        $customer = new CustomerFactory()->withMollieCustomer([
            'mollie_customer_reference_id' => 'cst_kEn1PlbGa',
            'updated_at' => new DateTimeImmutable('yesterday'),
        ])->createOne();

        $mollieCustomer = $customer->mollieCustomer;
        self::assertInstanceOf(MollieCustomer::class, $mollieCustomer);
        $mollieCustomerId = $mollieCustomer->mollie_customer_reference_id;

        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$mollieCustomerId" => function (Request $request) {
                self::assertSame('PATCH', $request->method());

                return Http::response(include __DIR__ . '/data/customers/errors/unprocessable_entity.php', 422);
            },
        ]);

        $mollieCustomerRepository = self::resolve(MollieCustomerManager::class);

        self::expectException(MollieCustomerApiException::class);
        self::expectExceptionCode(422);
        self::expectExceptionMessageIs(
            sprintf(
                'Mollie API exception, code: %d, title: %s, detail: %s, field: %s',
                422,
                'Unprocessable Entity',
                "The email address 'thisemailiswrong' is invalid",
                'email',
            ),
        );

        $customerUpdateData = new MollieCustomerRequestDTO(
            name: 'Customer B',
            email: 'customerB@example.org',
            locale: 'nl_BE',
            metadata: new MollieCustomerMetadataDTO(debtorId: 5678),
        );

        $mollieCustomerRepository->updateCustomer($mollieCustomer, $customerUpdateData);
    }
}
