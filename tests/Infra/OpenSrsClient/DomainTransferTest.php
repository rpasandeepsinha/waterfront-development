<?php

declare(strict_types=1);

namespace Tests\Infra\OpenSrsClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\Domains\DTO\TransferParameters;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Infra\OpenSrsClient\Messages\Connection;
use Waterfront\Infra\OpenSrsClient\Messages\DomainTransferRequest;
use Waterfront\Infra\OpenSrsClient\Messages\DomainTransferResponse;
use Waterfront\Infra\OpenSrsClient\Support\OpsXml;

#[CoversClass(DomainTransferRequest::class)]
#[CoversClass(DomainTransferResponse::class)]
class DomainTransferTest extends TestCase
{
    #[Test]
    public function itBuildsTheSwRegisterTransferRequest(): void
    {
        $request = new DomainTransferRequest($this->client(), $this->connection(), 'example.org');
        $request->setParameters($this->parameters('secret-123'));

        $attributes = OpsXml::decode($request->getXml())['attributes'];

        self::assertSame('transfer', $attributes['reg_type']);
        self::assertSame('example.org', $attributes['domain']);
        self::assertSame('secret-123', $attributes['domain_auth_info']);
        self::assertSame('support@sandwave.io', $attributes['contact_set']['owner']['email']);
    }

    #[Test]
    public function itOmitsTheAuthInfoWhenNoSecretIsGiven(): void
    {
        $request = new DomainTransferRequest($this->client(), $this->connection(), 'example.org');
        $request->setParameters($this->parameters(null));

        $attributes = OpsXml::decode($request->getXml())['attributes'];

        self::assertArrayNotHasKey('domain_auth_info', $attributes);
    }

    #[Test]
    public function itMapsASuccessfulTransferToScheduled(): void
    {
        $result = $this->response()->getResult();

        self::assertSame(DomainStatus::SCHEDULED->value, $result->getStatus());
    }

    private function parameters(?string $secret): TransferParameters
    {
        /** @var array<string, mixed> $customer */
        $customer = include __DIR__ . '/data/customer.php';

        $data = [
            'domain'          => 'example.org',
            'period'          => 1,
            'customer'        => $customer,
            'nameServerGroup' => 'sandwave',
        ];
        if ($secret !== null) {
            $data['transferSecret'] = $secret;
        }

        return TransferParameters::create($data);
    }

    private function response(): DomainTransferResponse
    {
        return new DomainTransferResponse(new Response(
            200,
            ['Content-Type' => 'text/xml'],
            (string) file_get_contents(__DIR__ . '/data/opensrs_transfer_response.xml'),
        ));
    }

    private function client(): Client
    {
        return new Client();
    }

    private function connection(): Connection
    {
        return new Connection('https://horizon.opensrs.net:55443', 'reseller', 'key');
    }
}
