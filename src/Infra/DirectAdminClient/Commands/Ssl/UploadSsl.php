<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Ssl;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class UploadSsl extends DirectAdminCommand
{
    /**
     * @var string Command from DirectAdminApi
     */
    protected string $command = 'CMD_API_SSL';

    /**
     * @var string Method to use to call DirectAdminApi
     */
    protected string $method = 'POST';

    protected string $cert = '';

    protected string $key = '';

    protected string $domain = '';

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getCert(): string
    {
        return $this->cert;
    }

    public function setDomain(string $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function setCert(string $cert): static
    {
        $this->cert = $cert;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getFullCert(): string
    {
        return $this->getKey() . $this->getCert();
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
            'type' => 'paste',
            'domain' => $this->getDomain(),
            'certificate' => $this->getFullCert(),
        ];

        return Utils::streamFor(http_build_query($params));
    }
}
