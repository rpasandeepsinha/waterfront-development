<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Jobs;

use Illuminate\Container\Container;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Hosting\WpToolkit\WpToolkitService;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class ReceiveWpInstallationIdJob extends AbstractQueueableJob
{
    public int $tries = 7;

    public function __construct(
        private readonly string $subscriptionUuid,
        private readonly Server $server,
    ) {
        parent::__construct();
    }

    /**
     * @return int[]
     */
    public function backoff(): array
    {
        return [
            5,
            30,
            60,
            5 * 60,
            15 * 60,
            60 * 60,
        ];
    }

    public function failed(?Throwable $throwable): void
    {
        $container = Container::getInstance();
        $logger = $container->make(LoggerInterface::class);
        $logger->error(
            'Error ReceiveWpInstallationIdJob while request the WpToolkitInstallationId job definitely failed after {queue.attempt} attempts',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscriptionUuid,
                LoggingContextKeys::QUEUE_ATTEMPT     => $this->attempts(),
                LoggingContextKeys::EXCEPTION         => $throwable,
            ]
        );

        /** @var SubscriptionRepository $subscriptionRepository */
        $subscriptionRepository = $container->make(SubscriptionRepository::class);
        $subscription = $subscriptionRepository->getByUuid($this->subscriptionUuid);

        assert($subscription instanceof Subscription);
        $subscriptionRepository->setTechnicalStatus($subscription, TechnicalStatus::FAILED->value);
    }

    public function handle(
        SubscriptionRepository $subscriptionRepository,
        HostingDeploymentRepository $deploymentRepository,
        WpToolkitService $wpToolkitService,
        LoggerInterface $logger
    ): void {
        $subscription = $subscriptionRepository->getByUuid($this->subscriptionUuid);

        assert($subscription instanceof Subscription);
        assert($subscription->domain !== null);

        $logger->debug(
            sprintf(
                'Starting ReceiveWpInstallationId Job for domain [{domain.name}]. attempt {queue.attempt}/{%d}',
                $this->tries
            ),
            [
                LoggingContextKeys::DOMAIN_NAME   => $subscription->domain,
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
            ]
        );

        $installationId = $wpToolkitService
            ->instantiateClient($this->server)
            ->getWpInstallationId($subscription->domain);

        if ($installationId === null) {
            $logger->debug(
                sprintf(
                    'No WpToolkitInstallationId found(yet) for domain [{domain.name}]. attempt {queue.attempt}/%d',
                    $this->tries
                ),
                [
                    LoggingContextKeys::DOMAIN_NAME   => $subscription->domain,
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                ]
            );

            $this->release($this->getBackoffDelay());
            return;
        }

        $logger->debug('WpToolkitInstallationId found for domain [{domain.name}]', [
            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
            LoggingContextKeys::META        => [
                'WpToolkitInstallationId' => $installationId,
            ],
        ]);

        $deployment = $subscription->hostingDeployment;
        assert($deployment instanceof HostingDeployment);

        $deploymentRepository->storeWpToolkitInstallationId(
            deployment: $deployment,
            wpToolkitInstallationId: $installationId
        );

        $subscriptionRepository->setTechnicalStatus($subscription, TechnicalStatus::OK->value);

        $logger->debug(
            'The received installationId for domain [{domain.name}]. Is stored',
            [
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                LoggingContextKeys::META        => [
                    'WpToolkitInstallationId' => $installationId,
                ],
            ]
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::HOSTING;
    }
}
