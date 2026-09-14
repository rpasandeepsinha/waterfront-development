<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Domains;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class EnableDisableDKIM extends DirectAdminCommand
{
    protected string $command = 'CMD_API_EMAIL_POP';

    protected string $method = 'POST';

    protected bool $useJsonResponse = true;

    /**
     * @var string[]
     */
    private array $domainData;

    public function __construct(
        private readonly string $domain,
        private readonly bool $dkim,
    ) {
        $this->domainData['domain'] = $this->domain;
        $dkimValue = $this->dkim ? 'enable' : 'disable';
        $this->domainData[$dkimValue] = $dkimValue;
    }

    protected function createRequest(): Request
    {
        return parent::createRequest()->withBody($this->getPostBody());
    }

    private function getPostBody(): StreamInterface
    {
        return Utils::streamFor(http_build_query($this->domainData));
    }
}
