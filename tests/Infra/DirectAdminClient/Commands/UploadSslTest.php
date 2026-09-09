<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\Ssl\UploadSsl;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;

#[CoversClass(UploadSsl::class)]
class UploadSslTest extends DirectAdminTestCase
{
    private UploadSsl $sslUpload;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup config
        $this->sslUpload = new UploadSsl();
    }

    #[Test]
    public function check_command_name_and_method(): void
    {
        self::assertSame('CMD_API_SSL', $this->sslUpload->getCommand());
        self::assertSame('POST', $this->sslUpload->getMethod());
    }

    #[Test]
    public function upload_a_certificate_for_user_domain(): void
    {
        $user = 'tester';
        $domain = 'test-domain.nl';

        $response = '{"result": "","success": "Certificate and Key Saved."}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        $data = [
            'key' => '-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC1QrLPwS1wJuH9
ivu8KiQVGVKc8pgh8IzhE3q8gSTPVG0m36bVjPLzNRTiI624b7jGyDJxi4QuNM+g
UEop2ouah+qUq4A5O12cIfe8KCmGf8bsexjPxr+VpyKRKJnH8AE+Cb77lajFEv2/
aVEShpsnJGFCepzGdkKA//EllZhrJ0wSZSlDjAW/IhAbXADyvczjF7bTyYsmObRY
1U0ziBhYoSUhZD+3ac9vr+lpyQ+m5haGqmOzD+3/g42zPoigq8TxuQ2w0AvjwOGt
rat0OX/BmwxXu9KNVa96m5NM/f9R5AI6xPnk+QmgqN6NvdEgNxjB3wHjf3CeCXky
zxV4QdalAgMBAAECggEBAKSUhStiadF1XKkMMvptAQovTfW3yC647hHH0B+s2zFt
pRYw6JjqPAZcYjPa1Xer6YiEaljypvgVd5hGjrBmAXA0jOikt+4/WwXTSc+MX/gB
uSsrsiGmgnptoVNQHCGQaHBeBQ0GnJEkZ0YPaE977RCjVbQ5BHSnGEdtHRZVOGnB
Yh4OsyuBh7PNwkNMq4x3XQtASks1zHYHSBVOsLzmwBvGlOlRomrGn32Aqnux2btT
7/6MlcQ68lbqw1ErnzK/cw6dsFmSqisquJbNHeMrO46FWmL8C4ujqDik4D29ayUN
Z9FX2aOM1oUsS7uU3OiZNeou74hL+muqisUpQILY6AECgYEA6ktKfgO/XjUtsh1i
OsKmhJnoyUQuNvNT6idC6AFh15WoCb5pcblPjO3FG8rlq6crAm8bBrZX36czQ92X
0C7WOc7vW8ljRHBdluFlSbvbO1K21PBiCCNViKfZ/50YUc5VtuYwAMwnBex7CHgq
Cxh0hKoLV1BLC82sWDWGixrY5aUCgYEAxg2e10sIkZeuN4+xJIcZ3aA523OKHzlB
2GwjG9RxjWfEDy2Ani+frMtYNJFr2MClefADQpYIn4GvtSwEEv5PzRHD/qxykiGi
wfNdOgckqvhddDU8+RfTV3qEAGpXreYoRVR/T+YAh0DEiwfVdKm3R2yDmNqNdgyz
YkMN9MFMXQECgYBVsF25QuOdr/NbflWryf8e5i92VOJWJJ5fOCbHNaI0N77yeVqV
RkIq99csOAPRyNz5Eeufg9cVrFAalRPuBwNAt0dhmYEdyb7g7OSfl/4xbyoBLT2d
XlbtGP6o9yqq2L0OnJeX4xKunvPMgC5YSoRq9MobD/mygnFy/XiMrbAAJQKBgGC8
AltRsMu79EH7EyCuRDn1qoy/cDUz+C9HEhbjutrAVgi7xth8llcFsv1qEez6m1hl
nJIHSgrugu3Qo+TLBhs5lCtt+z/Y4fAtd9mB560CRlMeNbvMoVNW6eZyCoVLp1vF
m7Fgu91UCyuFFgM3aeee3t/nz7RbG7rg2Y40Y6IBAoGBANQ75rX9dKSC8O9R5ezP
9v0e/Ve19y7LcVHmsFgeC8xOGH5CoFEhpl89mwVQ2dy6X4H2s5KNI7ij6A7hpoQM
J7pxKiSDwfri0cGVoH1xau2XqiZZ7bTI6AP1TGEk7Bb7ufgH9q4/+gI+sE7lkzXQ
kaARvK4YeQqVSOCtjC6TF1f/
-----END PRIVATE KEY-----',
            'cert' => '-----BEGIN CERTIFICATE-----
MIIF0TCCBLmgAwIBAgIQQ3f7rhiguSMHP1zuQ/k/nzANBgkqhkiG9w0BAQsFADCB
jzELMAkGA1UEBhMCR0IxGzAZBgNVBAgTEkdyZWF0ZXIgTWFuY2hlc3RlcjEQMA4G
A1UEBxMHU2FsZm9yZDEYMBYGA1UEChMPU2VjdGlnbyBMaW1pdGVkMTcwNQYDVQQD
Ey5TZWN0aWdvIFJTQSBEb21haW4gVmFsaWRhdGlvbiBTZWN1cmUgU2VydmVyIENB
MB4XDTIwMDUxOTAwMDAwMFoXDTIxMDUyMDIzNTk1OVowHzEdMBsGA1UEAxMUc3Rv
cmV0ZXN0ZXJkZXRlc3QubmwwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIB
AQC1QrLPwS1wJuH9ivu8KiQVGVKc8pgh8IzhE3q8gSTPVG0m36bVjPLzNRTiI624
b7jGyDJxi4QuNM+gUEop2ouah+qUq4A5O12cIfe8KCmGf8bsexjPxr+VpyKRKJnH
8AE+Cb77lajFEv2/aVEShpsnJGFCepzGdkKA//EllZhrJ0wSZSlDjAW/IhAbXADy
vczjF7bTyYsmObRY1U0ziBhYoSUhZD+3ac9vr+lpyQ+m5haGqmOzD+3/g42zPoig
q8TxuQ2w0AvjwOGtrat0OX/BmwxXu9KNVa96m5NM/f9R5AI6xPnk+QmgqN6NvdEg
NxjB3wHjf3CeCXkyzxV4QdalAgMBAAGjggKWMIICkjAfBgNVHSMEGDAWgBSNjF7E
VK2K4Xfpm/mbBeG4AY1h4TAdBgNVHQ4EFgQUuEHklqo7ScL2mBE6gCKxGYXakQgw
DgYDVR0PAQH/BAQDAgWgMAwGA1UdEwEB/wQCMAAwHQYDVR0lBBYwFAYIKwYBBQUH
AwEGCCsGAQUFBwMCMEkGA1UdIARCMEAwNAYLKwYBBAGyMQECAgcwJTAjBggrBgEF
BQcCARYXaHR0cHM6Ly9zZWN0aWdvLmNvbS9DUFMwCAYGZ4EMAQIBMIGEBggrBgEF
BQcBAQR4MHYwTwYIKwYBBQUHMAKGQ2h0dHA6Ly9jcnQuc2VjdGlnby5jb20vU2Vj
dGlnb1JTQURvbWFpblZhbGlkYXRpb25TZWN1cmVTZXJ2ZXJDQS5jcnQwIwYIKwYB
BQUHMAGGF2h0dHA6Ly9vY3NwLnNlY3RpZ28uY29tMDkGA1UdEQQyMDCCFHN0b3Jl
dGVzdGVyZGV0ZXN0Lm5sghh3d3cuc3RvcmV0ZXN0ZXJkZXRlc3QubmwwggEEBgor
BgEEAdZ5AgQCBIH1BIHyAPAAdgB9PvL4j/+IVWgkwsDKnlKJeSvFDngJfy5ql2iZ
fiLw1wAAAXIs9dTDAAAEAwBHMEUCIHPGLh6pwvH/s7WT0Wx8Z5Nx5LvQRpRWZygs
C7+TPsshAiEA2PMePM/HN+cdE0K+8ERqjmWyQI+XVtob4yPCNgvxBNEAdgCUILwe
jtWNbIhzH4KLIiwN0dpNXmxPlD1h204vWE2iwgAAAXIs9dTrAAAEAwBHMEUCIQCJ
YLUnoMUB5yLImT6ZSFee+jvu9QNxoK3Y+adT1TDkNAIgOp77BGAY8IhWUeEpG8kI
WAfNsZe+d/dQd66s44xYRRswDQYJKoZIhvcNAQELBQADggEBAMiDTous7E49GlTE
zQzuIpiEhzc5K0FhdgYX3GF+QQ9g8Wu00wbmsMxMDj0xKH/LBH47/gS0GL4Rgum1
L28eI/WlJIHdur+Imo7RVCAM87cTddGMosJv9O5Z4y/2tYPdGZZ9wqKWNppn6ryC
AyJ92FlRWgnjhnRlj7nV4ws7xIDkR9vC2QfSKVRe+izNpn869z12sLa7e31RluxZ
9VjWnGlL1XP2/s+Ya0fsFeXwD3VlZgI7l5m8DAbhrNzucrKTJV365YecUrgakBC1
2Eksv24PJ5wKu/9AXHs+uatZ20CVMKmUnfWyavjR9CXmEEnOo00LSRIXUaGc1sos
xVRNbkk=
-----END CERTIFICATE-----',
        ];

        $this->sslUpload
            ->setCert($data['cert'])
            ->setKey($data['key'])
            ->setDomain($domain);

        $certificateCreated = $api->loginAs($user)->call($this->sslUpload);

        $success = $certificateCreated->getFormValues()['success'];
        assert(is_string($success));

        self::assertStringContainsString('Certificate and Key Saved', $success);
    }
}
