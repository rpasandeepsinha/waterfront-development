<?php

declare(strict_types=1);

namespace Tests\Infra\OpenproviderClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\Ssl\Interfaces\Models\Approver\Parameters;
use Waterfront\Domain\Ssl\Interfaces\Models\Approver\Result;
use Waterfront\Infra\OpenproviderClient\Fakers\OpenproviderClientFaker;
use Waterfront\Infra\OpenproviderClient\Messages\Connection;
use Waterfront\Infra\OpenproviderClient\Messages\SslApproverRequest;
use Waterfront\Infra\OpenproviderClient\Messages\SslApproverResponse;

#[CoversClass(SslApproverRequest::class)]
class SslApproverEmailTest extends TestCase
{
    #[Test]
    public function createXml(): void
    {
        $client = new OpenproviderClientFaker(
            new Client(),
            new Connection('https://test.nl', 'test-user', 'password'),
            $this->createStub(Dispatcher::class),
        );

        $parameters = $this->getParameters();

        $request = $client->getSslApproverRequest($parameters);

        $requestXml = file_get_contents(__DIR__ . '/data/openprovider_ssl_approver_request.xml');

        self::assertSame($requestXml, $request->getXml(), ' - SSL xml created correctly');
    }

    #[Test]
    public function processResponseXml(): void
    {
        $response = new Response(
            200,
            ['Content-Type' => 'text/xml'],
            (string) file_get_contents(__DIR__ . '/data/openprovider_ssl_approver_response.xml')
        );

        $sslResponse = new SslApproverResponse($response);
        $result = $sslResponse->getResult();

        self::assertSame(Result::STATUS_OK, $result->getStatus(), ' - retrieve status');
        self::assertSame(
            [
                'admin@example.org',
                'webmaster@example.org',
                'hostmaster@example.org',
            ],
            $result->getEmails(),
            ' - retrieve SSL approver emails'
        );
    }

    /**
     * Prepare a parameters object for the ssl.
     */
    private function getParameters(): Parameters
    {
        $parameters = include 'data/sslApprover.php';

        return Parameters::create($parameters);
    }
}
