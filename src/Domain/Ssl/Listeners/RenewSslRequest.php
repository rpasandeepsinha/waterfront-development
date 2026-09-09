<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Email\Enums\ReceiverType;
use Waterfront\Domain\Email\Repositories\EmailHistoryRepository;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Mailer\TemplateRepository;
use Waterfront\Domain\Ssl\Mailers\SslRenewalFailedMissingCname;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CustomerSharedSslService;
use Waterfront\Domain\Ssl\Services\DcvCnameValidatorService;
use Waterfront\Domain\Ssl\Services\SslDnsManagementResolver;
use Waterfront\Domain\Subscriptions\Events\SubscriptionRenewedEvent;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

/**
 * Request a new SSL certificate when renewing a SSL deployment.
 */
class RenewSslRequest implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = QueueName::PARTNER_SSL->value;

    public function __construct(
        private readonly CustomerSharedSslService $sslService,
        private readonly DcvCnameValidatorService $cnameValidator,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly SslDnsManagementResolver $sslDnsManagementResolver,
        private readonly TemplateRepository $templateRepository,
        private readonly EmailHistoryRepository $emailHistoryRepository
    ) {
    }

    public function handle(SubscriptionRenewedEvent $event): void
    {
        $subscription = $event->subscription;

        if (
            ! $subscription->sslDeployment instanceof SslDeployment
        ) {
            return;
        }

        $deployment = $subscription->sslDeployment;

        $this->sslService->renew($deployment);
        $deployment->refresh();

        if ($this->sslDnsManagementResolver->hasManagedDns($deployment)) {
            return;
        }

        $template = $this->templateRepository->getBySlug(
            SslRenewalFailedMissingCname::getTemplateSlug()
        );

        $alreadySent = $this->emailHistoryRepository->wasEmailSentSince(
            receiverType: ReceiverType::CUSTOMER,
            receiverUuid: (string) $subscription->customer->getUuid(),
            templateId: $template->id,
            since: CarbonImmutable::today()
        );

        if ($alreadySent) {
            return;
        }

        $dcv = $this->sslService->getDcvDetails($deployment);

        if ($dcv === null) {
            $this->logger->warning(
                'RenewSslRequest: validation failed — DCV details not available.',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::PROVISIONING_TYPE => 'ssl',
                    LoggingContextKeys::META => [
                        'reason' => 'dcv_details_null',
                    ],
                ]
            );
            return;
        }

        if (! $this->cnameValidator->isCnameMissingOrIncorrect($dcv)) {
            return;
        }

        $domain = $subscription->domain ?? '';

        $this->mailer->send(
            [$subscription->customer],
            new SslRenewalFailedMissingCname(
                domain: $domain,
                cnameName: $dcv->dnsRecord,
                cnameValue: $dcv->dnsContent,
                expirydate: $subscription->end_date->format('Y-m-d')
            )
        );
    }
}
