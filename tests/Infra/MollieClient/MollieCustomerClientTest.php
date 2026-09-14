<?php

declare(strict_types=1);

namespace Tests\Infra\MollieClient;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerMetadataDTO;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerRequestDTO;
use Waterfront\Infra\MollieClient\Exceptions\MollieCustomerApiException;
use Waterfront\Infra\MollieClient\MollieCustomerClient;

#[CoversClass(MollieCustomerClient::class)]
class MollieCustomerClientTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    #[Test]
    public function getCustomerById(): void
    {
        $mollieCustomerId = 'cst_kEn1PlbGa';

        Http::fake([
            "api.mollie.sandwaveio.test/v2/customers/$mollieCustomerId" => function (Request $request) {
                self::assertSame('GET', $request->method());

                $expectedListData = include __DIR__ . '/data/customers/fetch_customer_response.php';

                return Http::response($expectedListData);
            },
        ]);

        $client = self::resolve(MollieCustomerClient::class);

        $mollieCustomer = $client->getCustomerById($mollieCustomerId);

        self::assertSame('cst_kEn1PlbGa', $mollieCustomer->id);
        self::assertSame('test', $mollieCustomer->mode);
        self::assertSame('Customer A', $mollieCustomer->name);
        self::assertSame('customer@example.org', $mollieCustomer->email);
        self::assertSame('nl_NL', $mollieCustomer->locale);
        self::assertSame(1234, $mollieCustomer->metadata?->debtorId);
        self::assertSame('2018-04-06 13:23:21', $mollieCustomer->createdAt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function createCustomer(): void
    {
        Http::fake([
            'api.mollie.sandwaveio.test/v2/customers' => function (Request $request) {
                self::assertSame('POST', $request->method());

                $expectedResponseData = include __DIR__ . '/data/customers/create_customer_response.php';

                return Http::response($expectedResponseData, 201);
            },
        ]);

        $customerToCreate = new MollieCustomerRequestDTO(
            name: 'Customer A',
            email: 'customer@example.org',
            locale: 'nl_NL',
            metadata: new MollieCustomerMetadataDTO(debtorId: 1234),
        );

        $client = self::resolve(MollieCustomerClient::class);
        $mollieCustomer = $client->createCustomer($customerToCreate);

        self::assertSame('cst_kEn1PlbGa', $mollieCustomer->id);
        self::assertSame('test', $mollieCustomer->mode);
        self::assertSame('Customer A', $mollieCustomer->name);
        self::assertSame('customer@example.org', $mollieCustomer->email);
        self::assertSame('nl_NL', $mollieCustomer->locale);
        self::assertSame(1234, $mollieCustomer->metadata?->debtorId);
        self::assertSame('2018-04-06 13:23:21', $mollieCustomer->createdAt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function fetchCustomerNotFound(): void
    {
        Http::fake([
            'api.mollie.sandwaveio.test/v2/customers*' => Http::sequence()->push(
                include __DIR__ . '/data/customers/errors/not_found.php',
                404,
            ),
        ]);

        $client = self::resolve(MollieCustomerClient::class);

        self::expectException(MollieCustomerApiException::class);
        self::expectExceptionCode(404);
        self::expectExceptionMessageIs(
            sprintf(
                'Mollie API exception, code: %d, title: %s, detail: %s, field: %s',
                404,
                'Not Found',
                'The resource with the token "testje" could not be found.',
                '',
            ),
        );

        $client->getCustomerById('testje');
    }

    #[Test]
    public function createCustomerUnprocessableEntity(): void
    {
        Http::fake([
            'api.mollie.sandwaveio.test/v2/customers*' => Http::sequence()->push(
                include __DIR__ . '/data/customers/errors/unprocessable_entity.php',
                422,
            ),
        ]);

        $client = self::resolve(MollieCustomerClient::class);

        $customerToCreate = new MollieCustomerRequestDTO(
            name: 'John Smith',
            email: 'hoi',
            locale: 'nl_NL',
            metadata: null,
        );

        self::expectException(MollieCustomerApiException::class);
        self::expectExceptionCode(422);
        self::expectExceptionMessageIs(
            sprintf(
                'Mollie API exception, code: %d, title: %s, detail: %s, field: %s',
                422,
                'Unprocessable Entity',
                "The email address 'hoi' is invalid",
                'email',
            ),
        );

        $client->createCustomer($customerToCreate);
    }

    #[Test]
    public function updateCustomer(): void
    {
        Http::fake([
            'api.mollie.sandwaveio.test/v2/customers/cst_kEn1PlbGa' => function (Request $request) {
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

        $client = self::resolve(MollieCustomerClient::class);
        $mollieCustomer = $client->updateCustomer('cst_kEn1PlbGa', $customerUpdateData);

        self::assertSame('cst_kEn1PlbGa', $mollieCustomer->id);
        self::assertSame('test', $mollieCustomer->mode);
        self::assertSame('Customer B', $mollieCustomer->name);
        self::assertSame('customerB@example.org', $mollieCustomer->email);
        self::assertSame('nl_BE', $mollieCustomer->locale);
        self::assertSame(5678, $mollieCustomer->metadata?->debtorId);
        self::assertSame('2018-04-06 13:23:21', $mollieCustomer->createdAt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function updateCustomerNotFound(): void
    {
        Http::fake([
            'api.mollie.sandwaveio.test/v2/customers*' => Http::sequence()->push(
                include __DIR__ . '/data/customers/errors/not_found.php',
                404,
            ),
        ]);

        $client = self::resolve(MollieCustomerClient::class);

        self::expectException(MollieCustomerApiException::class);
        self::expectExceptionCode(404);
        self::expectExceptionMessageIs(
            sprintf(
                'Mollie API exception, code: %d, title: %s, detail: %s, field: %s',
                404,
                'Not Found',
                'The resource with the token "testje" could not be found.',
                '',
            ),
        );

        $customerToUpdate = new MollieCustomerRequestDTO(
            name: 'John Smith',
            email: 'hoi',
            locale: 'nl_NL',
            metadata: null,
        );

        $client->updateCustomer('testje', $customerToUpdate);
    }
}
