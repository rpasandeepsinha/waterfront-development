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
use Waterfront\Infra\DirectAdminClient\Commands\Users\ShowUserStats;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;

#[CoversClass(ShowUserStats::class)]
class ShowUserTest extends DirectAdminTestCase
{
    private ShowUserStats $userStats;

    protected function setUp(): void
    {
        parent::setUp();
        $json = '{ "bandwidth": "8723", "comments": "", "db_quota": "0", "domainptr": "100", "domains": { "0": { "domain": "user.nl", "bandwidth": { "limit": "shared", "usage": "0.000000" }, "quota": { "limit": "shared", "usage": "0.117413" }, "log_usage": "0.000333786", "nsubdomains": "0", "suspended": "no", "settings": { "cgi": "OFF", "domainptr": { }, "php": "OFF", "ssl": "ON" } }, "info": { "columns": { "domain": "1", "bandwidth": "2", "quota": "3", "log_usage": "4", "nsubdomains": "5", "suspended": "6", "settings": "7" }, "current_page": "1", "ipp": "50", "rows": "1", "total_pages": "1" } }, "email_deliveries": "0", "email_deliveries_incoming": "0", "email_deliveries_outgoing": "0", "email_quota": "33", "feature_sets": { "core_functions": { "checked": "no", "name": "Core Functions" }, "dns_only": { "checked": "no", "name": "DNS-Only" }, "email_only": { "checked": "no", "name": "E-Mail-Only" } }, "ftp": "1", "inode": "172", "is_reseller_skin": "0", "mysql": "0", "nemailf": "0", "nemailml": "0", "nemailr": "0", "nemails": "1", "nsubdomains": "0", "other_quota": "0", "plugins": { "all_plugins": { "backuprestore": "backuprestore", "cagefs": "cagefs", "csf": "csf", "custombuild": "custombuild", "hsts": "hsts", "lvemanager_spa": "lvemanager_spa", "nodejs_selector": "nodejs_selector", "phpselector": "phpselector", "python_selector": "python_selector", "resource_usage": "resource_usage", "softaculous": "softaculous", "spamexperts": "spamexperts", "sshwhitelist": "sshwhitelist" } }, "quota": "1235", "quota_without_system": "0.0000", "reseller_can_reset_email_count": "0", "stats": { "0": { "setting": "bandwidth", "usage": "2.67", "max_usage": "8723" }, "1": { "setting": "additional_bandwidth", "usage": "0", "max_usage": "available" }, "10": { "setting": "nemailml", "usage": "0", "max_usage": "0" }, "11": { "setting": "nemailr", "usage": "0", "max_usage": "10" }, "12": { "setting": "email_deliveries_outgoing", "usage": "0", "max_usage": "1000" }, "13": { "setting": "email_deliveries_incoming", "usage": "0", "max_usage": "" }, "14": { "setting": "max_per_email_send_limit", "usage": "", "max_usage": "200" }, "15": { "setting": "mysql", "usage": "0", "max_usage": "5" }, "16": { "setting": "domainptr", "usage": "0", "max_usage": "100" }, "17": { "setting": "ftp", "usage": "1", "max_usage": "1" }, "18": { "setting": "email", "usage": "user@user.nl", "max_usage": "<input name=email type=submit value=\"Save E-Mail\">" }, "19": { "setting": "name", "usage": "user", "max_usage": "<input name=name type=submit value=\"Save Name\">" }, "2": { "setting": "quota", "usage": "0.1523", "max_usage": "1235" }, "20": { "setting": "language", "usage": { "0": { "text": "ar", "value": "ar" }, "1": { "selected": "yes", "text": "en", "value": "en" }, "2": { "text": "es", "value": "es" }, "3": { "text": "fa", "value": "fa" }, "4": { "text": "fr", "value": "fr" }, "5": { "text": "hu", "value": "hu" }, "6": { "text": "nl", "value": "nl" }, "7": { "text": "pl", "value": "pl" }, "8": { "text": "pt_BR", "value": "pt_BR" }, "9": { "text": "ru", "value": "ru" }, "10": { "text": "sv", "value": "sv" }, "11": { "text": "tr", "value": "tr" }, "12": { "text": "uk", "value": "uk" }, "13": { "text": "zh", "value": "zh" }, "14": { "text": "zh_Hans", "value": "zh_Hans" } }, "max_usage": "<input type=submit name=language value=\"Save Language\">" }, "21": { "setting": "ip", "usage": { "0": "92.63.169.145" }, "max_usage": "" }, "22": { "setting": "ns1", "usage": "ns1.axc.nl", "max_usage": "" }, "23": { "setting": "ns2", "usage": "ns2.axc.nl", "max_usage": "" }, "24": { "setting": "ssh", "usage": "OFF", "max_usage": "" }, "25": { "setting": "ssl", "usage": "ON", "max_usage": "" }, "26": { "setting": "cgi", "usage": "ON", "max_usage": "" }, "27": { "setting": "php", "usage": "ON", "max_usage": "" }, "28": { "setting": "spam", "usage": "ON", "max_usage": "" }, "29": { "setting": "catchall", "usage": "OFF", "max_usage": "" }, "3": { "setting": "email_quota", "usage": "33 B", "max_usage": "" }, "30": { "setting": "aftp", "usage": "OFF", "max_usage": "" }, "31": { "setting": "cron", "usage": "ON", "max_usage": "" }, "32": { "setting": "sysinfo", "usage": "ON", "max_usage": "" }, "33": { "setting": "login_keys", "usage": "ON", "max_usage": "" }, "34": { "setting": "dnscontrol", "usage": "ON", "max_usage": "" }, "35": { "setting": "suspend_at_limit", "usage": "ON", "max_usage": "" }, "36": { "setting": "date_created", "usage": "Mon Jun 20 13:37:26 2022", "max_usage": "" }, "37": { "setting": "creator", "usage": "reseller", "max_usage": "" }, "38": { "setting": "suspended", "usage": "no" }, "39": { "setting": "package", "usage": "userpackage", "max_usage": "" }, "4": { "setting": "db_quota", "usage": "0 B", "max_usage": "" }, "40": { "setting": "skin", "usage": { "0": { "selected": "yes", "text": "Evolution", "value": "evolution" } }, "max_usage": "" }, "41": { "setting": "usertype", "usage": "user", "max_usage": "" }, "42": { "setting": "<input type=button onClick=\"location.href=\'/CMD_USER_HISTORY?user=user\'\" value=\'User History\'>" }, "5": { "setting": "inode", "usage": "172", "max_usage": "unlimited" }, "6": { "setting": "vdomains", "usage": "1", "max_usage": "1" }, "7": { "setting": "nsubdomains", "usage": "0", "max_usage": "10" }, "8": { "setting": "nemails", "usage": "1", "max_usage": "10" }, "9": { "setting": "nemailf", "usage": "0", "max_usage": "0" }, "info": { "columns": { "setting": "1", "usage": "2", "max_usage": "3", "extra": "4" }, "current_page": "1", "ipp": "99999", "rows": "43", "total_pages": "1" } }, "user_email": "user@user.nl, admin@install-versio-da.axc.nl", "vdomains": "1" } ';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $json),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        // Setup config
        $command = new ShowUserStats();
        $server = $this->getTestServer();
        $api = new DirectAdminApi($server, $client);

