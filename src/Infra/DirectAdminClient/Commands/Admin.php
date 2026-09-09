<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

class Admin extends DirectAdminCommand
{
    /**
     * @var string Command as documented in DirectAdmin API
     */
    protected string $command = 'CMD_API_MANAGE_USER_PACKAGES';

    /**
     * @var string Method used to call API
     */
    protected string $method = 'POST';

    protected function createRequest(): Request
    {
        return parent::createRequest()->withBody($this->getPostBod());
    }

    private function getPostBod(): StreamInterface
    {
        $array = [
            'add' => 'save',
            'aftp' => 'OFF',
            'cgi' => 'ON',
            'dns' => 'OFF',
            'dnscontrol' => 'ON',
            'bandwidth' => '25600',
            'domainptr' => '0',
            'ftp' => 'unlimited',
            'ips' => '0',
            'mysql' => 'unlimited',
            'nemailf' => 'unlimited',
            'nemailml' => 'unlimited',
            'nemailr' => 'unlimited',
            'nemails' => 'unlimited',
            'nsubdomains' => 'unlimited',
            'quota' => '2048',
            'serverip' => 'ON',
            'ssh' => 'OFF',
            'userssh' => 'OFF',
            'ssl' => 'ON',
            'vdomains' => 'unlimited',
            'packagename' => 'apicreatedpackage',
        ];

        return Utils::streamFor(http_build_query($array));
    }
}
