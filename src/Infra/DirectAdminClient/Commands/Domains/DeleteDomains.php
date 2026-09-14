<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Domains;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class DeleteDomains extends DirectAdminCommand
{
    protected string $command = 'CMD_API_DOMAIN';

    protected string $method = 'POST';

    protected bool $useJsonResponse = true;

    /**
     * @var array<string,string>
     */
    private array $requestData = [
        'delete_data' => 'yes',
        'confirmed' => 'Confirm',
        'delete_data_aware' => 'yes',
        'delete' => 'yes',
    ];

    /**
     * @var string[]
     */
    private array $domains = [];

    /**
     * Set the array with domains to delete.
     *
     * @param string[] $domains
     */
    public function setDomains(array $domains): DeleteDomains
    {
        $this->domains = $domains;

        return $this;
    }

    /**
     * Add a domain to delete.
     */
    public function addDomain(string $domain): DeleteDomains
    {
        $this->domains[] = $domain;

        return $this;
    }

    /**
     * @param mixed[] $decodedContent
     */
    public function responseReceived(array $decodedContent): static
    {
        if (array_key_exists('success', $decodedContent) && $decodedContent['success'] === 'Domain Deletion Results') {
            $this->setSucceeded(true);
        }

        return $this;
    }

    /**
     * Create a 'Modify Domain' request to be sent to the api.
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
        $params = $this->requestData;

        foreach ($this->domains as $index => $domain) {
            $params['select' . $index] = $domain;
        }

        return Utils::streamFor(http_build_query($params));
    }
}
