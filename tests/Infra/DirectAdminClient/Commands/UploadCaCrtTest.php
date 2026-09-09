<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Testing\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\Ssl\UploadCaCrt;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(UploadCaCrt::class)]
class UploadCaCrtTest extends DirectAdminTestCase
{
    private UploadCaCrt $uploadCaCrt;

    private DirectAdminApi $api;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uploadCaCrt = new UploadCaCrt();
    }

    #[Test]
    public function checkCommandNameAndMethod(): void
    {
        Assert::assertSame('CMD_SSL', $this->uploadCaCrt->getCommand());
        Assert::assertSame('POST', $this->uploadCaCrt->getMethod());
    }

    #[Test]
    public function uploadCaCrtSucces(): void
    {
        $domain = 'ssltestfromtest.nl';
        $user = 'dummy';

        $response = '{"result": "CA Certificate is ok. Your site should be secure within a few minutes.\n",
    	                  "success": "Success"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->uploadCaCrt
            ->setDomain($domain)
            ->setCaCert($this->getCertificateData());
        $response = $this->api->loginAs($user)->call($this->uploadCaCrt);

        Assert::assertStringContainsString(
            'CA Certificate is ok. Your site should be secure within a few minutes.',
            $response->getResult()
        );
    }

    #[Test]
    public function uploadCaCrtInvalidCrt(): void
    {
        $domain = 'ssltestfromtest1.nl';
        $user = 'dummy1';

        $response = '{"error": "Could not execute your request","result": "No certificates found\nUnable to find CA certificate\n"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->expectException(DirectAdminCommandException::class);

        $this->uploadCaCrt
            ->setDomain($domain)
            ->setCaCert('Invalid Certficate string');
        $this->api->loginAs($user)->call($this->uploadCaCrt);
    }

    #[Test]
    public function uploadCaCrtSslOff(): void
    {
        $domain = 'ssltestfromtest1.nl';
        $user = 'dummy2';

        $response = '{"error": "Could not execute your request","result": "SSL is not enabled for this domain"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $this->expectException(DirectAdminCommandException::class);

        $this->uploadCaCrt
            ->setDomain($domain)
            ->setCaCert($this->getCertificateData());
        $this->api->loginAs($user)->call($this->uploadCaCrt);
    }

    private function getCertificateData(): string
    {
        return '-----BEGIN CERTIFICATE-----
MIIE9TCCA92gAwIBAgIETA6MOTANBgkqhkiG9w0BAQUFADCBtDEUMBIGA1UEChML
RW50cnVzdC5uZXQxQDA+BgNVBAsUN3d3dy5lbnRydXN0Lm5ldC9DUFNfMjA0OCBp
bmNvcnAuIGJ5IHJlZi4gKGxpbWl0cyBsaWFiLikxJTAjBgNVBAsTHChjKSAxOTk5
IEVudHJ1c3QubmV0IExpbWl0ZWQxMzAxBgNVBAMTKkVudHJ1c3QubmV0IENlcnRp
ZmljYXRpb24gQXV0aG9yaXR5ICgyMDQ4KTAeFw0xMTExMTExNTQwNDBaFw0yMTEx
MTIwMjUxMTdaMIGxMQswCQYDVQQGEwJVUzEWMBQGA1UEChMNRW50cnVzdCwgSW5j
LjE5MDcGA1UECxMwd3d3LmVudHJ1c3QubmV0L3JwYSBpcyBpbmNvcnBvcmF0ZWQg
YnkgcmVmZXJlbmNlMR8wHQYDVQQLExYoYykgMjAwOSBFbnRydXN0LCBJbmMuMS4w
LAYDVQQDEyVFbnRydXN0IENlcnRpZmljYXRpb24gQXV0aG9yaXR5IC0gTDFDMIIB
IjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAl6MtPJ7eBdoTwhGNnY7jf8dL
flqfs/9iq3PIKGu6EGSChxPNVxj/KM7A5g4GkVApg9Hywyrb2NtOBMwA64u2lty8
qvpSdwTB2xnkrpz9PIsD7028GgNl+cGxP3KG8jiqGa4QiHgo2nXDPQKCApy5wWV3
diRMmPdtMTj72/7bNwJ2oRiXpszeIAlJNiRpQvbkN2LxWW2pPO00nKOO29w61/cK
b+8u2NWTWnrtCElo4kHjWpDBhlX8UUOd4LLEZ7TLMjEl8FSfS9Fv29Td/K9ebHiQ
ld7KOki5eTybGdZ1BaD5iNfB6KUJ5BoV3IcjqrJ1jGMlh9j4PabCzGb/pWZoVQID
AQABo4IBDjCCAQowDgYDVR0PAQH/BAQDAgEGMBIGA1UdEwEB/wQIMAYBAf8CAQAw
MwYIKwYBBQUHAQEEJzAlMCMGCCsGAQUFBzABhhdodHRwOi8vb2NzcC5lbnRydXN0
Lm5ldDAyBgNVHR8EKzApMCegJaAjhiFodHRwOi8vY3JsLmVudHJ1c3QubmV0LzIw
NDhjYS5jcmwwOwYDVR0gBDQwMjAwBgRVHSAAMCgwJgYIKwYBBQUHAgEWGmh0dHA6
Ly93d3cuZW50cnVzdC5uZXQvcnBhMB0GA1UdDgQWBBQe8auJBvhJDwEzd+4Ueu4Z
fJMoTTAfBgNVHSMEGDAWgBRV5IHREYC+2Im5CKMx+aEkCRa5cDANBgkqhkiG9w0B
AQUFAAOCAQEAQJqHfojUzCanS/p4SiDV+aI2IbvuW6BPRI3PqvmXF5aEqchnm7vm
EN551lZqpHgUSdl87TBeaeptJEZaiDQ9JifPaUGEHATaGTgu24lBOX5lH51aOszh
DEw3oc5gk6i1jMo/uitdTBuBiXrKNjCc/4Tj/jrx93lxybXTMwPKd86wuinSNF1z
/6T98iW4NUV5eh+Xrsm+CmiEmXQ5qE56JvXN3iXiN4VlB6fKxQW3EzgNLfBtGc7e
mWEn7kVuxzn/9sWL4Mt8ih7VegcxKlJcOlAZOKlE+jyoz+95nWrZ5S6hjyko1+yq
wfsm5p9GJKaxB825DOgNghYAHZaS/KYIoA==
-----END CERTIFICATE-----
';
    }
}
