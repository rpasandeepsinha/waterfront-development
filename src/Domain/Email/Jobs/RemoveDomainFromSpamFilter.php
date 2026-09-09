<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Jobs;

use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;
use Waterfront\Domain\Hosting\Models\SpamExpertsCluster;
use Waterfront\Infra\SpamExpertsClient\Exceptions\SpamexpertsNoSuchDomainException;
use Waterfront\Infra\SpamExpertsClient\SpamExpertsClient;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class RemoveDomainFromSpamFilter extends AbstractQueueableJob
{
    public function __construct(
        private readonly string $domain,
        private readonly SpamExpertsCluster|null $spamExpertsCluster,
    ) {
        parent::__construct();
    }

    /**
     * @throws LogicException
     */
    public function handle(SpamExpertsClient $client): void
    {
        try {
            $client->removeDomain($this->domain, $this->spamExpertsCluster);
        } catch (SpamexpertsNoSuchDomainException) {
            Log::warning(sprintf(
                'Failed to remove domain %s from spam filter because domain was not found.',
                $this->domain
            ));
        } catch (Throwable $exception) {
            throw new LogicException(
                'Failed to remove domain from spam filter: ' . $exception->getMessage(),
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
