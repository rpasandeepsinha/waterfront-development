<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands\Resellers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\ShowResellerConfig;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;

#[CoversClass(ShowResellerConfig::class)]
class ShowResellerConfigTest extends DirectAdminTestCase
{
    private ShowResellerConfig $showResellerConfig;

    private DirectAdminApi $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->api = new DirectAdminApi($this->getTestServer());
        $this->showResellerConfig = new ShowResellerConfig();
    }

    #[Test]
    public function testCommandNameMethodAndResponse(): void
    {
        Assert::assertSame('CMD_API_SHOW_RESELLER_CONFIG', $this->showResellerConfig->getCommand());
        Assert::assertSame('GET', $this->showResellerConfig->getMethod());
        Assert::assertTrue($this->showResellerConfig->usesJsonResponse());
    }

    #[Test]
    public function resellerConfigCanBeRetrieved(): void
    {
        $configKeys = [
            'additional_bandwidth',
            'aftp',
            'bandwidth',
            'catchall',
            'cgi',
            'cron',
            'dns',
            'dnscontrol',
            'domainptr',
            'ftp',
            'inode',
            'ip',
            'ips',
            'login_keys',
            'mysql',
            'nemailf',
            'nemailml',
            'nemailr',
            'nemails',
            'ns1',
            'ns2',
            'nsubdomains',
            'nusers',
            'original_package',
            'oversell',
            'package',
            'php',
            'quota',
            'redis',
            'serverip',
            'spam',
            'ssh',
            'ssl',
            'subject',
            'sysinfo',
            'userssh',
            'usertype',
            'vdomains',
        ];

        $response = '{ "additional_bandwidth": "0", "aftp": "ON", "bandwidth": "unlimited", "catchall": "ON", "cgi": "ON", "cron": "ON", "dns": "OFF", "dnscontrol": "ON", "domainptr": "unlimited", "ftp": "unlimited", "inode": "unlimited", "ip": "shared", "ips": "0", "login_keys": "ON", "mysql": "unlimited", "nemailf": "unlimited", "nemailml": "unlimited", "nemailr": "unlimited", "nemails": "unlimited", "ns1": "ns1.axc.nl", "ns2": "ns2.axc.nl", "nsubdomains": "unlimited", "nusers": "unlimited", "original_package": "admin", "oversell": "ON", "package": "custom", "php": "ON", "quota": "unlimited", "redis": "OFF", "serverip": "ON", "spam": "ON", "ssh": "ON", "ssl": "ON", "subject": "Your account for |domain| is now ready for use.", "sysinfo": "ON", "userssh": "ON", "usertype": "admin", "vdomains": "unlimited" }';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $this->api = new DirectAdminApi($this->getTestServer(), $client);

        $resellerConfigCmd = $this->api->call($this->showResellerConfig);

        $resellerConfig = $resellerConfigCmd->getResellerConfig();

        foreach ($configKeys as $key) {
            Assert::assertArrayHasKey($key, $resellerConfig);
        }
    }
}
