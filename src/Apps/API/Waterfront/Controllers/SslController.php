<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Apps\API\Waterfront\Requests\Ssl\DownloadRequest;
use Waterfront\Apps\API\Waterfront\Resources\SslDeploymentResource;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CertificateRetriever;
use Waterfront\Domain\Ssl\Services\CustomerSharedSslService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Infra\Translation\TranslatorInterface;

class SslController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly SubscriptionPolicy $subscriptionPolicy,
        private readonly CertificateRetriever $certificateRetriever,
        private readonly SslDeploymentResource $sslDeploymentResource,
        private readonly CustomerSharedSslService $customerSharedSslService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     *
     * @return array <mixed>
     */
    public function getDeployment(SslDeployment $sslDeployment): array
    {
        $sslDeployment->loadMissing('subscription');
        $this->subscriptionPolicy->assertCanManageSsl($sslDeployment->subscription);

        return $this->sslDeploymentResource->toArray($sslDeployment);
    }

    public function download(DownloadRequest $request): Response
    {
        $uuid = $request->uuid;
        $type = $request->type;

        $subscription = $this->subscriptionService
            ->getSubscriptionsQuery()
            ->where('uuid', $uuid)
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->firstOrFail();

        if ($subscription->sslDeployment === null) {
            throw new NotFoundHttpException();
        }

        try {
            $certificate = $this->certificateRetriever->getCertificate($subscription->sslDeployment, $type);
            $fileName = sprintf('%s.%s', $subscription->domain, $type);
            $contentType = $this->getContentType($type);

            return new Response($certificate, Response::HTTP_OK, [
                'Content-Type' => $contentType,
                'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
                'Content-Length' => strlen($certificate),
            ]);
        } catch (Exception) {
            throw new NotFoundHttpException();
        }
    }

    public function retryDcv(SslDeployment $sslDeployment): JsonResponse
    {
        $sslDeployment->loadMissing('subscription');
        $this->subscriptionPolicy->assertCanManageSsl($sslDeployment->subscription);

        $result = $this->customerSharedSslService->resendDcv($sslDeployment);

        if ($result->getStatus() !== Result::STATUS_OK) {
            return new JsonResponse([
                'message' => $this->translator->translate('ssl-subscription.failed_resend_dcv'),
            ], $result->getErrorCode() ?? Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([
            'message' => $this->translator->translate('ssl-subscription.success_resend_dcv'),
        ], Response::HTTP_OK);
    }

    private function getContentType(string $type): string
    {
        // See the full list at https://pki-tutorial.readthedocs.io/en/latest/mime.html
        $mimeTypes = [
            'key' => 'application/pkcs8',
            'crt' => 'application/x-x509-user-cert',
        ];

        $contentType = Arr::get($mimeTypes, $type, 'text/plain');
        assert(is_string($contentType));

        return $contentType;
    }
}
