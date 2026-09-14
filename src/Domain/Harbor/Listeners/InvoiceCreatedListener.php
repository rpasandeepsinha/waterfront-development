<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Waterfront\Domain\Harbor\Exceptions\InvoiceLineToHarborException;
use Waterfront\Domain\Harbor\Services\HarborPropagationArbiter;
use Waterfront\Domain\Harbor\Services\HarborPropagator;
use Waterfront\Domain\Invoices\Events\InvoiceCreatedEvent;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\QueueName;

class InvoiceCreatedListener implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public string $queue = QueueName::INVOICES->value;

    /**
     * Amount of times the job retries. If a subscription isn't deployed in 12 hours we should see whats wrong.
     * In sentry. Using simple pow function to time the 12 hours. from quick tries to slower later in the flow.
     */
    public int $tries = 120;

    public function __construct(
        private readonly HarborPropagationArbiter $arbiter,
        private readonly HarborPropagator $propagator,
    ) {
    }

    /**
     * @throws InvoiceLineToHarborException
     */
    public function handle(InvoiceCreatedEvent $event): void
    {
        if ($this->arbiter->allowedToPropagate($event->invoice)->isPropagationAllowed()) {
            $this->propagator->propagate($event->invoice);

            return;
        }

        $subscription = $event->invoice->subscription;

        if (! $subscription instanceof Subscription) {
            throw InvoiceLineToHarborException::subscriptionNotFoundException(
                $event->invoice->id,
            );
        }

        $subscription->refresh();
        assert($this->job !== null);

        $domain = $subscription->domain;
        $subscriptionId = $subscription->id;
        $jobId = $this->job->getJobId();

        Log::info(sprintf(
            'Invoice line %s not dispatched. Subscription with id %d and domain %s has not been deployed successfully. Job id: %s attempt number: %d Delaying Harbor propagation...',
            $event->invoice->id,
            $subscriptionId,
            $domain,
            $jobId,
            $this->attempts(),
        ));

        if ($this->attempts() <= $this->tries) {
            $this->release($this->attempts() ** 2);
        } else {
            Log::error(sprintf(
                'Invoice %s not dispatched. Subscription with id %d and domain %s has not been deployed successfully. Reached the max amount of retries. Job id: %s',
                $event->invoice->id,
                $subscriptionId,
                $domain,
                $jobId,
            ));

            throw InvoiceLineToHarborException::subscriptionNotSuccessfullyDeployedException($event->invoice->id);
        }
    }
}
