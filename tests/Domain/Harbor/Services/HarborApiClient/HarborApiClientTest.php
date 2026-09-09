<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\HarborApiClient;

use Generator;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\Exceptions\HarborApiConfigException;
use Waterfront\Domain\Harbor\Services\HarborApiClient\HarborApi;
use Waterfront\Infra\Configuration\ConfigurationInterface;

#[CoversClass(HarborApi::class)]
#[AllowMockObjectsWithoutExpectations]
class HarborApiClientTest extends IntegrationTestCase
{
    #[DataProvider('configDataProvider')]
    #[Test]
    public function checkMandatoryConfigsFailsMissing(string $configName): void
    {
        $config = self::createStub(ConfigurationInterface::class);
        $config
            ->method('getAsString')
            ->willReturnCallback(
                fn (string $name): string => match($name) {
                    $configName => '',
                    default => 'somevalue'
                }
            );

        $this->expectException(HarborApiConfigException::class);

        $expectedMessage = sprintf(
            'The mandatory configvar : %s is missing or empty. Check the config file :',
            $configName,
        );

        $this->expectExceptionMessageIsOrContains($expectedMessage);

        new HarborApi(new Client(), $config, self::createStub(LoggerInterface::class));
    }

    /**
     * @return Generator<mixed>
     */
    public static function configDataProvider(): Generator
    {
        yield ['harbor-api-client.connection.api_url'];
        yield ['harbor-api-client.connection.api_endpoint_prefix'];
        yield ['harbor-api-client.connection.api_authorization_header'];
    }
}
