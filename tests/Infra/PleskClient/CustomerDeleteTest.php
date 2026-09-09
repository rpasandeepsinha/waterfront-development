<?php

declare(strict_types=1);

namespace Tests\Infra\PleskClient;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer\Result;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\Fakers\CustomerClientFaker;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Services\CustomerClient;

#[CoversClass(CustomerClient::class)]
class CustomerDeleteTest extends IntegrationTestCase
{
    #[Test]
    public function customerDeleteSendsCorrectXmlMessageAndReturnsSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_customer_delete_response.xml')),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(fn (callable $handler) => function (RequestInterface $request, $options) use ($handler) {
            self::assertSame((string) file_get_contents(__DIR__ . '/data/plesk_customer_delete_request.xml'), (string) $request->getBody());
            return $handler($request, $options);
        });

        $client = new Client(['handler' => $handlerStack]);
        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $customerClient = new CustomerClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection
        );

        $data = require __DIR__ . '/data/pleskDeleteCustomerData.php';
        $parameters = Parameters::create($data);

        $result = $customerClient->deleteCustomer($parameters);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function fakeCustomerDeleteReturnsSuccess(): void
    {
        $customerClient = self::resolve(CustomerClientFaker::class);
        $customerClient->setDesiredResponseCode(200);

        $data = require __DIR__ . '/data/pleskDeleteCustomerData.php';
        $parameters = Parameters::create($data);
        $result = $customerClient->deleteCustomer($parameters);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function fakeCustomerDeleteReturnsFailure(): void
    {
        $customerClient = self::resolve(CustomerClientFaker::class);
        $customerClient->setDesiredResponseCode(500);

        $data = require __DIR__ . '/data/pleskDeleteCustomerData.php';
        $parameters = Parameters::create($data);
        $result = $customerClient->deleteCustomer($parameters);

        self::assertSame(500, $result->getErrorCode());
        self::assertSame(Result::STATUS_ERROR, $result->getStatus());
    }
}
