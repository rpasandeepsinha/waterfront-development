<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Waterfront\Apps\API\Waterfront\Policies\RedirectPolicy;
use Waterfront\Apps\API\Waterfront\Requests\Redirect\DestroyRequest;
use Waterfront\Apps\API\Waterfront\Requests\Redirect\StoreRequest;
use Waterfront\Apps\API\Waterfront\Requests\Redirect\UpdateRequest;
use Waterfront\Apps\API\Waterfront\Resources\RedirectDeploymentResource;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Exceptions\ListRedirectsException;
use Waterfront\Domain\Redirects\Services\RedirectService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class RedirectController
{
    public function __construct(
        private readonly RedirectPolicy $redirectPolicy,
        private readonly TranslatorInterface $translator,
        private readonly RedirectDeploymentResource $redirectDeploymentResource,
        private readonly RedirectService $redirectService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     *
     * @return JsonResponse|array<string, mixed>
     */
    public function getDeployment(Subscription $subscription): JsonResponse|array
    {
        $this->redirectPolicy->assertCanList($subscription);

        if ($subscription->domain === null) {
            throw new RuntimeException(
                'Something went wrong, trying to retrieve redirects for a subscription without a domain.',
            );
        }

        try {
            $listRedirects = $this->redirectService->listRedirects($subscription);
        } catch (ListRedirectsException $exception) {
            $this->logger->error('Error listing redirects.', [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            return new JsonResponse([
                'message' => $this->translator->translate('redirects.get-failed'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->redirectDeploymentResource->toArray($listRedirects, $subscription);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     * @throws ValidationException
     */
    public function store(
        StoreRequest $request,
        Subscription $subscription,
    ): JsonResponse {
        $this->redirectPolicy->assertCanCreate($subscription);

        $source = strval($request->string('source'));
        $target = strval($request->string('target'));
        $type = strval($request->string('type'));

        $createRedirect = $this->redirectService->createRedirect(
            $subscription,
            $source,
            $target,
            RedirectType::from($type),
        );

        if ($createRedirect->failed) {
            return new JsonResponse([
                'message' => $this->translator->translate('redirects.create-failed'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'data' => [
                [
                    'source' => $source,
                    'target' => $target,
                    'type' => $type,
                ],
            ],
            'errors' => [],
        ], Response::HTTP_CREATED);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     * @throws ValidationException
     */
    public function update(
        UpdateRequest $request,
        Subscription $subscription,
    ): JsonResponse {
        $this->redirectPolicy->assertCanUpdate($subscription);
        $newSource = strval($request->string('new.source'));
        $newTarget = strval($request->string('new.target'));
        $newType = strval($request->string('new.type'));
        $oldSource = strval($request->string('old.source'));
        $oldType = strval($request->string('old.type'));
        $typeToUse = $newType !== $oldType ? $newType : $oldType;

        $updateRedirect = $this->redirectService->updateRedirect(
            $subscription,
            $oldSource,
            $newSource,
            $newTarget,
            RedirectType::from($typeToUse),
        );

        if ($updateRedirect->failed) {
            return new JsonResponse([
                'message' => $this->translator->translate('redirects.update-failed'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'message' => $this->translator->translate('status.success'),
            'errors' => [],
        ]);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function destroy(
        DestroyRequest $request,
        Subscription $subscription,
    ): JsonResponse {
        $this->redirectPolicy->assertCanDelete($subscription);

        $deleteRedirect = $this->redirectService->deleteRedirect($subscription, $request->source);

        if ($deleteRedirect->failed) {
            return new JsonResponse([
                'message' => $this->translator->translate('redirects.delete-failed'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'message' => $this->translator->translate('status.success'),
            'errors' => [],
        ]);
    }
}