        // Call the API with ShowUserStats command
        $this->userStats = $api->call($command);
    }

    #[Test]
    public function check_command_name_and_method(): void
    {
        self::assertSame('CMD_API_SHOW_USER_USAGE', $this->userStats->getCommand());
        self::assertSame('GET', $this->userStats->getMethod());
    }

    #[Test]
    public function checkGetters(): void
    {
        $userSettings = [
            'bandwidth' => '8723',
            'nemailml' => '0',
            'nemailr' => '10',
            'mysql' => '5',
            'domainptr' => '100',
            'ftp' => '1',
            'quota' => '1235',
            'ssh' => 'OFF',
            'ssl' => 'ON',
            'cgi' => 'ON',
            'php' => 'ON',
            'spam' => 'ON',
            'cron' => 'ON',
            'sysinfo' => 'ON',
            'dnscontrol' => 'ON',
            'vdomains' => '1',
            'nsubdomains' => '10',
            'nemails' => '10',
            'nemailf' => '0',
        ];

        $domainSettings = [
            'user.nl' => [
                'php' => 'OFF',
                'ssl' => 'ON',
                'cgi' => 'OFF',
                'ubandwidth' => 'ON',
                'uquota' => 'ON',
            ],
        ];

        $stats = [
            'bandwidth' => '2.67',
            'additional_bandwidth' => '0',
            'nemailml' => '0',
            'nemailr' => '0',
            'email_deliveries_outgoing' => '0',
            'email_deliveries_incoming' => '0',
            'max_per_email_send_limit' => '',
            'mysql' => '0',
            'domainptr' => '0',
            'ftp' => '1',
            'email' => 'user@user.nl',
            'name' => 'user',
            'quota' => '0.1523',
            'language' => [
                0 => [
                    'text' => 'ar',
                    'value' => 'ar',
                ],
                1 => [
                    'selected' => 'yes',
                    'text' => 'en',
                    'value' => 'en',
                ],
                2 => [
                    'text' => 'es',
                    'value' => 'es',
                ],
                3 => [
                    'text' => 'fa',
                    'value' => 'fa',
                ],
                4 => [
                    'text' => 'fr',
                    'value' => 'fr',
                ],
                5 => [
                    'text' => 'hu',
                    'value' => 'hu',
                ],
                6 => [
                    'text' => 'nl',
                    'value' => 'nl',
                ],
                7 => [
                    'text' => 'pl',
                    'value' => 'pl',
                ],
                8 => [
                    'text' => 'pt_BR',
                    'value' => 'pt_BR',
                ],
                9 => [
                    'text' => 'ru',
                    'value' => 'ru',
                ],
                10 => [
                    'text' => 'sv',
                    'value' => 'sv',
                ],
                11 => [
                    'text' => 'tr',
                    'value' => 'tr',
                ],
                12 => [
                    'text' => 'uk',
                    'value' => 'uk',
                ],
                13 => [
                    'text' => 'zh',
                    'value' => 'zh',
                ],
                14 => [
                    'text' => 'zh_Hans',
                    'value' => 'zh_Hans',
                ],
            ],
            'ip' => [
                0 => '92.63.169.145',
            ],
            'ns1' => 'ns1.axc.nl',
            'ns2' => 'ns2.axc.nl',
            'ssh' => 'OFF',
            'ssl' => 'ON',
            'cgi' => 'ON',
            'php' => 'ON',
            'spam' => 'ON',
            'catchall' => 'OFF',
            'email_quota' => '33 B',
            'aftp' => 'OFF',
            'cron' => 'ON',
            'sysinfo' => 'ON',
            'login_keys' => 'ON',
            'dnscontrol' => 'ON',
            'suspend_at_limit' => 'ON',
            'date_created' => 'Mon Jun 20 13:37:26 2022',
            'creator' => 'reseller',
            'suspended' => 'no',
            'package' => 'userpackage',
            'db_quota' => '0 B',
            'skin' => [
                0 => [
                    'selected' => 'yes',
                    'text' => 'Evolution',
                    'value' => 'evolution',
                ],
            ],
            'usertype' => 'user',
            'inode' => '172',
            'vdomains' => '1',
            'nsubdomains' => '0',
            'nemails' => '1',
            'nemailf' => '0',
        ];

        self::assertSame('8723', $this->userStats->getBandwidth());
        self::assertSame('100', $this->userStats->getDomainPtr());
        self::assertSame($userSettings, $this->userStats->getUserSettings());
        self::assertSame($domainSettings, $this->userStats->getDomainSettings());
        self::assertSame('1235', $this->userStats->getQuota());
        self::assertSame('8723', $this->userStats->getBandwidth());
        self::assertSame($stats, $this->userStats->getStats());
    }
}
