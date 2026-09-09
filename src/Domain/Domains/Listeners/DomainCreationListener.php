<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\RegistrationResult;
use Waterfront\Domain\Domains\DTO\TransferResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Events\CreateDomain;
use Waterfront\Domain\Domains\Mailers\MailDomainCreationFailed;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Services\RtrErrorParseService;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

class DomainCreationListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = QueueName::PARTNER_DOMAIN->value;

    public function __construct(
        private readonly DomainService $domainService,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly RtrErrorParseService $rtrErrorParseService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function failed(CreateDomain $event, Throwable $throwable): void
    {
        $subscription = $event->subscription;
        $subscription->refresh();
        $subscription->technical_status = TechnicalStatus::FAILED->value;
        $subscription->save();

        $this->sendTransferFailedMail($subscription);
    }

    /**
     * @throws LogicException
     */
    public function handle(CreateDomain $event): void
    {
        $this->logger->info(
            'Initializing domain creation for subscription: {subscription.uuid} with domain: {domain.name}',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $event->subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $event->domain,
            ]
        );

        $this->setSubscriptionTechnicalStateOnPending($event->subscription);

        $this->createDomain($event);
    }

    private function createDomain(CreateDomain $event): void
    {
        $subscription = $event->subscription;
        $domainDeployment = $event->domainDeployment;
        $transferSecret = $event->getTransferSecret();
        $product = $subscription->product;
        $isTransfer = $transferSecret !== null;

        if ($this->domainService->registrationRequiresDnsBeforeSubmission($event->domain)) {
            /*
             * If the domain has a zone check specification with the provider we can't register the domain just yet.
             * We need to ensure we have successfully provisioned the DNS zone and nameservers first. Registering
             * the domain will be triggered by the DnsCreationListener when it has successfully created the zone.
             */
            $this->logger->debug(
                'Domain tld [{product.slug}] has zone check enabled. Skipping minimal transfer/registration.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::PRODUCT_SLUG => $product->slug,
                    LoggingContextKeys::PROVISIONING_TYPE => $isTransfer ? 'domain.transfer' : 'domain.registration',
                ]
            );
            return;
        }

        $this->logger->debug('DomainCreationListener started for domain {domain.name}. Registration or transfer: {provisioning.type}', [
            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            LoggingContextKeys::PROVISIONING_TYPE => $isTransfer ? 'domain.transfer' : 'domain.registration',
        ]);

        if ($event->getTransferSecret() === 'deferred_transfer') {
            $subscription = $event->subscription;
            $subscription->technical_status = TechnicalStatus::TRANSFER_FAILED->value;
            $subscription->save();

            return;
        }

        $result = $isTransfer
            ? $this->domainService->minimalTransfer($domainDeployment)
            : $this->domainService->minimalRegister($domainDeployment);

        $subscription->technical_status = $this->getResultStatus($result);
        $subscription->save();

        $domainDeployment->update([
            'last_result' => $result->getExceptionMessage() ?? $result->getReason(),
            'last_result_received' => CarbonImmutable::now(),
        ]);

        if (in_array($subscription->technical_status, [DomainStatus::FAILED->value, TechnicalStatus::FAILED->value], true)) {
            $this->logger->warning(
                sprintf(
                    'Domain registration status: %s. Reason: %s',
                    $subscription->technical_status,
                    $result->getReason()
                ),
                [
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::PROVISIONING_ID => $subscription->domainDeployment?->id,
                ]
            );
        }
    }

    private function setSubscriptionTechnicalStateOnPending(Subscription $subscription): void
    {
        $subscription->technical_status = TechnicalStatus::PENDING->value;
        $subscription->save();
    }

    private function sendTransferFailedMail(Subscription $subscription): void
    {
        if (in_array($subscription->technical_status, [DomainStatus::FAILED->value, TechnicalStatus::FAILED->value], true)) {
            $reason = ! is_null($subscription->domainDeployment?->last_result)
                ? $this->rtrErrorParseService->getTranslatedRtrError($subscription->domainDeployment->last_result)
                : $this->translator->translate('domain-register-transfer-failed-unknown-reason');

            $this->mailer->send(
                [$subscription->customer],
                new MailDomainCreationFailed(
                    $subscription->domain ?? '-',
                    $reason
                )
            );
        }
    }

    /**
     *  This is a workaround for the fact that the status can be either a string or an enum
     *  The reason these are different is that the TransferResult->status is currently not
     *  an enum. TODO remove this when TechnicalStatusEnum is converted to enum (WATER-4189).
     */
    private function getResultStatus(TransferResult|RegistrationResult $result): string
    {
        if ($result instanceof RegistrationResult) {
            $status = $result->getStatus();

            if ($status === DomainStatus::PENDING) {
                return TechnicalStatus::PENDING->value;
            }

            return $status->value;
        }

        return $result->getStatus();
    }
}
