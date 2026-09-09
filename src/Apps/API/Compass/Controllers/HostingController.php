<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;
use Waterfront\Apps\API\Compass\Requests\RetryHostingRequest;
use Waterfront\Apps\API\Compass\Resources\Infrastructure\HostingServerResource;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Domain\Hosting\Actions\RetryHostingAction;
use Waterfront\Domain\Hosting\Actions\RetryHostingDowngradeAction;
use Waterfront\Domain\Hosting\Enums\HostingRetryType;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

class HostingController
{
    public function __construct(
        private readonly RetryHostingDowngradeAction $retryHostingDowngradeAction,
        private readonly RetryHostingAction $retryHostingAction,
        private readonly SubscriptionPolicy $subscriptionPolicy,
        private readonly TranslatorInterface $translator,
        private readonly ServerRepository $serverRepository,
    ) {
    }

    public function retryDowngrade(Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanRetryDowngrades($subscription);

        try {
            $this->retryHostingDowngradeAction->execute($subscription);
        } catch (RuntimeException $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
            return new JsonResponse([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'message' => $this->translator->translate('status.success'),
        ], Response::HTTP_OK);
    }

    public function retryHosting(RetryHostingRequest $request, Subscription $subscription): JsonResponse
    {
        if (! $this->subscriptionPolicy->canRetryHosting($subscription)) {
            return new JsonResponse([
                'message' => $this->translator->translate('action.retry-hosting.subscription-invalid-for-retry'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->retryHostingAction->execute(
            $subscription,
            HostingRetryType::from($request->hosting_type),
            $request->server_id ?? null,
        );

        return new JsonResponse([
            'message' => $this->translator->translate('action.retry-hosting.retried-successfully'),
            'errors' => [],
        ], Response::HTTP_OK);
    }

    public function servers(): string
    {
        $servers = $this->serverRepository->getAllHostingServers();

        return HostingServerResource::collection($servers)->toJson();
    }
}
