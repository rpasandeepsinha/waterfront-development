<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Ssl;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class UploadCaCrt extends DirectAdminCommand
{
    protected string $command = 'CMD_SSL';

    protected string $method = 'POST';

    protected string $caCert = '';

    protected string $key = '';

    protected string $domain = '';

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getCaCert(): string
    {
        return $this->caCert;
    }

    public function setDomain(string $domain): UploadCaCrt
    {
        $this->domain = $domain;
        return $this;
    }

    public function setCaCert(string $caCert): UploadCaCrt
    {
        $this->caCert = $caCert;
        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): UploadCaCrt
    {
        $this->key = $key;
        return $this;
    }

    public function getFullCert(): string
    {
        return $this->getKey() . $this->getCaCert();
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
            'action'    => 'save',
            'type'      => 'cacert',
            'active'    => 'yes',
            'domain'    => $this->getDomain(),
            'cacert'    => $this->getFullCert(),
        ];

        return Utils::streamFor(http_build_query($params));
    }
}
