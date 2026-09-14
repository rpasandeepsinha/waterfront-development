<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Jobs;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\AuditLogs\Actions\StoreAuditLogAction;
use Waterfront\Domain\AuditLogs\Enums\AuditLogEvent;
use Waterfront\Domain\Domains\Exceptions\DomainDoesNotExistException;
use Waterfront\Domain\Domains\Exceptions\DomainForbiddenException;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Email\Actions\SendSubscriptionUnSuspendedMailAction;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Services\SubscriptionMetadataService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Exceptions\NotImplementedException;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

class UnsuspendDomainJob extends AbstractQueueableJob
{
    public int $tries = 3;

    public function __construct(
        private readonly DomainDeployment $domainDeployment,
    ) {
        parent::__construct();
    }

    public function handle(
        DomainServiceFactory $domainServiceFactory,
        SendSubscriptionUnSuspendedMailAction $sendSubscriptionUnSuspendedMailAction,
        StoreAuditLogAction $storeAuditLogAction,
        LoggerInterface $logger,
    ): void {
        $logger->info(sprintf('Unsuspending subscription with uuid: %s', $this->domainDeployment->subscription->uuid));
        try {
            $domainDriver = $domainServiceFactory->driver(
                $this->domainDeployment->provider->slug,
                $this->domainDeployment->businessUnit,
            );

            $this->domainDeployment->subscription->update(['technical_status' => TechnicalStatus::UNSUSPENDING->value]);

            $domain = $this->domainDeployment->subscription->domain;
            Assert::notNull($domain, 'Provided subscription has no domain');

            $domainDriver->unsuspend($domain);

            $storeAuditLogAction->execute(
                AuditLogEvent::UNSUSPENSION,
                DomainDeployment::class,
                $this->domainDeployment->id,
            );

            $this->domainDeployment->subscription->technical_status = TechnicalStatus::OK->value;
            $this->domainDeployment->subscription->save();

            $logger->info(sprintf(
                'Unsuspension for subscription with uuid: %s successfully, informing the customer..',
                $this->domainDeployment->subscription->uuid,
            ));
            $sendSubscriptionUnSuspendedMailAction->execute($this->domainDeployment->subscription);
        } catch (NotImplementedException $exception) {
            $this->fail($exception);
        } catch (ModelNotFoundException|DomainModificationFailedException $exception) {
            $logger->error((string) $exception);

            if ($this->attempts() > $this->tries) {
                $this->fail($exception);
            } else {
                $this->release(60);
            }
        } catch (DomainDoesNotExistException $exception) {
            $logger->error((string) $exception);

            $this->fail($exception);
        } catch (DomainForbiddenException $exception) {
            $logger->error(
                'Failed unsuspend for {domain.name}, RTR access forbidden for business unit.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->domainDeployment->subscription->domain,
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
        $this->domainDeployment->subscription->technical_status = TechnicalStatus::UNSUSPENSION_FAILED->value;
        $this->domainDeployment->subscription->save();
        $container = Container::getInstance();
        $subscriptionMetadataService = $container->make(SubscriptionMetadataService::class);
        $subscriptionMetadataService->assignCategory(
            $this->domainDeployment->subscription,
            SubscriptionCategory::UNSUSPENSION,
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::PARTNER_DOMAIN;
    }
}
