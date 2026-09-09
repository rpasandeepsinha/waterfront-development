<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Jobs;

use RuntimeException;
use Throwable;
use Waterfront\Domain\Hosting\Models\SpamExpertsCluster;
use Waterfront\Infra\SpamExpertsClient\SpamExpertsClient;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class AddDomainToSpamFilter extends AbstractQueueableJob
{
    public function __construct(
        private readonly string $domain,
        private readonly SpamExpertsCluster|null $spamExpertsCluster,
    ) {
        parent::__construct();
    }

    public function handle(SpamExpertsClient $client): void
    {
        try {
            $client->addDomain($this->domain, $this->spamExpertsCluster);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Failed to add domain to spam filter: ' . $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::HOSTING;
    }
}
