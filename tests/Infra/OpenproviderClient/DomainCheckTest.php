<?php

declare(strict_types=1);

namespace Tests\Infra\OpenproviderClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Infra\OpenproviderClient\Messages\Connection;
use Waterfront\Infra\OpenproviderClient\Messages\DomainCheckRequest;
use Waterfront\Infra\OpenproviderClient\Messages\DomainCheckResponse;
use Waterfront\Infra\OpenproviderClient\OpenproviderClient;

#[CoversClass(DomainCheckRequest::class)]
#[CoversClass(DomainCheckResponse::class)]
class DomainCheckTest extends IntegrationTestCase
{
    #[Test]
    public function domainCheckService(): void
    {
        $domain = 'sandwave.nl';
        $domainCheckService = self::resolve(OpenproviderClient::class);
        $result = $domainCheckService->checkDomain($domain);

        self::assertSame($domain, $result->getDomain());
        self::assertContains($result->getStatus(), CheckResult::getStatuses());
    }

    #[Test]
    public function createXml(): void
    {
        $request = new DomainCheckRequest(
            new Client(),
            new Connection('https://test.nl/', 'test-user', 'password'),
            'example.org',
        );

        $requestXml = file_get_contents(__DIR__ . '/data/openprovider_check_request.xml');

        self::assertSame($requestXml, $request->getXml());
    }

    #[Test]
    public function processResponseXml(): void
    {
        $response = new Response(
            200,
            ['Content-Type' => 'text/xml'],
            (string) file_get_contents(__DIR__ . '/data/openprovider_check_response.xml'),
        );

        $domainCheckResponse = new DomainCheckResponse($response, 'sandwave.test');
        $result = $domainCheckResponse->getResult();

        self::assertSame('example.org', $result->getDomain());
        self::assertSame(CheckResult::STATUS_ACTIVE, $result->getStatus());
    }
}
