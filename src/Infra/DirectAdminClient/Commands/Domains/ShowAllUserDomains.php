<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Domains;

use GuzzleHttp\Psr7\Request;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;

class ShowAllUserDomains extends DirectAdminCommand
{
    protected string $command = 'CMD_API_SHOW_DOMAINS';

    protected string $method = 'GET';

    protected bool $useJsonResponse = true;

    /**
     * @var mixed[] domain data retrieved from DA
     */
    private array $showDomainData = [];

    public function __construct(
        private readonly string $user,
    ) {
    }

    /**
     * @param mixed[] $decodedContent
     */
    public function responseReceived(array $decodedContent): static
    {
        $this->showDomainData = $decodedContent;

        $this->succeeded = true;

        return $this;
    }

    /**
     * @return mixed[]
     */
    public function getDomainData(): array
    {
        return $this->showDomainData;
    }

    protected function createRequest(): Request
    {
        return new Request($this->getMethod(), $this->getUrl() . '&user=' . $this->user);
    }
}
