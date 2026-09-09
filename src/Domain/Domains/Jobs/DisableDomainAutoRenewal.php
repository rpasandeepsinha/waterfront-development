<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Jobs;

use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Exceptions\DisableAutorenewalFailedException;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class DisableDomainAutoRenewal extends AbstractQueueableJob
{
    public function __construct(private readonly DomainDeployment $domainDeployment)
    {
        parent::__construct();
    }

    public function handle(DomainService $domainService): void
    {
        try {
            $domainService->disableAutoRenewal($this->domainDeployment);
        } catch (DisableAutorenewalFailedException) {
            // @ignoreException
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::PARTNER_DOMAIN;
    }
}
