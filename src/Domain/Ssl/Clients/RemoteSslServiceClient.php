<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Clients;

use Exception;
use LogicException;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use RuntimeException;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Ssl\Factories\SslServiceFactory;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Infra\RtrClient\DTO\DcvDetails;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;

class RemoteSslServiceClient
{
    public function __construct(
        private readonly SslServiceFactory $sslServiceFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function hasSslRequest(SslDeployment $sslDeployment): bool
    {
        $domain = $sslDeployment->subscription->domain;
        if ($domain === null) {
            throw new LogicException(
                "SSL deployment with id {$sslDeployment->id} has no domain, so the SSL request cannot be retrieved."
            );
        }

        return $this->sslServiceFactory
            ->driver($sslDeployment->provider->slug)
            ->hasSslRequest(domain: $domain);
    }

    /**
     * Call the SSL service in order to create an SSL deployment.
     */
    public function create(
        int $period,
        SslDeployment $sslDeployment,
        ?string $csr = null
    ): Result {
        try {
            if ($sslDeployment->request_id !== null) {
                throw new RuntimeException(
                    sprintf(
                        'Cannot create a new SSL request for subscription #%s because the ssl deployment already appears to have one requested #%s',
                        $sslDeployment->id,
                        $sslDeployment->request_id
                    )
                );
            }

            /** @var ProductSpec $productSpec */
            $productSpec = $sslDeployment->subscription->product->productSpecs()->where('name', 'ssl.product_id')
                ->firstOrFail();

            $this->logger->info(
                'Creating SSL certificate',
                [
                    LoggingContextKeys::PROVISIONING_ID => $sslDeployment->id,
                    LoggingContextKeys::DOMAIN_NAME => $sslDeployment->subscription->domain,
                    LoggingContextKeys::META => [
                        'period' => $period,
                        'csr' => $csr,
                    ],
                ]
            );

            return $this->sslServiceFactory
                ->driver($sslDeployment->provider->slug)
                ->create(
                    productSpec: $productSpec,
                    period: $period,
                    customerData: $sslDeployment->subscription->customer->load('address')->toArray(),
                    sslDeployment: $sslDeployment,
                    csr: $csr,
                );
        } catch (Exception $exception) {
            $this->logger->error(
                self::class . '::callCreationService - status code: ' . $exception->getCode()
                . ', message: ' . $exception->getMessage()
                . ', trace: ' . $exception->getTraceAsString()
            );

            return Result::create(
                [
                    'status' => Result::STATUS_ERROR,
                    'errorCode' => $exception->getCode(),
                    'errorMessage' => $exception->getMessage(),
                ]
            );
        }
    }

    public function retrieve(SslDeployment $sslDeployment): Result
    {
        return $this->sslServiceFactory
            ->driver($sslDeployment->provider->slug)
            ->retrieve($sslDeployment);
    }

    /**
     * Call the ssl service in order to reissue an SSL deployment.
     */
    public function reissue(
        SslDeployment $sslDeployment,
        string $csr
    ): Result {
        $this->logger->info(
            'Reissue SSL certificate',
            [
                LoggingContextKeys::PROVISIONING_ID => $sslDeployment->id,
                LoggingContextKeys::DOMAIN_NAME => $sslDeployment->subscription->domain,
                LoggingContextKeys::META => [
                    'csr' => $csr,
                ],
            ]
        );

        try {
            return $this->sslServiceFactory
                ->driver($sslDeployment->provider->slug)
                ->reissue(
                    customerData: $sslDeployment->subscription->customer->load('address')->toArray(),
                    sslDeployment: $sslDeployment,
                    csr: $csr
                );
        } catch (Exception $exception) {
            $this->logger->error(
                self::class . '::reissue - status code: ' . $exception->getCode()
                . ', message: ' . $exception->getMessage()
                . ', trace: ' . $exception->getTraceAsString()
            );

            return Result::create(
                [
                    'status' => Result::STATUS_ERROR,
                    'errorCode' => $exception->getCode(),
                    'errorMessage' => $exception->getMessage(),
                ]
            );
        }
    }

    public function renew(SslDeployment $sslDeployment): Result
    {
        $this->logger->info(
            'Renewing SSL certificate',
            [
                LoggingContextKeys::PROVISIONING_ID => $sslDeployment->id,
                LoggingContextKeys::DOMAIN_NAME => $sslDeployment->subscription->domain,
            ]
        );

        try {
            return $this->sslServiceFactory
                ->driver($sslDeployment->provider->slug)
                ->renew($sslDeployment);
        } catch (Exception $exception) {
            $this->logger->error(
                self::class . '::callRenewalService - status code: ' . $exception->getCode()
                . ', message: ' . $exception->getMessage()
                . ', trace: ' . $exception->getTraceAsString()
            );

            return Result::create(
                [
                    'status' => Result::STATUS_ERROR,
                    'errorCode' => $exception->getCode(),
                    'errorMessage' => $exception->getMessage(),
                ]
            );
        }
    }

    /**
     * Call the ssl service to check if the csr key exists locally for a domain.
     */
    public function csrExistsForDomain(string $domain, ProviderSlug $driver): bool
    {
        return $this->sslServiceFactory
            ->driver($driver)
            ->csrExistsForDomain($domain);
    }

    /**
     * @return bool|Result|string[]
     */
    public function csrValidate(string $csr, string $domain, ?SslDeployment $sslDeployment): array|Result|bool
    {
        $driver = $sslDeployment?->provider->slug;

        try {
            $service = $driver !== null ?
                $this->sslServiceFactory->driver($driver) :
                $this->sslServiceFactory->defaultDriver();

            return $service->validate($csr, $domain, $sslDeployment);
        } catch (Exception $exception) {
            return Result::create(
                [
                    'status' => Result::STATUS_ERROR,
                    'errorCode' => $exception->getCode(),
                    'errorMessage' => $exception->getMessage(),
                    'csr' => $csr,
                ]
            );
        }
    }

    public function resendDcv(SslDeployment $sslDeployment): Result
    {
        $this->logger->info('Resend DCV', [
            LoggingContextKeys::PROVISIONING_ID => $sslDeployment->id,
            LoggingContextKeys::DOMAIN_NAME => $sslDeployment->subscription->domain,
            LoggingContextKeys::META => [
                'request_id' => $sslDeployment->request_id,
            ],
        ]);

        try {
            return $this->sslServiceFactory
                ->driver($sslDeployment->provider->slug)
                ->resendDcv($sslDeployment);
        } catch (NotImplementedException) {
            $this->logger->warning(self::class . '::resendDcv - not supported by provider', [
                LoggingContextKeys::PROVISIONING_ID => $sslDeployment->id,
                LoggingContextKeys::DOMAIN_NAME => $sslDeployment->subscription->domain,
                LoggingContextKeys::META => [
                    'provider' => $sslDeployment->provider->slug,
                ],
            ]);

            return Result::create([
                'status' => Result::STATUS_ERROR,
                'errorCode' => 409,
                'errorMessage' => 'blocked',
                'reason' => 'Provider does not support DNS DCV resend',
            ]);
        } catch (RealtimeRegisterClientException $exception) {
            $this->logger->error(
                self::class . '::resendDcv - status code: ' . $exception->getCode()
                . ', message: ' . $exception->getMessage()
                . ', trace: ' . $exception->getTraceAsString(),
                [
                    LoggingContextKeys::PROVISIONING_ID => $sslDeployment->id,
                    LoggingContextKeys::DOMAIN_NAME => $sslDeployment->subscription->domain,
                ]
            );

            return Result::create([
                'status' => Result::STATUS_ERROR,
                'errorCode' => $exception->getCode(),
                'errorMessage' => $exception->getMessage(),
            ]);
        }
    }

    public function getDcvDetails(SslDeployment $sslDeployment): ?DcvDetails
    {
        try {
            return $this->sslServiceFactory
                ->driver($sslDeployment->provider->slug)
                ->getSslCnameRecord($sslDeployment);
        } catch (NotImplementedException) {
            return null;
        } catch (RealtimeRegisterClientException $exception) {
            $this->logger->warning(
                self::class . '::getDcvDetails - RTR client error',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $sslDeployment->subscription_uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::RESPONSE_CODE => $exception->getCode(),
                ]
            );
            return null;
        }
    }
}
