<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Jobs;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Certificate;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use RealtimeRegister\RealtimeRegister;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Ssl\Events\CreateSsl;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Repositories\DeploymentRepository;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class RetrySslJob extends AbstractQueueableJob
{
    public function __construct(
        private readonly SslDeployment $sslDeployment,
        private readonly ?string $csr,
    ) {
        parent::__construct();
    }

    public function handle(
        Dispatcher $eventDispatcher,
        RealtimeRegister $realtimeRegister,
        DeploymentRepository $sslDeploymentRepository,
        LoggerInterface $logger,
    ): void {
        $subscription = $this->sslDeployment->subscription;
        $domain = $subscription->domain;

        if ($domain === null) {
            return;
        }

        $existingCertificate = $this->findExistingActiveCertificate($realtimeRegister, $logger, $domain);

        if ($existingCertificate instanceof Certificate) {
            $sslDeploymentRepository->linkExistingCertificate($this->sslDeployment, $existingCertificate);

            $logger->info(
                'Retry SSL: linked existing RTR certificate to deployment',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SSL,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $this->sslDeployment->provider->slug,
                    LoggingContextKeys::PROVISIONING_ID => $this->sslDeployment->id,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::META => [
                        'certificate_id' => $existingCertificate->id,
                    ],
                ]
            );

            return;
        }

        $eventDispatcher->dispatch(
            new CreateSsl(
                $domain,
                $subscription->contract_period,
                $this->sslDeployment,
                $this->csr,
            )
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::PARTNER_SSL;
    }

    private function findExistingActiveCertificate(
        RealtimeRegister $realtimeRegister,
        LoggerInterface $logger,
        string $domain,
    ): ?Certificate {
        try {
            $certificates = $realtimeRegister->certificates->listCertificates(
                limit: 1,
                offset: 0,
                parameters: [
                    'status' => 'ACTIVE',
                    'order' => '-startDate',
                    'domainName' => $domain,
                ],
            );
        } catch (RealtimeRegisterClientException | GuzzleException $exception) {
            $logger->warning(
                'Retry SSL: unable to query RTR for existing certificate',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SSL,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

            return null;
        }

        $certificate = $certificates->offsetGet(0);

        if (! $certificate instanceof Certificate) {
            return null;
        }

        if ($certificate->domainName !== $domain) {
            $logger->warning(
                'Retry SSL: RTR returned certificate for a different domain; ignoring',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SSL,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::META => [
                        'certificate_id' => $certificate->id,
                        'rtr_domain' => $certificate->domainName,
                    ],
                ]
            );

            return null;
        }

        return $certificate;
    }
}
