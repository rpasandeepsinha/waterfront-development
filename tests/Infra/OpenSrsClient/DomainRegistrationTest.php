<?php

declare(strict_types=1);

namespace Tests\Infra\OpenSrsClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\Domains\DTO\RegistrationParameters;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Infra\OpenSrsClient\Messages\Connection;
use Waterfront\Infra\OpenSrsClient\Messages\DomainRegistrationRequest;
use Waterfront\Infra\OpenSrsClient\Messages\DomainRegistrationResponse;
use Waterfront\Infra\OpenSrsClient\Support\OpsXml;

#[CoversClass(DomainRegistrationRequest::class)]
#[CoversClass(DomainRegistrationResponse::class)]
class DomainRegistrationTest extends TestCase
{
    #[Test]
    public function itBuildsTheSwRegisterRequest(): void
    {
        $request = new DomainRegistrationRequest($this->client(), $this->connection(), 'example.org');
        $request->setParameters($this->parameters());

        $message = OpsXml::decode($request->getXml());
        $attributes = $message['attributes'];

        self::assertSame('SW_REGISTER', $message['action']);
        self::assertSame('example.org', $attributes['domain']);
        self::assertSame('new', $attributes['reg_type']);
        self::assertSame('process', $attributes['handle']);
        self::assertSame('1', $attributes['period']);
        self::assertSame('0', $attributes['f_whois_privacy']);
        self::assertSame('wf2', $attributes['reg_username']);
        // OpenSRS requires a 10-20 char password mixing letters and digits.
        self::assertGreaterThanOrEqual(10, strlen($attributes['reg_password']));
        self::assertLessThanOrEqual(20, strlen($attributes['reg_password']));
        self::assertMatchesRegularExpression('/[A-Za-z]/', $attributes['reg_password']);
        self::assertMatchesRegularExpression('/[0-9]/', $attributes['reg_password']);

        self::assertSame([
            ['name' => 'ns1.example.com', 'sortorder' => '1'],
            ['name' => 'ns2.example.com', 'sortorder' => '2'],
        ], $attributes['nameserver_list']);

        self::assertSame('Firstname', $attributes['contact_set']['owner']['first_name']);
        self::assertSame('Australielaan 11', $attributes['contact_set']['owner']['address1']);
        self::assertSame('+31.61123123123', $attributes['contact_set']['owner']['phone']);
        self::assertSame($attributes['contact_set']['owner'], $attributes['contact_set']['admin']);
    }

    #[Test]
    public function itMapsASuccessfulRegistrationToActive(): void
    {
        $result = $this->response('opensrs_register_response.xml')->getResult();

        self::assertSame(DomainStatus::ACTIVE, $result->getStatus());
    }

    #[Test]
    public function itMapsAFailedRegistrationToFailedWithReason(): void
    {
        $result = $this->response('opensrs_register_response_error.xml')->getResult();

        self::assertSame(DomainStatus::FAILED, $result->getStatus());
        self::assertStringContainsString('Domain already registered', $result->getReason());
    }

    private function parameters(): RegistrationParameters
    {
        /** @var array<string, mixed> $customer */
        $customer = include __DIR__ . '/data/customer.php';

        $parameters = RegistrationParameters::create([
            'domain'      => 'example.org',
            'period'      => 1,
            'nameServers' => [
                new Nameserver('ns1.example.com'),
                new Nameserver('ns2.example.com'),
            ],
        ]);
        $parameters->setCustomer($customer);

        return $parameters;
    }

    private function response(string $fixture): DomainRegistrationResponse
    {
        return new DomainRegistrationResponse(new Response(
            200,
            ['Content-Type' => 'text/xml'],
            (string) file_get_contents(__DIR__ . '/data/' . $fixture),
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
