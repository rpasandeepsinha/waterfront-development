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
use Waterfront\Infra\DirectAdminClient\Commands\Users\ShowUserConfig;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\User;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;

#[CoversClass(ShowUserConfig::class)]
class ShowUserConfigTest extends DirectAdminTestCase
{
    private User $user;

    private ShowUserConfig $showUserConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $body = 'account=ON&additional%5Fbandwidth=%30&aftp=OFF&api%5Fwith%5Fpassword=yes&bandwidth=unlimited&catchall=OFF&cgi=OFF&creator=admin&cron=OFF&date%5Fcreated=Tue%20Oct%20%20%36%20%31%34%3A%30%32%3A%31%38%20%32%30%32%30&dnscontrol=OFF&docsroot=%2E%2Fdata%2Fskins%2Fenhanced&domain=user%31%2Ddomain%2Enl&domainptr=unlimited&email=user%31%40user%2Enl&email%5Flimit=%31%30%30%30&ftp=unlimited&inode=unlimited&ip=%31%38%35%2E%32%32%34%2E%38%38%2E%35%33&ips=%31%38%35%2E%32%32%34%2E%38%38%2E%35%33&language=en&login%5Fkeys=OFF&mysql=unlimited&name=user%31&nemailf=unlimited&nemailml=unlimited&nemailr=unlimited&nemails=unlimited&notify%5Fon%5Fall%5Fquestion%5Ffailures=yes&notify%5Fon%5Fall%5Ftwostep%5Fauth%5Ffailures=yes&ns%31=ns%31%2Eskdfhgdskfghkdsjfgh%2Esite&ns%32=ns%32%2Eskdfhgdskfghkdsjfgh%2Esite&nsubdomains=unlimited&package=&php=OFF&quota=unlimited&security%5Fquestions=no&skin=enhanced&spam=OFF&ssh=OFF&ssl=OFF&suspend%5Fat%5Flimit=OFF&suspended=no&sysinfo=OFF&twostep%5Fauth=no&username=user%31&usertype=user&vdomains=unlimited&zoom=%31%30%30';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $body),
        ]));

        $server = $this->getTestServer();
        $client = new Client(['handler' => $handlerStack]);

        // Setup config
        $this->showUserConfig = new ShowUserConfig();
        $api = new DirectAdminApi($server, $client);
        $this->user = new User($api);
    }

    #[Test]
    public function check_command_name_and_method(): void
    {
        self::assertSame('CMD_API_SHOW_USER_CONFIG', $this->showUserConfig->getCommand());
        self::assertSame('GET', $this->showUserConfig->getMethod());
    }

    #[Test]
    public function users_config_can_be_retrieved(): void
    {
        $config = $this->user->showUserConfig('user1');

        self::assertSame('user1', $config['username']);
        self::assertSame('OFF', $config['ssh']);
    }
}
