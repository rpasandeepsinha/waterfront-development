<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Apps\API\Waterfront\Requests\Mail\CreateForwardRequest;
use Waterfront\Apps\API\Waterfront\Requests\Mail\CreateUserRequest;
use Waterfront\Apps\API\Waterfront\Requests\Mail\ResetUserRequest;
use Waterfront\Domain\Hosting\DirectAdmin\Exceptions\EmailForwardException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\MailManagement\Services\MailManagementService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\SpamExpertsClient\Exceptions\SpamexpertsSsoException;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;
use Webmozart\Assert\Assert;

class MailController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SubscriptionPolicy $subscriptionPolicy,
        private readonly TranslatorInterface $translator,
        private readonly MailManagementService $mailOnlyService,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function configuration(
        string $domain,
    ): JsonResponse {
        /**
         * @var Subscription|null $subscription
         */
        $subscription = Subscription::query()
            ->whereProductGroupType(ProductGroupType::HOSTING)
            ->where('domain', $domain)
            ->whereOneOfProductSpecNamesAndValueIsTrue([
                ProductSpecName::HOSTING_LEGACY_MAIL_ONLY,
                ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT,
            ])
            ->first();

        if (is_null($subscription)) {
            throw new AuthorizationException();
        }

        $this->subscriptionPolicy->assertCanAccess($subscription);

        try {
            $status = $this->mailOnlyService->configuration();

            return new JsonResponse([
                'data' => $status,
            ]);
        } catch (NotImplementedException|ModelNotFoundException) {
            return new JsonResponse([
                'message' => $this->translator->translate('mail-providers.errors.configuration-error'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function users(
        string $domain,
    ): JsonResponse {
        /**
         * @var Subscription|null $subscription
         */
        $subscription = Subscription::query()
            ->whereProductGroupType(ProductGroupType::HOSTING)
            ->where('domain', $domain)
            ->whereOneOfProductSpecNamesAndValueIsTrue([
                ProductSpecName::HOSTING_LEGACY_MAIL_ONLY,
                ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT,
            ])
            ->first();

        if (is_null($subscription)) {
            throw new AuthorizationException();
        }

        $this->subscriptionPolicy->assertCanAccess($subscription);

        try {
            $listDomain = $this->mailOnlyService->users($subscription);
            $rawUsers = Arr::get($listDomain, 'users', []);
            assert(is_array($rawUsers));

            $users = new Collection($rawUsers)->map(fn (string $user) => ['username' => $user]);

            return new JsonResponse([
                'data' => $users,
            ]);
        } catch (Exception $exception) {
            $this->logger->info($exception->getMessage(), [LoggingContextKeys::EXCEPTION => $exception]);

            return new JsonResponse([
                'message' => $this->translator->translate('mail-providers.errors.users-error'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function createUser(
        CreateUserRequest $request,
        string $domain,
    ): JsonResponse {
        /**
         * @var Subscription|null $subscription
         */
        $subscription = Subscription::query()
            ->whereProductGroupType(ProductGroupType::HOSTING)
            ->where('domain', $domain)
            ->whereOneOfProductSpecNamesAndValueIsTrue([
                ProductSpecName::HOSTING_LEGACY_MAIL_ONLY,
                ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT,
            ])
            ->first();

        if (is_null($subscription)) {
            throw new AuthorizationException();
        }

        $this->subscriptionPolicy->assertCanAccess($subscription);

        try {
            $username = strval($request->string('username'));
            $password = strval($request->string('password'));

            $status = $this->mailOnlyService->createUser(
                $subscription,
                $username,
                $password,
            );

            return new JsonResponse([
                'data' => [
                    'user' => $request->input('username'),
                    'status' => $status,
                ],
            ], 201);
        } catch (Exception $exception) {
            $this->logger->error(
                'Error when creating mail account on hosting deployment.',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::PRODUCT_SLUG => $subscription->product->slug,
                    LoggingContextKeys::PRODUCT_ID => $subscription->product->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'mail-account' => $username,
                    ],
                ],
            );

            return new JsonResponse([
                'message' => $this->translator->translate('mail-providers.errors.create-user-error'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function resetUser(
        ResetUserRequest $request,
        string $domain,
        string $username,
    ): JsonResponse {
        /**
         * @var Subscription|null $subscription
         */
        $subscription = Subscription::query()
            ->whereProductGroupType(ProductGroupType::HOSTING)
            ->where('domain', $domain)
            ->whereOneOfProductSpecNamesAndValueIsTrue([
                ProductSpecName::HOSTING_LEGACY_MAIL_ONLY,
                ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT,
            ])
            ->first();

        if (is_null($subscription)) {
            throw new AuthorizationException();
        }

        $this->subscriptionPolicy->assertCanAccess($subscription);

        try {
            if (! $this->mailOnlyService->domainUserExists($subscription, $username)) {
                return new JsonResponse([
                    'message' => $this->translator->translate('mail-providers.errors.username-does-not-exist'),
                    'errors' => [],
                ], Response::HTTP_NOT_FOUND);
            }

            $password = strval($request->string('password'));

            $status = $this->mailOnlyService->resetPassword($subscription, $username, $password);

            return new JsonResponse([
                'status' => $status,
            ]);
        } catch (Exception $exception) {
            $this->logger->error('Error during Mail account password reset', [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                LoggingContextKeys::PRODUCT_SLUG => $subscription->product->slug,
                LoggingContextKeys::PRODUCT_ID => $subscription->product->id,
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => [
                    'mail-account' => $username,
                ],
            ]);

            return new JsonResponse([
                'message' => $this->translator->translate('mail-providers.errors.password-reset-error'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function spamExperts(
        HostingDeployment $hostingDeployment,
    ): JsonResponse {
        $subscription = $hostingDeployment->subscription;

        $this->subscriptionPolicy->assertCanManageHosting($subscription);

        if (! $subscription->product->isMailOnlyServer() && ! $subscription->product->isSitebuilderProduct()) {
            return new JsonResponse([
                'message' => $this->translator->translate('mail-providers.domain-does-not-exist'),
                'errors' => [],
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $sso = $this->mailOnlyService->spamExpertsSso($subscription);
        } catch (SpamexpertsSsoException) {
            return new JsonResponse([
                'message' => $this->translator->translate('mail-providers.errors.spamexperts-sso-error'),
                'errors' => [],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'url' => $sso,
        ]);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function deleteUser(
        string $domain,
        string $username,
    ): JsonResponse {
        /**
         * @var Subscription|null $subscription
         */
        $subscription = Subscription::query()
            ->whereProductGroupType(ProductGroupType::HOSTING)
            ->where('domain', $domain)
            ->whereOneOfProductSpecNamesAndValueIsTrue([
                ProductSpecName::HOSTING_LEGACY_MAIL_ONLY,
                ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT,
            ])
            ->first();

        if (is_null($subscription)) {
            throw new AuthorizationException();
        }

        $this->subscriptionPolicy->assertCanAccess($subscription);

        try {
            if (! $this->mailOnlyService->domainUserExists($subscription, $username)) {
                return new JsonResponse([
                    'message' => $this->translator->translate('mail-providers.errors.username-does-not-exist'),
                    'errors' => [],
                ], Response::HTTP_NOT_FOUND);
            }

            $status = $this->mailOnlyService->deleteUser($subscription, $username);

            return new JsonResponse([
                'status' => $status,
            ]);
        } catch (Exception $exception) {
            $this->logger->error('Error during Mail account delete', [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                LoggingContextKeys::PRODUCT_SLUG => $subscription->product->slug,
                LoggingContextKeys::PRODUCT_ID => $subscription->product->id,
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => [
                    'mail-account' => $username,
                ],
            ]);

            return new JsonResponse([
                'message' => $this->translator->translate('mail-providers.errors.delete-user-error'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function forwards(
        HostingDeployment $hostingDeployment,
    ): JsonResponse {
        $subscription = $hostingDeployment->subscription;

        $this->subscriptionPolicy->assertCanManageHosting($subscription);

        if (! $subscription->product->isMailOnlyServer() && ! $subscription->product->isSitebuilderProduct()) {
            return new JsonResponse([
                'message' => $this->translator->translate('mail-providers.domain-does-not-exist'),
                'errors' => [],
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $listForwards = $this->mailOnlyService->getEmailForwardsFromDeployment($hostingDeployment);

            $forwards = [];

            foreach ($listForwards as $forward) {
                $forwards[] = [
                    'source' => $forward->getSource(),
                    'destinations' => $forward->getDestinations(),
                ];
            }

            return new JsonResponse(data: $forwards);
        } catch (EmailForwardException $exception) {
            $this->logger->error($exception->getMessage(), [LoggingContextKeys::EXCEPTION => $exception]);

            return new JsonResponse([
                'message' => $this->translator->translate('mail-providers.errors.forwards-error'),
                'errors' => [],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function createForward(
        CreateForwardRequest $request,
        HostingDeployment $hostingDeployment,
    ): JsonResponse {
        $subscription = $hostingDeployment->subscription;

        $this->subscriptionPolicy->assertCanManageHosting($subscription);

        if (! $subscription->product->isMailOnlyServer() && ! $subscription->product->isSitebuilderProduct()) {
            return new JsonResponse([
                'message' => $this->translator->translate('mail-providers.domain-does-not-exist'),
                'errors' => [],
            ], Response::HTTP_NOT_FOUND);
        }

        $source = $request->string('source')->toString();
        $destinations = $request->input('destinations');
        Assert::isArray($destinations);

        try {
            $this->mailOnlyService->createEmailForward(
                $hostingDeployment,
                $source,
                $destinations,
            );

            return new JsonResponse(status: Response::HTTP_CREATED);
        } catch (EmailForwardException $exception) {
            $this->logger->error($exception->getMessage(), [
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                LoggingContextKeys::META => [
                    'source' => $source,
                    'destinations' => $destinations,
                ],
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            return new JsonResponse([
                'message' => $this->translator->translate('mail-providers.errors.create-forward-error'),
                'errors' => [],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function deleteForward(
        HostingDeployment $hostingDeployment,
        string $source,
    ): JsonResponse {
        $subscription = $hostingDeployment->subscription;

        $this->subscriptionPolicy->assertCanManageHosting($subscription);

        if (! $subscription->product->isMailOnlyServer() && ! $subscription->product->isSitebuilderProduct()) {
            return new JsonResponse([
                'message' => $this->translator->translate('mail-providers.domain-does-not-exist'),
                'errors' => [],
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->mailOnlyService->deleteEmailForward(
                $hostingDeployment,
                $source,
            );

            return new JsonResponse(status: Response::HTTP_NO_CONTENT);
        } catch (EmailForwardException $exception) {
            $this->logger->error($exception->getMessage(), [LoggingContextKeys::EXCEPTION => $exception]);

            return new JsonResponse([
                'message' => $this->translator->translate('mail-providers.errors.delete-forward-error'),
                'errors' => [],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
