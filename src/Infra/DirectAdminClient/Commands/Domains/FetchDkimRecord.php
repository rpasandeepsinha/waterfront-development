<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Commands\Domains;

use GuzzleHttp\Psr7\Request;
use Psr\Log\LoggerInterface;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class FetchDkimRecord extends DirectAdminCommand
{
    protected string $command = 'CMD_DNS_ADMIN';

    protected string $method = 'GET';

    protected bool $useJsonResponse = true;

    /** @var string[] */
    private ?array $dkimRecord = null;

    public function __construct(private readonly string $domain, private readonly LoggerInterface $logger)
    {
    }

    /**
     * @param mixed[] $decodedContent
     */
    public function responseReceived(array $decodedContent): static
    {
        $records = $decodedContent['records'];
        Assert::isArray($records);

        $dkimRecords = array_filter($records, fn ($record) => $record['type'] === 'TXT' && str_contains($record['value'], 'v=DKIM1'));

        if (count($dkimRecords) === 0) {
            $this->dkimRecord = null;
            $this->succeeded = false;
            return $this;
        }

        if (count($dkimRecords) > 1) {
            $this->logger->warning(
                'Multiple dkim records found for domain {domain.name}, picking the first one.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->domain,
                ]
            );
        }

        $this->dkimRecord = current($dkimRecords);
        $this->succeeded = true;

        return $this;
    }

    /**
     * @return string[]|null
     */
    public function getDkimRecord(): ?array
    {
        return $this->dkimRecord;
    }

    protected function createRequest(): Request
    {
        return new Request($this->getMethod(), $this->getUrl() . '&domain=' . $this->domain);
    }
}
