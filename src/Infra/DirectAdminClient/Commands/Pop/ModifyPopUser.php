<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Pop;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ModifyPopUser extends DirectAdminCommand
{
    /**
     * @var string Command from DirectAdminApi
     */
    protected string $command = 'CMD_API_POP';

    /**
     * @var string Method to use to call DirectAdminApi
     */
    protected string $method = 'POST';

    private string $domain;

    private string $user;

    private string $password;

    private int $quota;

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): ModifyPopUser
    {
        $this->domain = $domain;

        return $this;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function setUser(string $user): ModifyPopUser
    {
        $this->user = $user;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): ModifyPopUser
    {
        $this->password = $password;

        return $this;
    }

    public function getQuota(): ?int
    {
        return $this->quota;
    }

    public function setQuota(int $quota): ModifyPopUser
    {
        $this->quota = $quota;

        return $this;
    }

    /**
     * Create a 'Create account' request to be send to the api.
     */
    protected function createRequest(): Request
    {
        return parent::createRequest()->withBody($this->getPostBody());
    }

    /**
     * Get the POST data as StreamInterface for the Request body.
     *
     * https://github.com/versio/visp/blob/master/private_html/customer/domains/manage.php
     */
    private function getPostBody(): StreamInterface
    {
        $params = [
            'action' => 'modify',
            'domain' => $this->getDomain(),
            'user' => $this->getUser(),
            'passwd' => $this->getPassword(),
            'passwd2' => $this->getPassword(),
            'quota' => $this->getQuota(),
        ];

        return Utils::streamFor(http_build_query($params));
    }
}
