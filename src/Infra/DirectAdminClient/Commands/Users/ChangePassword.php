<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Users;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ChangePassword extends DirectAdminCommand
{
    /**
     * @var string Command from DirectAdminApi
     */
    protected string $command = 'CMD_API_USER_PASSWD';

    /**
     * @var string Method to use to call DirectAdminApi
     */
    protected string $method = 'POST';

    private string $passwd;

    private string $username;

    /**
     * @return string The User's password. 5+ characters, ascii
     */
    public function getPasswd(): string
    {
        return $this->passwd;
    }

    /**
     * @param string $passwd The User's password. 5+ characters, ascii
     */
    public function setPasswd(string $passwd): ChangePassword
    {
        $this->passwd = $passwd;
        return $this;
    }

    /**
     * @return string The User's username. 4-8 characters, alphanumeric
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $username The User's username. 4-8 characters, alphanumeric
     */
    public function setUsername(string $username): ChangePassword
    {
        $this->username = $username;
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
     */
    private function getPostBody(): StreamInterface
    {
        $params = [
            'username' => $this->getUsername(),
            'passwd' => $this->getPasswd(),
            'passwd2' => $this->getPasswd(),
        ];

        return Utils::streamFor(http_build_query($params));
    }
}
