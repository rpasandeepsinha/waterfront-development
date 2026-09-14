<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Domains;

use GuzzleHttp\Psr7\Request;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

class GetEmail extends DirectAdminCommand
{
    protected string $command = 'CMD_API_EMAIL_POP';

    protected string $method = 'GET';

    protected bool $useJsonResponse = true;

    private bool $dkimEnabled;

    private int $storage = 0;

    public function __construct(
        private readonly string $domain,
    ) {
    }

    /**
     * @param mixed[] $decodedContent
     */
    public function responseReceived(array $decodedContent): static
    {
        if (! array_key_exists('DKIM_ENABLED', $decodedContent)) {
            throw new DirectAdminCommandException(
                sprintf(
                    'Cannot retrieve dkim enabled for domain %s. Response is missing the data; %s',
                    'DKIM_ENABLED',
                    json_encode($decodedContent, JSON_THROW_ON_ERROR),
                ),
            );
        }

        $this->dkimEnabled = (bool) $decodedContent['DKIM_ENABLED'];

        $this->storage = (int) array_sum((array) data_get($decodedContent, 'emails.*.usage.usage', [0]));

        $this->succeeded = true;

        return $this;
    }

    public function getDkimEnabled(): bool
    {
        return $this->dkimEnabled;
    }

    public function getStorage(): int
    {
        return $this->storage;
    }

    protected function createRequest(): Request
    {
        return new Request($this->getMethod(), $this->getUrl() . '&domain=' . $this->domain);
    }
}
