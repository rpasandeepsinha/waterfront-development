<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Domains;

use GuzzleHttp\Psr7\Request;
use JsonException;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

class ShowDomain extends DirectAdminCommand
{
    protected string $command = 'CMD_ADDITIONAL_DOMAINS';

    protected string $method = 'GET';

    protected bool $useJsonResponse = true;

    /**
     * @var string[]
     */
    private array $domainData = [
        'action' => 'view',
    ];

    /**
     * @var mixed[][] domain data retrieved from DA
     */
    private array $showDomainData = [];

    /**
     *
     * @param string $domain The domain to retrieve settings for.
     */
    public function setDomain(string $domain): ShowDomain
    {
        $this->domainData['domain'] = $domain;
        return $this;
    }

    /**
     * @param mixed[] $decodedContent
     *
     * @throws DirectAdminCommandException|JsonException
     */
    public function responseReceived(array $decodedContent): static
    {
        if (! array_key_exists($this->domainData['domain'], $decodedContent) || $decodedContent[$this->domainData['domain']] === null) {
            throw new DirectAdminCommandException(
                sprintf(
                    'Cannot retrieve domain data for domain %s. Response is missing the data; %s',
                    $this->domainData['domain'],
                    json_encode($decodedContent, JSON_THROW_ON_ERROR)
                )
            );
        }

        $domainData = $decodedContent[$this->domainData['domain']];
        assert(is_array($domainData));

        $this->showDomainData = $domainData;

        $this->succeeded = true;

        return $this;
    }

    /**
     * @return array<mixed, mixed>
     */
    public function getDomainData(): array
    {
        return $this->showDomainData;
    }

    /**
     * @throws DirectAdminCommandException
     */
    protected function createRequest(): Request
    {
        if (! array_key_exists('domain', $this->domainData)) {
            throw new DirectAdminCommandException('Cannot call ShowDomain without domain set.');
        }

        return new Request($this->getMethod(), $this->getUrl() . '&domain=' . $this->domainData['domain']);
    }
}
