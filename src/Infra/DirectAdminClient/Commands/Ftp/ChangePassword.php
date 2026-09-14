<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Ftp;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ChangePassword extends DirectAdminCommand
{
    /**
     * @var string Command from DirectAdminApi
     */
    protected string $command = 'CMD_API_FTP';

    /**
     * @var string Method to use to call DirectAdminApi
     */
    protected string $method = 'POST';

    private string $domain;

    private string $password;

    private string $username;

    public function getPasswd(): string
    {
        return $this->password;
    }

    public function setPasswd(string $passwd): ChangePassword
    {
        $this->password = $passwd;

        return $this;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): ChangePassword
    {
        $this->domain = $domain;

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): ChangePassword
    {
        $this->username = $username;

        return $this;
    }

    protected function createRequest(): Request
    {
        return parent::createRequest()->withBody($this->getPostBody());
    }

    /**
     * Get the POST data as StreamInterface for the Request body.
     */
    private function getPostBody(): StreamInterface
    {
        $params = [
            'action' => 'modify',
            'type' => 'ftp',
            'domain' => $this->getDomain(),
            'user' => $this->getUsername(),
            'passwd' => $this->getPasswd(),
            'passwd2' => $this->getPasswd(),
        ];

        return Utils::streamFor(http_build_query($params));
    }
}
