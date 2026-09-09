<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\Enums\TagFilter;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;

#[CoversClass(CloudStackClient::class)]
class CloudStackClientTest extends IntegrationTestCase
{
    #[Test]
    public function listTemplatesEmptyList(): void
    {
        $mockClient = $this->mock(CloudStackBaseClient::class);

        $mockClient->shouldReceive('execute')
            ->once()
            ->with('listTemplates', ['templatefilter' => 'featured', 'listall' => true])
            ->andReturn([
                'count' => 0,
                'template' => [],
            ]);

        $client = new CloudStackClient($mockClient, serializer: CloudstackSerializerFactory::get());
        $templates = $client->listTemplates();

        self::assertEmpty($templates);
    }

    #[Test]
    public function listTemplates(): void
    {
        $mockClient = $this->mock(CloudStackBaseClient::class);
        $data = json_decode((string) file_get_contents(__DIR__ . '/data/list-templates-success.json'), true, 512, JSON_THROW_ON_ERROR);

        $mockClient->shouldReceive('execute')
            ->once()
            ->with('listTemplates', ['templatefilter' => 'featured', 'listall' => true])
            ->andReturn($data);

        $client = new CloudStackClient($mockClient, serializer: CloudstackSerializerFactory::get());
        $templates = $client->listTemplates();

        self::assertCount(2, $templates);
        self::assertSame('Almalinux 9 - Password enabled', $templates[0]->name);
        self::assertSame('Almalinux 9', $templates[1]->name);
        $tag = $templates[1]->tags[0];
        self::assertSame('oscategory', $tag->key);
        self::assertSame('Almalinux', $tag->value);
    }

    #[Test]
    public function listNetworks(): void
    {
        $mockClient = $this->mock(CloudStackBaseClient::class);
        $data = json_decode((string) file_get_contents(__DIR__ . '/data/list-networks-success.json'), true, 512, JSON_THROW_ON_ERROR);

        $mockClient->shouldReceive('execute')
            ->once()
            ->with('listNetworks', [
                'traffictype'     => 'Guest',
                'listall'         => 'true',
                'type'            => 'shared',
                'canusefordeploy' => 'true',
                'tags'            => [
                    [
                        'key'   => TagFilter::VPS_NETWORK_KEY->value,
                        'value' => TagFilter::VPS_NETWORK->value,
                    ],
                ],
            ])
            ->andReturn($data);

        $client = new CloudStackClient($mockClient, serializer: CloudstackSerializerFactory::get());
        $templates = $client->listNetworks();

        self::assertCount(2, $templates);
        self::assertSame('VPS Network 2', $templates[0]->name);
        self::assertSame('VPS Network 1', $templates[1]->name);
        $tag = $templates[1]->tags[0];
        self::assertSame(TagFilter::VPS_NETWORK_KEY->value, $tag->key);
        self::assertSame(TagFilter::VPS_NETWORK->value, $tag->value);
    }

    #[Test]
    public function listTemplateByTag(): void
    {
        $expectedTag = 'Almalinux-9';

        $mockClient = $this->mock(CloudStackBaseClient::class);
        $data = json_decode((string) file_get_contents(__DIR__ . '/data/list-templates-success.json'), true, 512, JSON_THROW_ON_ERROR);

        $mockClient->shouldReceive('execute')
            ->once()
            ->with(
                'listTemplates',
                [
                    'templatefilter' => 'featured',
                    'listall' => true,
                    'tags' => [
                        [
                            'key' => 'template_slug',
                            'value' => $expectedTag,
                        ],
                    ],
                ]
            )
            ->andReturn($data);

        $client = new CloudStackClient($mockClient, serializer: CloudstackSerializerFactory::get());
        $templates = $client->listTemplates($expectedTag);

        self::assertCount(2, $templates);
        self::assertSame('Almalinux 9 - Password enabled', $templates[0]->name);
        self::assertSame('Almalinux 9', $templates[1]->name);
        $tag = $templates[1]->tags[0];
        self::assertSame('oscategory', $tag->key);
        self::assertSame('Almalinux', $tag->value);
    }

