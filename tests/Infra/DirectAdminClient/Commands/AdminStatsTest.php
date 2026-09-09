<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\AdminStats;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;

#[CoversClass(AdminStats::class)]
class AdminStatsTest extends DirectAdminTestCase
{
    private AdminStats|DirectAdminCommand $adminStats;

    protected function setUp(): void
    {
        parent::setUp();
        $json = '{"RX":"119.53 GB","TX":"15.65 GB","allocated":{"bandwidth":"unlimited","domainptr":"unlimited","ftp":"unlimited","inode":"unlimited","mysql":"200","nemailf":"unlimited","nemailml":"unlimited","nemailr":"unlimited","nemails":"unlimited","nsubdomains":"unlimited","quota":"unlimited","vdomains":"unlimited"},"bandwidth":"2.0175","db_quota":"0","device":"eth0","disk":{"info":{"columns":{"Filesystem":"1","1024-blocks":"2","Used":"3","Available":"4","Capacity":"5","Mounted on":"6"},"current_page":"1","ipp":"50","rows":"0","total_pages":"0"}},"disk1":"devtmpfs:497236:0:497236:0%:\/dev","disk2":"tmpfs:508084:0:508084:0%:\/dev\/shm","disk3":"tmpfs:508084:51184:456900:11%:\/run","disk4":"tmpfs:508084:0:508084:0%:\/sys\/fs\/cgroup","disk5":"\/dev\/sda1:41931756:5515996:36415760:14%:\/","disk6":"tmpfs:101620:0:101620:0%:\/run\/user\/0","domainptr":"0","email_deliveries":"0","email_deliveries_incoming":"0","email_deliveries_outgoing":"0","email_quota":"4320","ftp":"2","inode":"67","last_tally":"1599516662","loadavg":{"fifteen":"0.05","five":"0.01","one":"0.000000"},"mysql":"0","nemailf":"0","nemailml":"0","nemailr":"0","nemails":"2","nresellers":"0","nsubdomains":"0","nusers":"1","other_quota":"0","quota":"0.1171","usage":{"bandwidth":"2.0175","db_quota":"0","domainptr":"0","email_deliveries":"0","email_deliveries_incoming":"0","email_deliveries_outgoing":"0","email_quota":"4320","ftp":"2","inode":"67","last_tally":"1599516662","mysql":"0","nemailf":"0","nemailml":"0","nemailr":"0","nemails":"2","nresellers":"0","nsubdomains":"0","nusers":"1","other_quota":"0","quota":"0.1171","vdomains":"1"},"vdomains":"1"}';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $json),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        // Setup config
        $command = new AdminStats();
        $server = $this->getTestServer();
        $api = new DirectAdminApi($server, $client);

        // Call the API with AdminStats command
        $this->adminStats = $api->call($command);
    }

    #[Test]
    public function check_command_name_and_method(): void
    {
        self::assertSame('CMD_API_ADMIN_STATS', $this->adminStats->getCommand());
        self::assertSame('GET', $this->adminStats->getMethod());
    }

    #[Test]
    public function check_form_values_with_object_getters(): void
    {
        $formValues = $this->adminStats->getFormValues();

        foreach ($formValues as $property => $value) {
            $getMethod = Str::camel('get_' . $this->adminStats->caseProperty($property));
            $callable = [$this->adminStats, $getMethod];
            if (is_callable($callable)) {
                self::assertEquals($value, call_user_func($callable));
            }
        }
    }
}
