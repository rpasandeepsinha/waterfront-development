<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\RegistrationResult;
use Waterfront\Domain\Domains\DTO\TransferResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Mailers\MailDomainCreationFailed;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Provision\DomainNames\Exceptions\RegisterDomainNameException;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\RtrClient\Services\RtrErrorParseService;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

class RegisterDomainNameJob extends AbstractQueueableJob
{
    public int $tries = 1;

    public string $resultMessage = 'Unknown result';

    public bool $isTransfer;

    public Subscription $domainSubscription;

    public function __construct(
        private readonly DomainDeployment $domainDeployment,
    ) {
        $this->isTransfer = $this->domainDeployment->transfer_secret !== null;
        $this->domainSubscription = $this->domainDeployment->subscription;
        parent::__construct();
    }

    public function handle(
        DomainService $domainService,
        SubscriptionRepository $subscriptionRepository,
        LoggerInterface $logger,
    ): void {
        if ($this->domainSubscription->domain === null) {
            $this->fail(new RegisterDomainNameException(
                sprintf(
                    'Error registering domain: Domain is missing from Subscription (%s)',
                    $this->domainSubscription->uuid,
                ),
            ));

            return;
        }

        $registerOrTransfer = $this->isTransfer ? 'domain.transfer' : 'domain.registration';
        $logger->info(
            sprintf(
                'RegisterDomainJob started for domain %s. Registration or transfer: %s',
                $this->domainSubscription->domain,
                $registerOrTransfer,
            ),
            [
                LoggingContextKeys::DOMAIN_NAME => $this->domainSubscription->domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->domainSubscription->uuid,
                LoggingContextKeys::SUBSCRIPTION_ID => $this->domainSubscription->id,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
            ],
        );

        if ($this->domainDeployment->transfer_secret === DomainService::DEFERRED_TRANSFER) {
            $subscription = $this->domainSubscription;
            $subscription->technical_status = TechnicalStatus::TRANSFER_FAILED->value;
            $subscription->save();

            return;
        }

        $subscriptionRepository->setTechnicalStatus($this->domainSubscription, TechnicalStatus::PENDING->value);

        $result = $this->isTransfer
            ? $this->handleTransfer($domainService, $logger)
            : $this->handleRegistration($domainService, $logger);

        $resultStatus = $this->getResultStatus($result);

        $this->domainSubscription->update(['technical_status' => $resultStatus]);

        $this->domainDeployment->update([
            'last_result' => $result->getExceptionMessage() ?? $result->getReason(),
            'last_result_received' => CarbonImmutable::now(),
        ]);

        /*
         * This is older code from the DomainCreationListener, it might be the case that
         * in the DomainService the subscription is set to fail without it throwing an
         * exception, so we will ensure that the job fails if the subscription did
         */
        $this->domainSubscription->refresh();

        if (in_array(
            $this->domainSubscription->technical_status,
            [DomainStatus::FAILED->value, TechnicalStatus::FAILED->value],
            true,
        )) {
            $this->fail(new LogicException($this->resultMessage));
        }
    }

    public function failed(?Throwable $throwable): void
    {
        $container = Container::getInstance();
        $logger = $container->make(LoggerInterface::class);
        $logger->error(
            'Error RegisterDomainJob ({transfer_or_registration}) for domain {domain.name} job definitely failed after {job.attempt} attempts',
            [
                LoggingContextKeys::DOMAIN_NAME => $this->domainSubscription->domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->domainSubscription->uuid,
                LoggingContextKeys::SUBSCRIPTION_ID => $this->domainSubscription->id,
                LoggingContextKeys::EXCEPTION => $throwable,
                LoggingContextKeys::PROVISIONING_TYPE => $this->isTransfer ? 'domain.transfer' : 'domain.registration',
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
            ],
        );

        $this->domainSubscription->update([
            'technical_status' => TechnicalStatus::FAILED->value,
        ]);

        $this->domainDeployment->update([
            'last_result_received' => CarbonImmutable::now(),
            'last_result' => json_encode([
                'message' => 'Domain registration failed',
                'exception' => $throwable?->getMessage(),
                'trace' => $throwable?->getTraceAsString(),
            ]),
        ]);

        /** @var RtrErrorParseService $rtrErrorParseService */
        $rtrErrorParseService = $container->make(RtrErrorParseService::class);
        $translator = $container->make(TranslatorInterface::class);
        $reason = ! is_null($this->domainSubscription->domainDeployment?->last_result)
            ? $rtrErrorParseService->getTranslatedRtrError($this->domainSubscription->domainDeployment->last_result)
            : $translator->translate('domain-register-transfer-failed-unknown-reason');

        $mailer = $container->make(MailerInterface::class);
        $mailer->send(
            [$this->domainSubscription->customer],
            new MailDomainCreationFailed($this->domainSubscription->domain ?? '-', $reason),
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::PARTNER_DOMAIN;
    }

    private function handleRegistration(DomainService $domainService, LoggerInterface $logger): RegistrationResult
    {
        /*
         * This should never be triggered as the handle already checks for this
         * and throws an exception. PHPStan just doesn't understand this.
         */
        Assert::notNull($this->domainSubscription->domain);

        $result = $domainService->register(
            $this->domainDeployment,
            $this->domainSubscription->domain,
            $this->domainSubscription->contract_period,
            $this->domainSubscription->customer,
            $this->domainDeployment->private_whois_enabled,
            $this->domainDeployment->dnssec_enabled,
        );

        $this->resultMessage = sprintf(
            'Domain registration status: %s. Reason: %s',
            $result->getStatus()->value,
            $result->getReason(),
        );

        $logger->info(
            'RegisterDomainJob status after registration: {domain_registration.status}. Reason: {domain_registration.reason}',
            [
                LoggingContextKeys::DOMAIN_NAME => $this->domainSubscription->domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->domainSubscription->uuid,
                LoggingContextKeys::SUBSCRIPTION_ID => $this->domainSubscription->id,
                LoggingContextKeys::PROVISIONING_TYPE => 'domain.registration',
                LoggingContextKeys::META => [
                    'domain_registration.status' => $result->getStatus()->value,
                    'domain_registration.reason' => $result->getReason(),
                ],
            ],
        );

        return $result;
    }

    private function handleTransfer(DomainService $domainService, LoggerInterface $logger): TransferResult
    {
        /*
         * This should never be triggered as the handle already checks for this
         * and throws an exception. PHPStan just doesn't understand this.
         */
        Assert::notNull($this->domainSubscription->domain);

        $result = $domainService->transfer(
            $this->domainDeployment,
            $this->domainSubscription->domain,
            $this->domainSubscription->contract_period,
            $this->domainSubscription->customer,
            $this->domainDeployment->private_whois_enabled,
            $this->domainDeployment->dnssec_enabled,
            $this->domainDeployment->transfer_secret,
        );

        $this->resultMessage = sprintf(
            'Domain transfer status: %s. Reason: %s',
            $result->getStatus(),
            $result->getReason(),
        );

        $logger->info(
            'RegisterDomainJob status after transfer: {status}. Reason: {reason}',
            [
                LoggingContextKeys::DOMAIN_NAME => $this->domainSubscription->domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $this->domainSubscription->uuid,
                LoggingContextKeys::SUBSCRIPTION_ID => $this->domainSubscription->id,
                LoggingContextKeys::PROVISIONING_TYPE => 'domain.transfer',
                LoggingContextKeys::META => [
                    'domain_transfer.status' => $result->getStatus(),
                    'domain_transfer.reason' => $result->getReason(),
                ],
            ],
        );

        return $result;
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
