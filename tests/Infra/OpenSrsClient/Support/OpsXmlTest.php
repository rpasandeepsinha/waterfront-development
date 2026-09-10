<?php

declare(strict_types=1);

namespace Tests\Infra\OpenSrsClient\Support;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\OpenSrsClient\Support\OpsXml;

#[CoversClass(OpsXml::class)]
class OpsXmlTest extends TestCase
{
    #[Test]
    public function itEncodesAssociativeArraysAsDtAssocAndListsAsDtArray(): void
    {
        $xml = OpsXml::encode([
            'protocol'   => 'XCP',
            'object'     => 'DOMAIN',
            'action'     => 'SW_REGISTER',
            'attributes' => [
                'domain'          => 'example.org',
                'nameserver_list' => [
                    ['name' => 'ns1.example.com', 'sortorder' => '1'],
                    ['name' => 'ns2.example.com', 'sortorder' => '2'],
                ],
            ],
        ]);

        self::assertStringContainsString('<!DOCTYPE OPS_envelope SYSTEM "ops.dtd">', $xml);
        self::assertStringContainsString('<item key="protocol">XCP</item>', $xml);
        self::assertStringContainsString('<dt_array>', $xml);
        self::assertStringContainsString('<item key="0">', $xml);
        self::assertStringContainsString('<item key="name">ns1.example.com</item>', $xml);
    }

    #[Test]
    public function itRoundTripsANestedStructure(): void
    {
        $payload = [
            'protocol'   => 'XCP',
            'object'     => 'DOMAIN',
            'action'     => 'SW_REGISTER',
            'attributes' => [
                'domain'      => 'example.org',
                'period'      => '1',
                'contact_set' => [
                    'owner' => ['first_name' => 'Owen', 'last_name' => 'Ottway'],
                ],
                'nameserver_list' => [
                    ['name' => 'ns1.example.com', 'sortorder' => '1'],
                ],
            ],
        ];

        self::assertSame($payload, OpsXml::decode(OpsXml::encode($payload)));
    }

    #[Test]
    public function itDecodesAResponseEnvelope(): void
    {
        $decoded = OpsXml::decode(
            (string) file_get_contents(__DIR__ . '/../data/opensrs_lookup_response_taken.xml')
        );

        self::assertSame('211', $decoded['response_code']);
        self::assertSame('1', $decoded['is_success']);
        self::assertSame('Premium Name', $decoded['attributes']['reason']);
    }

    #[Test]
    public function itNeverLeaksAnExternalEntityIntoTheDecodedPayload(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <!DOCTYPE OPS_envelope [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
            <OPS_envelope>
                <header><version>0.9</version></header>
                <body><data_block><dt_assoc>
                    <item key="response_text">&xxe;</item>
                </dt_assoc></data_block></body>
            </OPS_envelope>
            XML;

        try {
            $decoded = OpsXml::decode($xml);
        } catch (Exception) {
            // Rejecting the envelope outright is an acceptable outcome.
            $this->expectNotToPerformAssertions();

            return;
        }

        self::assertStringNotContainsString('root:', (string) ($decoded['response_text'] ?? ''));
    }

    #[Test]
    public function itRejectsDuplicateKeysWithinAContainer(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <OPS_envelope>
                <body><data_block><dt_assoc>
                    <item key="response_code">200</item>
                    <item key="response_code">400</item>
                </dt_assoc></data_block></body>
            </OPS_envelope>
            XML;

        $this->expectException(Exception::class);

        OpsXml::decode($xml);
    }
}
