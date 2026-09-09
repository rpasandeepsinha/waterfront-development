<?php

declare(strict_types=1);

namespace Tests\Infra\HubspotClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Marketing\Factory\HubspotSubscriptionFactory;
use Waterfront\Infra\HubspotClient\Client\HubspotCrmHttpClient;
use Waterfront\Infra\HubspotClient\DTO\HubspotSubscriptionDTO;
use Waterfront\Infra\HubspotClient\SubscriptionClient;

#[CoversClass(SubscriptionClient::class)]
class HubspotSubscriptionClientTest extends IntegrationTestCase
{
    private SubscriptionClient $subscriptionClient;

    private HubspotCrmHttpClient&MockObject $httpClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = self::createMock(HubspotCrmHttpClient::class);
        $this->app->bind(HubspotCrmHttpClient::class, fn () => $this->httpClient);

        $this->subscriptionClient = self::resolve(SubscriptionClient::class);
    }

    #[Test]
    public function createBatchWithoutContactAssociation(): void
    {
        $factory = self::resolve(HubspotSubscriptionFactory::class);
        $hubspotSubscription = $factory->createDTOFromSubscription(DomainSubscriptionDataProvider::subscription());

        $this->httpClient->expects(self::once())
            ->method('post')
            ->with(
                self::isString(),
                self::callback(function (array $body) {
                    self::assertArrayHasKey('inputs', $body);
                    self::assertCount(1, $body['inputs']);
                    self::assertObjectNotHasProperty('associations', $body['inputs'][0]);
                    return true;
                }),
            );

        $this->subscriptionClient->createBatch([$hubspotSubscription]);
    }

    #[Test]
    public function createBatchResponse(): void
    {
        $this->httpClient->expects(self::once())
            ->method('post')
            ->willReturn(json_decode((string) file_get_contents(__DIR__ . '/Data/batch_create_response.json'), true));

        // Doesn't matter what we send here, we are just testing the response handling
        $response = $this->subscriptionClient->createBatch([]);

        self::assertNotNull($response);
        self::assertCount(2, $response);
        self::assertContainsOnlyInstancesOf(HubspotSubscriptionDTO::class, $response);
        self::assertEqualsCanonicalizing(['237684293823', '237684293822'], array_map(fn (HubspotSubscriptionDTO $dto) => $dto->hubspotId, $response));
        self::assertEqualsCanonicalizing(['test1.nl', 'test2.nl'], array_map(fn (HubspotSubscriptionDTO $dto) => $dto->domain, $response));
    }

    #[Test]
    public function createBatchFailedResponse(): void
    {
        $this->httpClient->expects(self::once())
            ->method('post')
            ->willReturn(json_decode((string) file_get_contents(__DIR__ . '/Data/batch_create_error_response.json'), true));

        // Doesn't matter what we send here, we are just testing the response handling
        $response = $this->subscriptionClient->createBatch([]);

        self::assertNull($response);
    }

    #[Test]
    public function updateBatch(): void
    {
        $factory = self::resolve(HubspotSubscriptionFactory::class);
        $hubspotSubscription = $factory->createDTOFromSubscription(DomainSubscriptionDataProvider::subscription());

        $this->httpClient->expects(self::once())
            ->method('post')
            ->with(
                self::isString(),
                self::callback(function (array $body) {
                    self::assertArrayHasKey('inputs', $body);
                    self::assertCount(1, $body['inputs']);
                    self::assertObjectNotHasProperty('associations', $body['inputs'][0]);
                    return true;
                }),
            );

        $this->subscriptionClient->updateBatch([$hubspotSubscription]);
    }

    #[Test]
    public function listByUuid(): void
    {
        $this->httpClient->expects(self::once())
            ->method('post')
            ->willReturn(json_decode((string) file_get_contents(__DIR__ . '/Data/object_search_response.json'), true));

        $response = $this->subscriptionClient->listByUuid(['1d503aaf-5a7b-4ce6-bba7-bb9f608f1ec0', 'b40cfa50-d09a-4c34-a039-95aee35a087a']);

        self::assertCount(2, $response);
        self::assertEqualsCanonicalizing(['1d503aaf-5a7b-4ce6-bba7-bb9f608f1ec0', 'b40cfa50-d09a-4c34-a039-95aee35a087a'], array_column($response, 'swUuid'));
        self::assertEqualsCanonicalizing(['239466879178', '239454574812'], array_column($response, 'hubspotId'));
        self::assertEqualsCanonicalizing(['active', null], array_column($response, 'otsStatus'));
    }
}