    #[Test]
    public function listTemplatesInvalidList(): void
    {
        $mockClient = $this->mock(CloudStackBaseClient::class);

        $mockClient->shouldReceive('execute')
            ->once()
            ->with('listTemplates', ['templatefilter' => 'featured', 'listall' => true])
            ->andReturn([
                'invalid' => 0,
            ]);

        self::expectException(ClientException::class);
        self::expectExceptionMessageIs('Invalid response from Cloudstack, missing templates.');

        $client = new CloudStackClient($mockClient, serializer: CloudstackSerializerFactory::get());
        $templates = $client->listTemplates();

        self::assertEmpty($templates);
    }

    #[Test]
    public function getConsoleUrl(): void
    {
        $vmId = '86f44477-86eb-41a9-a2f7-3384612fa20a';

        $data = json_decode((string) file_get_contents(__DIR__ . '/data/create-console-endpoint.json'), true, 512, JSON_THROW_ON_ERROR);

        $mockClient = $this->mock(CloudStackBaseClient::class);

        $mockClient->shouldReceive('execute')
            ->once()
            ->with('createConsoleEndpoint', ['virtualmachineid' => $vmId])
            ->andReturn($data);

        $client = new CloudStackClient($mockClient, serializer: CloudstackSerializerFactory::get());
        $consoleEndpoint = $client->getConsoleEndpoint($vmId);

        // These values match the JSON response.
        $expectedUrl = 'https://13.37.13.37.infra.cldin.net/resource/noVNC/vnc.html?autoconnect=true&port=8443&token=legit-token';
        $expectedHost = '13.37.13.37.infra.cldin.net';
        $expectedPort = '8443';
        $expectedPath = 'websockify';
        $expectedToken = 'legit-token';

        self::assertTrue($consoleEndpoint->success);
        self::assertNull($consoleEndpoint->details);
        self::assertSame($expectedUrl, $consoleEndpoint->url);
        self::assertSame($expectedHost, $consoleEndpoint->websocketHost);
        self::assertSame($expectedPort, $consoleEndpoint->websocketPort);
        self::assertSame($expectedPath, $consoleEndpoint->websocketPath);
        self::assertSame($expectedToken, $consoleEndpoint->websocketToken);
    }

    #[Test]
    public function getConsoleUrlExceptionWithIncorrectResponse(): void
    {
        $vmId = '86f44477-86eb-41a9-a2f7-3384612fa20a';

        $mockClient = $this->mock(CloudStackBaseClient::class);

        $mockClient->shouldReceive('execute')
            ->once()
            ->with('createConsoleEndpoint', ['virtualmachineid' => $vmId])
            ->andReturn([]);

        self::expectException(ClientException::class);
        self::expectExceptionMessageIs('Invalid response from Cloudstack, missing `consoleendpoint` object.');

        $client = new CloudStackClient($mockClient, serializer: CloudstackSerializerFactory::get());
        $client->getConsoleEndpoint($vmId);
    }

    #[Test]
    public function getConsoleUrlExceptionsWithEmptyResponse(): void
    {
        $vmId = '86f44477-86eb-41a9-a2f7-3384612fa20a';

        $mockClient = $this->mock(CloudStackBaseClient::class);

        $mockClient->shouldReceive('execute')
            ->once()
            ->with('createConsoleEndpoint', ['virtualmachineid' => $vmId])
            ->andReturn(['consoleendpoint' => []]);

        self::expectException(ClientException::class);
        self::expectExceptionMessageIs('Invalid response from Cloudstack, missing `consoleendpoint` object.');

        $client = new CloudStackClient($mockClient, serializer: CloudstackSerializerFactory::get());
        $client->getConsoleEndpoint($vmId);
    }

    #[Test]
    public function deleteDomain(): void
    {
        $jobId = '8d191cba-cdea-4b06-af48-9fccb50c7a0c';
        $domainId = 'b51920e8-1993-429d-9d1e-071281d9421e';

        $mockClient = $this->mock(CloudStackBaseClient::class);

        $mockClient->shouldReceive('execute')
            ->once()
            ->with('deleteDomain', ['id' => $domainId, 'cleanup' => true])
            ->andReturn([
                'jobid' => $jobId,
            ]);

        $adminClient = new CloudStackClient($mockClient, serializer: CloudstackSerializerFactory::get());

        $deleteDomain = $adminClient->deleteDomain($domainId, true);

        self::assertSame($jobId, $deleteDomain->jobId);
    }
}
