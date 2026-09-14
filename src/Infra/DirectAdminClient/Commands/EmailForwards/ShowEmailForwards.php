<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\EmailForwards;

use GuzzleHttp\Psr7\Request;
use Waterfront\Domain\MailManagement\Interfaces\EmailForwardInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\DTO\DirectAdminEmailForward;

class ShowEmailForwards extends DirectAdminCommand
{
    protected string $command = 'CMD_API_EMAIL_FORWARDERS';

    protected string $method = 'GET';

    protected bool $useJsonResponse = true;

    /**
     * @var array<DirectAdminEmailForward>
     */
    private array $forwards;

    private string $domain;

    /**
     * @return array<int, EmailForwardInterface>
     */
    public function getForwards(): array
    {
        return $this->forwards;
    }

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
     * @param array<mixed> $decodedContent
     */
    public function responseReceived(array $decodedContent): static
    {
        $forwards = [];

        /**
         * @var string             $source
         * @var array<int, string> $destinations
         */
        foreach ($decodedContent as $source => $destinations) {
            $forwards[] = new DirectAdminEmailForward(
                source: $source,
                destinations: $destinations,
            );
        }

        $this->forwards = $forwards;

        $this->succeeded = true;

        return $this;
    }

    protected function createRequest(): Request
    {
        return new Request($this->getMethod(), $this->getUrl() . '&domain=' . $this->getDomain());
    }
}
