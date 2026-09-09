<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Jobs;

use Illuminate\Container\Container;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Microsoft365\Enums\PrimaryDomainStatus;
use Waterfront\Domain\Microsoft365\Mailer\Microsoft365PrimaryDomainUpdated;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

class SetPrimaryDomainJob extends AbstractQueueableJob
{
    public int $tries = 7;

    public function __construct(
        private readonly string $domain,
        private readonly Microsoft365CustomerInfo $microsoft365CustomerInfo,
    ) {
        parent::__construct();
    }

    /**
     * @return int[]
     */
    public function backoff(): array
    {
        return [
            15 * 60,
            30 * 60,
            60 * 60,
            120 * 60,
            240 * 60,
            480 * 60,
        ];
    }

    public function failed(?Throwable $throwable): void
    {
        $container = Container::getInstance();
        $logger = $container->make(LoggerInterface::class);
        $logger->error(
            'Error SetPrimaryDomainJob for domain {domain.name} job definitely failed after {queue.attempt} attempts',
            [
                LoggingContextKeys::DOMAIN_NAME => $this->domain,
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::EXCEPTION => $throwable,
                LoggingContextKeys::META => [
                    'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                ],
            ]
        );
        $this->updatePrimaryDomainInfo(PrimaryDomainStatus::VERIFICATION_FAILED);
    }

    public function handle(
        Microsoft365Service $microsoft365Service,
        LoggerInterface $logger,
        MailerInterface $mailer,
    ): void {
        $tenantId = $this->microsoft365CustomerInfo->tenant_id;

        if ($tenantId === null) {
            $tenantName = $this->microsoft365CustomerInfo->tenant_name;
            Assert::string($tenantName, 'Microsoft365CustomerInfo must have a tenant name to resolve the tenant id.');

            $tenantId = $microsoft365Service->getTenantIdByName($tenantName);

            if ($tenantId === null) {
                $logger->warning(
                    'Could not resolve tenant id for domain [{domain.name}], retrying',
                    [
                        LoggingContextKeys::DOMAIN_NAME => $this->domain,
                        LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                        LoggingContextKeys::META => [
                            'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                        ],
                    ]
                );
                $this->release($this->getBackoffDelay());
                return;
            }

            $this->microsoft365CustomerInfo->tenant_id = $tenantId;
            $this->microsoft365CustomerInfo->save();
        }

        $subscription = $this->microsoft365CustomerInfo->microsoft365Deployments->first()?->subscription;
        Assert::notNull($subscription, 'Microsoft365CustomerInfo must have at least one deployment with a subscription.');

        $logger->debug(
            sprintf(
                'Starting SetPrimaryDomainJob for domain [{domain.name}], attempt {queue.attempt}/%d',
                $this->tries
            ),
            [
                LoggingContextKeys::DOMAIN_NAME => $this->domain,
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::META => [
                    'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                ],
            ]
        );

        $this->updatePrimaryDomainInfo(PrimaryDomainStatus::VERIFICATION_PENDING);

        $domainExists = $microsoft365Service->checkIfDomainExistsInMicrosoftAccount(
            domain: $this->domain,
            tenantId: $tenantId,
            subscription: $subscription,
        );

        if (! $domainExists) {
            $logger->debug(
                'Creating domain [{domain.name}] in Microsoft',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->domain,
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::META => [
                        'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                    ],
                ]
            );

            $createDomainSuccess = $microsoft365Service->createDomainInMicrosoftAccount(
                domain: $this->domain,
                tenantId: $tenantId,
                subscription: $subscription,
            );

            $logger->debug(
                sprintf('[{domain.name}] %s in Microsoft', $createDomainSuccess ? 'has successfully been created' : 'failed to create'),
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->domain,
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::META => [
                        'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                    ],
                ]
            );

            if (! $createDomainSuccess) {
                $this->release($this->getBackoffDelay());
                return;
            }
        }

        // Retrieves verification records, checks if they exist else creates them
        $setVerificationRecords = $microsoft365Service->setVerificationDnsRecordsForPrimaryDomain(
            domain: $this->domain,
            tenantId: $tenantId,
            subscription: $subscription,
        );

        if (! $setVerificationRecords) {
            $logger->debug(
                'Verification records could not be retrieved for domain [{domain.name}]',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->domain,
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::META => [
                        'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                    ],
                ]
            );
            $this->release($this->getBackoffDelay());
            return;
        }

        $verifyDomain = $microsoft365Service->verifyDomainInMicrosoftAccount(
            domain: $this->domain,
            tenantId: $tenantId,
            subscription: $subscription,
        );

        if (! $verifyDomain) {
            $logger->debug(
                'Verification did not change the verify status or domain does not exist [{domain.name}]',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->domain,
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::META => [
                        'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                    ],
                ]
            );
            $this->release($this->getBackoffDelay());
            return;
        }

        $this->updatePrimaryDomainInfo(PrimaryDomainStatus::VERIFIED);

        $promotedDomain = $microsoft365Service->promoteDomainInMicrosoftAccount(
            domain: $this->domain,
            tenantId: $tenantId,
            subscription: $subscription,
        );

        if (! $promotedDomain) {
            $logger->debug(
                'Promotion did not change the domain to a default root domain [{domain.name}]',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->domain,
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::META => [
                        'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                    ],
                ]
            );
            $this->release($this->getBackoffDelay());
            return;
        }

        $isDefaultDomain = $microsoft365Service->setDomainAsDefaultDomainInMicrosoftAccount(
            domain: $this->domain,
            tenantId: $tenantId,
            subscription: $subscription,
        );

        if (! $isDefaultDomain) {
            $logger->debug(
                'Failed to set domain [{domain.name}] as default domain',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->domain,
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::META => [
                        'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                    ],
                ]
            );
            $this->release($this->getBackoffDelay());
            return;
        }

        // Retrieves service configuration records, delete old mx, checks if they exist else creates them
        $setServiceConfigurationRecords = $microsoft365Service->setServiceConfigurationRecordsForPrimaryDomain(
            domain: $this->domain,
            tenantId: $tenantId,
            subscription: $subscription,
        );

        if (! $setServiceConfigurationRecords) {
            $logger->debug(
                'Verification records could not be retrieved for domain [{domain.name}]',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->domain,
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::META => [
                        'Microsoft365CustomerInfoId' => $this->microsoft365CustomerInfo->id,
                    ],
                ]
            );
            $this->release($this->getBackoffDelay());
            return;
        }

        $this->updatePrimaryDomainInfo(PrimaryDomainStatus::ACTIVE, $this->domain);

        $mailer->send([$this->microsoft365CustomerInfo->customer], new Microsoft365PrimaryDomainUpdated(
            $this->domain
        ));
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::MICROSOFT365;
    }

    private function updatePrimaryDomainInfo(PrimaryDomainStatus $primaryDomainStatus, ?string $domain = null): void
    {
        if ($domain !== null) {
            $this->microsoft365CustomerInfo->primary_domain = $domain;
        }
        $this->microsoft365CustomerInfo->primary_domain_status = $primaryDomainStatus;
        $this->microsoft365CustomerInfo->save();
    }
}
