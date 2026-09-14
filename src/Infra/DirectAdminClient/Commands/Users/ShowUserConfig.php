<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Users;

use GuzzleHttp\Psr7\Request;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ShowUserConfig extends DirectAdminCommand
{
    protected string $command = 'CMD_API_SHOW_USER_CONFIG';

    protected bool $useJsonResponse = false;

    protected bool $urlDecode = true;

    /**
     * @var string The User's username. 4-8 characters, alphanumeric
     */
    private string $user = '';

    /**
     * User config retrieved from the API.
     *
     * @var mixed[]
     */
    private array $userConfig = [];

    /**
     * @return string The User's username. 4-8 characters, alphanumeric
     */
    public function getUser(): string
    {
        return $this->user;
    }

    /**
     * @param string $username The User's username. 4-8 characters, alphanumeric
     */
    public function setUser(string $username): ShowUserConfig
    {
        $this->user = $username;

        return $this;
    }

    /**
     * Override response received.
     */
    public function responseReceived(array $decodedContent): static
    {
        $this->userConfig = $decodedContent;

        return parent::responseReceived($decodedContent);
    }

    /**
     * @return mixed[]
     */
    public function getUserConfig(): array
    {
        return $this->userConfig;
    }

    /**
     * @param mixed[] $userConfig
     */
    public function setUserConfig(array $userConfig): ShowUserConfig
    {
        $this->userConfig = $userConfig;

        return $this;
    }

    protected function createRequest(): Request
    {
        if ($this->getUser() !== '') {
            return new Request($this->getMethod(), $this->getUrl() . '?user=' . $this->getUser());
        }

        return parent::createRequest();
    }
}
