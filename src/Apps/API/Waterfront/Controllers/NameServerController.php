<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use JsonException;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Apps\API\Waterfront\Requests\NameServer\UpdateRequest;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Exceptions\DomainDoesNotExistException;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\OpenproviderClient\Exceptions\OpenProviderResultException;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Exceptions\NotImplementedException;

class NameServerController
{
    public function __construct(
        private readonly SubscriptionPolicy $subscriptionPolicy,
        private readonly TranslatorInterface $translator,
        private readonly DomainService $domainService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function show(Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanManageNameservers($subscription);

        $this->subscriptionPolicy->assertCanView($subscription);

        /** @var DomainDeployment $domainDeployment */
        $domainDeployment = $subscription->domainDeployment;

        try {
            $result = $this->domainService->checkNameservers($domainDeployment);

            return new JsonResponse([
                'data' => [
                    'nameservers' => $result->getNameServers(),
                    'nameservergroup' => $result->getNsGroup(),
                    'isDefaultNameservers' => $result->getIsDefaultNameservers(),
                ],
            ]);
        } catch (NotImplementedException) {
            // We will return an empty success data, the frontend will not show this if
            // a placeholder provider is being used, it will show the migration warning
            return new JsonResponse([
                'data' => [
                    'nameservers' => [],
                    'nameservergroup' => '',
                    'isDefaultNameservers' => false,
                ],
            ]);
        } catch (RealtimeRegisterClientException|OpenProviderResultException|DomainDoesNotExistException $e) {
            return new JsonResponse([
                'reason' => $e->getMessage(),
                'nameservers' => [],
                'nameservergroup' => null,
                'isDefaultNameservers' => null,
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    /**
     * @throws JsonException
     */
    public function update(UpdateRequest $request, Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanManageNameservers($subscription);

        $this->subscriptionPolicy->assertCanManageDomain($subscription);

        $nameservers = $this->getNameServersFromRequest($request);

        /** @var DomainDeployment $domainDeployment */
        $domainDeployment = $subscription->domainDeployment;

        $success = $this->domainService->setCustomNameservers(
            $domainDeployment,
            $nameservers,
        );

        return new JsonResponse([$this->translator->translate('status.success') => $success]);
    }

    public function reset(Subscription $subscription): JsonResponse
    {
        $this->subscriptionPolicy->assertCanManageNameservers($subscription);

        $this->subscriptionPolicy->assertCanManageDomain($subscription);

        /** @var DomainDeployment $domainDeployment */
        $domainDeployment = $subscription->domainDeployment;

        try {
            $resetNameServersSuccess = $this->domainService->resetNameServersToInternal($domainDeployment);
        } catch (Exception $exception) {
            $this->logger->error('Failed to reset nameservers for domain {domain}', [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                LoggingContextKeys::EXCEPTION => $exception,
            ]);

            $resetNameServersSuccess = false;
        }

        return new JsonResponse([
            $this->translator->translate('status.success') => $resetNameServersSuccess,
        ]);
    }

    /**
     * @return Nameserver[]
     */
    private function getNameServersFromRequest(UpdateRequest $request): array
    {
        $validated = $request->validated();
        /** @var array<array{name: ?string, ip: ?string, ip6: ?string}> $nameserversFromRequest */
        $nameserversFromRequest = $validated['nameServers'];

        $nameservers = array_filter($nameserversFromRequest, fn ($ns) => $ns['name'] !== null);

        return array_map(fn ($ns) => new Nameserver($ns['name'], $ns['ip'] ?? null, $ns['ip6'] ?? null), $nameservers);
    }
}
