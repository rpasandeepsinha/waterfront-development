<?php

declare(strict_types=1);

namespace Tests\Infra\PleskClient;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateCustomer\Result;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Messages\CustomerGetDomainList\CustomerGetDomainListRequest;
use Waterfront\Infra\PleskClient\Messages\CustomerGetDomainList\CustomerGetDomainListResponse;
use Waterfront\Infra\PleskClient\Messages\CustomerGetDomainList\CustomerGetDomainListResult;
use Waterfront\Infra\PleskClient\Services\CustomerClient;

#[CoversClass(CustomerGetDomainListRequest::class)]
#[CoversClass(CustomerGetDomainListResponse::class)]
#[CoversClass(CustomerGetDomainListResult::class)]
class CustomerGetDomainListTest extends IntegrationTestCase
{
    private LoggerInterface&Stub $loggerMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->loggerMock = self::createStub(LoggerInterface::class);
    }

    #[Test]
    public function customerGetDomainListSendsCorrectXmlMessageAndReturnsStatusOk(): void
    {
        $customerLogin = 'website1234'; // Same as data XML file.
        $mock = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_customer_get_domain_list_response.xml')),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(fn (callable $handler) => function (RequestInterface $request, $options) use ($handler) {
            self::assertSame((string) file_get_contents(__DIR__ . '/data/plesk_customer_get_domain_list_request.xml'), (string) $request->getBody());
            return $handler($request, $options);
        });

        $client = new Client(['handler' => $handlerStack]);
        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $customerClient = new CustomerClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: $this->loggerMock,
            connection: $connection
        );

        $result = $customerClient->getDomainList($customerLogin);

        self::assertSame(Result::STATUS_OK, $result->getStatus());

        self::assertSame('jsmith111.com', $result->domains[0]->name);
        self::assertSame('sub1.jsmith111.com', $result->domains[1]->name);
        self::assertSame('sub2.jsmith111.com', $result->domains[2]->name);
        self::assertSame('aliassmith.com', $result->domains[3]->name);
        self::assertSame('adddom1.com', $result->domains[4]->name);
    }

    #[Test]
    public function invalidResponseMessage(): void
    {
        $xml = '<customer></customer>';
        $response = new CustomerGetDomainListResponse(new Response(200, [], $xml));

        self::assertSame(0, $response->getErrorCode());
        self::assertSame('No result element found in response', $response->getErrorText());
    }

    #[Test]
    public function errorResponse(): void
    {
        $xml = '<packet>
                  <customer>
                    <get-domain-list>
                      <result>
                        <status>mockerror</status>
                        <errcode>1337</errcode>
                        <errtext>Mock error</errtext>
                      </result>
                    </get-domain-list>
                  </customer>
                </packet>';

        $response = new CustomerGetDomainListResponse(new Response(200, [], $xml));

        self::assertSame(1337, $response->getErrorCode());
        self::assertSame('Mock error', $response->getErrorText());
    }
}
