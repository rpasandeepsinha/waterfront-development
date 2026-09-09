<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;
use Waterfront\Apps\API\Compass\DTO\UpdateHostingDeploymentDto;
use Waterfront\Apps\API\Compass\Resources\Deployments\ProvisionGroupedRequestResource;
use Waterfront\Apps\API\Compass\Transformers\ProvisioningResultGroupingTransformer;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Apps\API\Waterfront\Requests\Hosting\GenerateHostingSsoUrlRequest;
use Waterfront\Domain\Domains\Actions\RetryDomainAction;
use Waterfront\Domain\Hosting\Actions\UpdateHostingDeploymentAction;
use Waterfront\Domain\Hosting\Exceptions\SsoResolveException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Servers\Exceptions\ServerNotFoundException;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Exceptions\NotImplementedException;
use Webmozart\Assert\Assert;

class DeploymentController
{
    public function __construct(
        private readonly HostingService $hostingService,
        private readonly SubscriptionPolicy $subscriptionPolicy,
        private readonly ProvisionGateway $provisionGateway,
        private readonly RetryDomainAction $retryDomainAction,
        private readonly TranslatorInterface $translator,
        private readonly SitebuilderService $sitebuilderService,
        private readonly UpdateHostingDeploymentAction $updateHostingDeploymentAction,
        private readonly ProvisioningResultGroupingTransformer $provisioningResultGroupingTransformer,
    ) {
    }

    public function updateDeployment(Request $request, HostingDeployment $hostingDeployment): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username'                      => ['sometimes', 'nullable', 'string'],
            'plesk_customer_id'             => ['sometimes', 'nullable', 'integer'],
            'provider'                      => ['required', 'string', 'exists:providers,slug'],
            'server_id'                     => ['required', 'integer', 'exists:hosting_servers,id'],
            'basekit_user_ref'              => ['sometimes', 'nullable', 'required_if:provider,basekit', 'integer'],
            'basekit_site_ref'              => ['sometimes', 'nullable', 'required_if:provider,basekit', 'integer'],
            'mail_provider'                 => ['sometimes', 'nullable', 'string', 'exists:providers,slug'],
            'mail_server_id'                => ['sometimes', 'nullable', 'integer', 'exists:hosting_servers,id'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->messages()->toArray());
        }

        $username = $request->input('username') !== null ? $request->string('username')->toString() : null;
        $pleskCustomerId = $request->input('plesk_customer_id') !== null ? $request->integer('plesk_customer_id') : null;
        $providerSlug = $request->string('provider')->toString();
        $serverId = $request->integer('server_id');
        $basekitUserRef = $request->input('basekit_user_ref') !== null ? $request->integer('basekit_user_ref') : null;
        $basekitSiteRef = $request->input('basekit_site_ref') !== null ? $request->integer('basekit_site_ref') : null;
        $mailProvider = $request->input('mail_provider') !== null ? $request->string('mail_provider')->toString() : null;
        $mailServerId = $request->input('mail_server_id') !== null ? $request->integer('mail_server_id') : null;

        $dto = new UpdateHostingDeploymentDto(
            hostingDeployment: $hostingDeployment,
            username: $username,
            pleskCustomerId: $pleskCustomerId,
            provider: $providerSlug,
            serverId: $serverId,
            basekitUserRef: $basekitUserRef,
            basekitSiteRef: $basekitSiteRef,
            mailProvider: $mailProvider,
            mailServerId: $mailServerId,
        );

        $this->updateHostingDeploymentAction->execute($dto);

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }

    public function FailedProvisioningResults(Request $request): AnonymousResourceCollection
    {
        $filters = new ProvisioningResultQueryFilters(null, null, [ProvisionStatus::FAILED, ProvisionStatus::DELETION_FAILED, ProvisionStatus::VALIDATION_ERROR]);

        $results = $this->provisionGateway->fetch($filters, null);

        $transformedRequests = $this->provisioningResultGroupingTransformer->transform($results);

        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $currentPage = $request->integer('page', 1);

        $currentPageItems = $transformedRequests->slice(($currentPage - 1) * $pageSize, $pageSize)->values();

        $collection = new LengthAwarePaginator(
            $currentPageItems,
            $results->count(),
            $pageSize,
            $currentPage,
            []
        );

        return ProvisionGroupedRequestResource::collection($collection)->additional([
            'meta' => ['totalFailedRequests' => $results->count()],
        ]);
    }

    public function updateRetry(Request $request, Subscription $subscription): Response
    {
        $request->validate([
            'dnssec_enabled' => ['required', 'boolean'],
            'transfer_secret' => ['sometimes', 'required', 'string'],
            'private_whois_enabled' => ['required', 'boolean'],
        ]);

        $enableDnssec = $request->input('dnssec_enabled');
        $transferSecret = $request->input('transfer_secret');
        $privateWhois = $request->input('private_whois_enabled');

        Assert::boolean($enableDnssec);
        Assert::boolean($privateWhois);
        if ($transferSecret !== null) {
            Assert::string($transferSecret);
        }

        Assert::notNull($subscription->domainDeployment);

        $this->retryDomainAction->execute(
            $subscription->domainDeployment,
            $enableDnssec,
            $privateWhois,
            $transferSecret
        );

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function getSsoUrl(
        GenerateHostingSsoUrlRequest $request,
        Subscription $subscription,
    ): JsonResponse {
        $hostingDeployment = $subscription->hostingDeployment;
        assert($hostingDeployment instanceof HostingDeployment);
        $this->subscriptionPolicy->assertCanManageHosting($subscription);

        $provider = match (true) {
            $hostingDeployment->mailProvider !== null => $hostingDeployment->mailProvider,
            $hostingDeployment->provider !== null => $hostingDeployment->provider,
            $hostingDeployment->sitebuilderProvider !== null => $hostingDeployment->sitebuilderProvider,
            default => null
        };

        if ($provider === null) {
            return new JsonResponse([
                'message' => $this->translator->translate('hosting.sso-resolve-exception'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($provider->type === ProviderType::SITEBUILDER) {
            try {
                $url = $this->sitebuilderService->getSsoUrl($hostingDeployment);
            } catch (UnexpectedValueException|ServerNotFoundException|InvalidArgumentException) {
                return new JsonResponse([
                    'message' => $this->translator->translate('hosting.sso-resolve-exception'),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } else {
            try {
                /** @var string $ipAddress */
                $ipAddress = $request->ip();

                $url = $this->hostingService->getSsoUrl(
                    $hostingDeployment,
                    $ipAddress,
                    $provider->type === ProviderType::MAILONLY
                );
            } catch (SsoResolveException|NotImplementedException) {
                return new JsonResponse([
                    'message' => $this->translator->translate('hosting.sso-resolve-exception'),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return new JsonResponse([
            'url' => $url,
        ]);
    }
}
