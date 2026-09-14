<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\AuditLogs\Actions\StoreAuditLogAction;
use Waterfront\Domain\AuditLogs\Enums\AuditLogEvent;
use Waterfront\Domain\Domains\Exceptions\DomainDoesNotExistException;
use Waterfront\Domain\Domains\Exceptions\DomainForbiddenException;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Email\Actions\SendSubscriptionSuspendedMailAction;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionMetadataService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Exceptions\NotImplementedException;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

class SuspendDomainJob extends AbstractQueueableJob
{
    public int $tries = 3;

    public function __construct(
        private readonly DomainDeployment $domainDeployment,
        private readonly bool $sendMailAfterSuspensionSuccess,
    ) {
        parent::__construct();
    }

    public function handle(
        DomainServiceFactory $domainServiceFactory,
        SendSubscriptionSuspendedMailAction $sendSubscriptionSuspendedMailAction,
        StoreAuditLogAction $storeAuditLogAction,
        LoggerInterface $logger,
        StoreNoteAction $storeNoteAction,
    ): void {
        $subscription = $this->domainDeployment->subscription;
        $subscription->technical_status = TechnicalStatus::SUSPENDING->value;
        $subscription->suspended_at = CarbonImmutable::now();
        $subscription->save();

        $subscription->refresh();

        $logger->info(sprintf('Suspending subscription with uuid: %s', $subscription->uuid));
        try {
            $domainDriver = $domainServiceFactory->driver(
                $this->domainDeployment->provider->slug,
                $this->domainDeployment->businessUnit,
            );
            $domain = $subscription->domain;
            Assert::notNull($domain, 'Provided subscription has no domain');

            $domainDriver->suspend($domain);

            $storeAuditLogAction->execute(
                AuditLogEvent::SUSPENSION,
                DomainDeployment::class,
                $this->domainDeployment->id,
            );

            $subscription->technical_status = TechnicalStatus::SUSPENDED->value;
            $subscription->save();

            if ($this->sendMailAfterSuspensionSuccess) {
                $this->informCustomer(
                    $subscription,
                    $sendSubscriptionSuspendedMailAction,
                    $logger,
                );
            }
        } catch (NotImplementedException $exception) {
            $this->fail($exception);
        } catch (DomainModificationFailedException $exception) {
            $logger->error((string) $exception);

            if ($this->attempts() > $this->tries) {
                $this->fail($exception);
            } else {
                $this->release(60);
            }
        } catch (DomainDoesNotExistException $exception) {
            $storeNoteAction->execute($exception->getMessage(), $subscription);
        } catch (DomainForbiddenException $exception) {
            $logger->error(
                'Failed suspend for {domain.name}, RTR access forbidden for business unit.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $this->domainDeployment->provider->slug,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'business_unit_slug' => $this->domainDeployment->businessUnit?->slug,
                    ],
                ],
            );

            $this->fail($exception);
        }
    }

    public function failed(Throwable $exception): void
    {
        $storeAuditLogAction = new StoreAuditLogAction();
        $storeAuditLogAction->execute(
            AuditLogEvent::SUSPENSION,
            DomainDeployment::class,
            $this->domainDeployment->id,
            newValues: [$exception->getMessage()],
        );

        $this->domainDeployment->subscription->technical_status = TechnicalStatus::SUSPENSION_FAILED->value;
        $this->domainDeployment->subscription->save();
        $container = Container::getInstance();
        $subscriptionMetadataService = $container->make(SubscriptionMetadataService::class);
        $subscriptionMetadataService->assignCategory(
            $this->domainDeployment->subscription,
            SubscriptionCategory::SUSPENSION,
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::PARTNER_DOMAIN;
    }

    private function informCustomer(
        Subscription $subscription,
        SendSubscriptionSuspendedMailAction $sendSubscriptionSuspendedMailAction,
        LoggerInterface $logger,
    ): void {
        $logger->info(sprintf(
            'Suspension for subscription with uuid: %s successfully, informing the customer..',
            $subscription->uuid,
        ));
        $sendSubscriptionSuspendedMailAction->execute($subscription);
    }
}
