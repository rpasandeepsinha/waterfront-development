<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Jobs;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Ssl\Mailers\SslRenewalFailedMissingCname;
use Waterfront\Domain\Ssl\Repositories\DeploymentRepository as SslDeploymentRepository;
use Waterfront\Domain\Ssl\Services\CustomerSharedSslService;
use Waterfront\Domain\Ssl\Services\DcvCnameValidatorService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Exceptions\NotImplementedException;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

class SendDcvReminderEmail extends AbstractQueueableJob
{
    public function __construct(
        public readonly int $sslDeploymentId
    ) {
        parent::__construct();
    }

    public function handle(
        SslDeploymentRepository $sslDeploymentRepository,
        CustomerSharedSslService $customerSharedSslService,
        DcvCnameValidatorService $cnameValidatorService,
        MailerInterface $mailer,
        LoggerInterface $logger
    ): void {
        $sslDeployment = $sslDeploymentRepository->findForReminderById($this->sslDeploymentId);

        if ($sslDeployment === null) {
            return;
        }

        $subscription = $sslDeployment->subscription;
        Assert::notNull($subscription->domain, sprintf('Cannot send DCV reminder: subscription %s has no domain', $subscription->uuid));

        try {
            $dcv = $customerSharedSslService->getDcvDetails($sslDeployment);
        } catch (NotImplementedException) {
            return;
        }

        if ($dcv === null) {
            $logger->warning(
                'RenewSslRequest: validation failed — DCV details not available.',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $sslDeployment->subscription_uuid,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::PROVISIONING_TYPE => 'ssl',
                    LoggingContextKeys::META => [
                        'reason' => 'dcv_details_null',
                    ],
                ]
            );
            return;
        }

        if (! $cnameValidatorService->isCnameMissingOrIncorrect($dcv)) {
            return;
        }

        $expiryDate = $subscription->end_date->format('Y-m-d');

        $mailer->send(
            [$sslDeployment->subscription->customer],
            new SslRenewalFailedMissingCname(
                domain: $subscription->domain,
                cnameName: $dcv->dnsRecord,
                cnameValue: $dcv->dnsContent,
                expirydate: $expiryDate
            )
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::CRM;
    }
}
