<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Pipelines\CreateCertificate;

use Illuminate\Contracts\Events\Dispatcher;
use Waterfront\Domain\DNS\Events\UpdateDns;
use Waterfront\Domain\DNS\Services\Ssl\SslDnsService;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;

class UpdateDnsStep
{
    public function __construct(
        private readonly SslDnsService $sslDnsService,
        private readonly Dispatcher $eventDispatcher
    ) {
    }

    public function execute(Result $result, string $domain): void
    {
        $dnsRecords = $this->sslDnsService->getSslDnsRecords($result);

        if ($dnsRecords !== null) {
            $this->eventDispatcher->dispatch(new UpdateDns($domain, $dnsRecords));
        }
    }
}
