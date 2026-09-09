<?php

declare(strict_types=1);

namespace Tests\Infra\MicrosoftOnlineClient;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\MicrosoftOnlineClient\Config\ConnectorConfig;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Webmozart\Assert\InvalidArgumentException;

#[CoversClass(ConnectorConfig::class)]
class ConnectorConfigTest extends TestCase
{
    #[DataProvider('getValidBaseUrls')]
    #[Test]
    public function validBaseUrl(string $baseUrl): void
    {
        $config = new ConnectorConfig(
            baseUrl: $baseUrl,
            retryConfig: new RetryConfig(),
        );

        self::assertSame($baseUrl, $config->baseUrl);
    }

    #[DataProvider('getInValidBaseUrls')]
    #[Test]
    public function invalidBaseUrl(string $baseUrl): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ConnectorConfig(
            baseUrl: $baseUrl,
            retryConfig: new RetryConfig(),
        );
    }

    public static function getValidBaseUrls(): Generator
    {
        yield 'Valid http' => ['http://baseurl-test.nl'];
        yield 'Valid https' => ['https://baseurl-test.nl'];
    }

    public static function getInValidBaseUrls(): Generator
    {
        yield 'Empty' => [''];
        yield 'Missing Protocol' => ['baseurl-test.nl'];
        yield 'Unsupported Protocol' => ['ftp://baseurl-test.nl'];
    }
}
