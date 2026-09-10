<?php

declare(strict_types=1);

namespace Tests\Infra\OpenSrsClient\Messages;

use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\OpenSrsClient\Messages\BaseRequest;
use Waterfront\Infra\OpenSrsClient\Messages\Connection;
use Waterfront\Infra\OpenSrsClient\Messages\DomainLookupRequest;

#[CoversClass(BaseRequest::class)]
#[CoversClass(DomainLookupRequest::class)]
class BaseRequestSignatureTest extends TestCase
{
    #[Test]
    public function itSignsTheBodyWithADoubleMd5OfTheApiKey(): void
    {
        $apiKey = 'super-secret-key';
        $request = new DomainLookupRequest(
            new Client(),
            new Connection('https://horizon.opensrs.net:55443', 'reseller', $apiKey),
            'example.org',
        );

        $xml = $request->getXml();

        self::assertSame(
            md5(md5($xml . $apiKey) . $apiKey),
            $request->signature($xml),
        );
    }
}
