<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Waterfront\Apps\API\Compass\Requests\RetrySslDeploymentRequest;
use Waterfront\Apps\API\Compass\Resources\Subscription\SslDeploymentResource;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Ssl\Actions\UpdateSslRequestStatusAction;
use Waterfront\Domain\Ssl\Exceptions\SslRequestStatusException;
use Waterfront\Domain\Ssl\Jobs\RetrySslJob;
use Waterfront\Domain\Ssl\Jobs\SetSslDnsVerifyRecordJob;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CertificateRetriever;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\RtrClient\Services\Ssl\CertificateDownloader;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class SslDeploymentController
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SslDeploymentResource $sslDeploymentResource,
        private readonly CertificateRetriever $certificateRetriever,
        private readonly CertificateDownloader $rtrCertificateDownloader,
        private readonly LoggerInterface $logger,
        private readonly UpdateSslRequestStatusAction $updateSslRequestStatusAction,
        private readonly SubscriptionPolicy $subscriptionPolicy,
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    public function deployment(string $domain): string|JsonResponse
    {
        $subscription = $this->subscriptionRepository->findByDomainAndType($domain, ProductGroupType::SSL);

        $sslDeployment = $subscription->sslDeployment;
        if ($sslDeployment === null) {
            return new JsonResponse(['message' => 'The deployment could not be found'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->sslDeploymentResource->toJson($sslDeployment);
    }

    public function download(Request $request, SslDeployment $sslDeployment): Response
    {
        $request->validate([
            'type' => ['required', 'string', Rule::in(['csr', 'key', 'crt', 'root', 'intermediate'])],
        ]);
        $certificateType = $request->input('type');
        Assert::notNull($certificateType);
        Assert::string($certificateType);

        try {
            $certificate = $this->certificateRetriever->getCertificate($sslDeployment, $certificateType);
            $fileName = sprintf('%s.%s', $sslDeployment->subscription->domain, $certificateType);
            $contentType = $this->getContentType($certificateType);

            return new Response($certificate, Response::HTTP_OK, [
                'Content-Type' => $contentType,
                'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
                'Content-Length' => strlen($certificate),
            ]);
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    'Could not download the %s certificate for SSL deployment #%d: %s',
                    $certificateType,
                    $sslDeployment->id,
                    $exception->getMessage()
                ),
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );
            throw new NotFoundHttpException();
        }
    }

    public function updateSslRequestStatus(SslDeployment $sslDeployment): JsonResponse
    {
        $subscription = $sslDeployment->subscription;
        try {
            $this->subscriptionPolicy->assertCanManageSsl($subscription);
        } catch (AuthorizationException) {
            return new JsonResponse(['message' => 'Unauthorized', 'errors' => []], Response::HTTP_FORBIDDEN);
        }

        try {
            $this->updateSslRequestStatusAction->execute($sslDeployment);
        } catch (SslRequestStatusException $exception) {
            return new JsonResponse(['message' => $exception->getMessage(),  'errors' => []], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['message' => 'SSL request status updated successfully']);
    }

    /**
     * @throws Exception
     */
    public function syncCertificateFromRtr(SslDeployment $sslDeployment): JsonResponse
    {
        if ($sslDeployment->provider->slug !== ProviderSlug::REALTIME_REGISTER) {
            return new JsonResponse(['message' => 'Deployment is not RTR provider', 'errors' => []], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->rtrCertificateDownloader->downloadForSslDeployment($sslDeployment);

            return new JsonResponse(['message' => 'Certificate downloaded successfully.'], Response::HTTP_OK);
        } catch (Exception $exception) {
            $this->logger->error(
                'Manually syncing SSL certificate from RTR failed for subscription {subscription.id}',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $sslDeployment->subscription->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );
            throw $exception;
        }
    }

    public function retrySslDeployment(RetrySslDeploymentRequest $request, SslDeployment $sslDeployment): Response
    {
        $csr = $request->input('csr');
        Assert::nullOrString($csr);

        $this->jobDispatcher->dispatch(new RetrySslJob($sslDeployment, $csr));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function setSslDnsVerifyRecord(SslDeployment $sslDeployment): Response
    {
        $this->subscriptionPolicy->assertCanManageSsl($sslDeployment->subscription);

        $this->jobDispatcher->dispatch(new SetSslDnsVerifyRecordJob($sslDeployment));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function updateProvider(SslDeployment $sslDeployment, Provider $provider): JsonResponse
    {
        $subscription = $sslDeployment->subscription;

        try {
            $this->subscriptionPolicy->assertCanManageSsl($subscription);
        } catch (AuthorizationException) {
            return new JsonResponse(['message' => 'Unauthorized', 'errors' => []], Response::HTTP_FORBIDDEN);
        }

        if ($provider->type !== ProviderType::SSL) {
            return new JsonResponse(['message' => 'Provider is not an SSL provider', 'errors' => []], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $sslDeployment->provider_id = $provider->id;
        $sslDeployment->save();

        $this->logger->info('SSL deployment provider updated', [
            LoggingContextKeys::SUBSCRIPTION_ID => $sslDeployment->subscription->id,
            LoggingContextKeys::PROVISIONING_PROVIDER => $provider->slug->value,
        ]);

        return new JsonResponse(['message' => 'SSL provider updated successfully']);
    }

    private function getContentType(string $type): string
    {
        $mimeTypes = [
            'key' => 'application/pkcs8',
            'crt' => 'application/x-x509-user-cert',
        ];

        $contentType = Arr::get($mimeTypes, $type, 'text/plain');
        Assert::string($contentType);

        return $contentType;
    }
}
