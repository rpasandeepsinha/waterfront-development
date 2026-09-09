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

class ConfigureMailOnlyDns extends AbstractQueueableJob
{
    public function __construct(
        public string $domain,
        public string $primaryHost,
        public string $fallbackHost,
        public string $mailRecordIp
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
        $migrator->setMxRecords($this->domain, $this->primaryHost, $this->fallbackHost);
        $migrator->setARecord($this->domain, $this->mailRecordIp);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::DNS;
    }
}
