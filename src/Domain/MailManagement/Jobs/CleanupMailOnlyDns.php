<?php

declare(strict_types=1);

namespace Waterfront\Domain\MailManagement\Jobs;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\MailManagement\Services\DnsMigrationService;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class CleanupMailOnlyDns extends AbstractQueueableJob
{
    public function __construct(
        public string $domain,
        public string $primaryHost,
        public string $fallbackHost,
    ) {
        parent::__construct();
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function handle(DnsMigrationService $migrator): void
    {
        $migrator->removeMxRecords($this->domain, $this->primaryHost, $this->fallbackHost);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::DNS;
    }
}
