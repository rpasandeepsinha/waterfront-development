<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Ssl;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class DisableLetsEncryptAutoRenew extends DirectAdminCommand
{
    /**
     * @var string Command from DirectAdminApi
     */
    protected string $command = 'CMD_API_SSL';

    /**
     * @var string Method to use to call DirectAdminApi
     */
    protected string $method = 'POST';

    protected string $domain = '';

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = $domain;
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
            'action' => 'save',
            'domain' => $this->getDomain(),
            'disable_letsencrypt_autorenew' => 'yes',
        ];

        return Utils::streamFor(http_build_query($params));
    }
}
