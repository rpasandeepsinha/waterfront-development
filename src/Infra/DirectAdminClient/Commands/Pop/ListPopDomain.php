<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Pop;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ListPopDomain extends DirectAdminCommand
{
    /**
     * @var string Command from DirectAdminApi
     */
    protected string $command = 'CMD_API_POP';

    /**
     * @var string Method to use to call DirectAdminApi
     */
    protected string $method = 'POST';

    private ?string $domain = null;

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): ListPopDomain
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * @param mixed[] $users
     */
    public function setUsers(array $users): ListPopDomain
    {
        $this->formValues = $users;

        return $this;
    }

    /**
     * @return mixed[]
     */
    public function getUsers(): array
    {
        return $this->formValues;
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
     *
     *   action	create
     *   domain	the domain to be shown eg: domain.com
     *   user	email user eg: bob
     *   passwd	the password for the account
     *   passwd2	password confirmation
     *   quota	Integer in Megabytes. Zero for unlimited, 1+ for number of Megabytes.
     *   limit	Send Limit. Zero for unlimited. Blank defaults to the system's default.
     */
    private function getPostBody(): StreamInterface
    {
        $params = [
            'action'	=> 'list',
            'domain'	=> $this->getDomain(),
        ];

        return Utils::streamFor(http_build_query($params));
    }
}
