<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Services;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use UnexpectedValueException;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Exceptions\ListRedirectsException;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\DeleteRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\ListRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\TerminateRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UpdateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Results\ListRedirectResult;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;
use Waterfront\Domain\Redirects\Enums\DnsRedirectProvisionOption;
use Waterfront\Domain\Redirects\Services\RedirectsDatabase\Redirect;
use Waterfront\Domain\Redirects\Services\RedirectsDatabase\RedirectsRepositoryInterface;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Support\Enums\LoggingContextKeys;

class RedirectService implements RedirectServiceInterface
{
    public function __construct(
        private readonly RedirectsRepositoryInterface $redirects,
        private readonly ProvisionGateway $provisionGateway,
        private readonly LoggerInterface $logger,
        private readonly RedirectDnsServiceInterface $redirectDnsService,
        private readonly PublicSuffixList $publicSuffixList
    ) {
    }

    /** @return Redirect[] */
    public function index(int $customerId, string $domain): array
    {
        return $this->redirects->listRedirects($customerId, $domain);
    }

    public function add(int $customerId, string $source, string $target, RedirectType $type): Redirect
    {
        Log::info('Redirects.RedirectService: Adding redirect', [
            LoggingContextKeys::CUSTOMER_ID => $customerId,
            LoggingContextKeys::META => [
                'from' => $source,
                'to' => $target,
                'type' => $type->value,
            ],
        ]);
        return $this->redirects->createRedirect(
            $customerId,
            $source,
            $target,
            $type->value
        );
    }

    public function remove(int $customerId, string $source): void
    {
        Log::info('Redirects.RedirectService: Removing redirect', [
            LoggingContextKeys::CUSTOMER_ID => $customerId,
            LoggingContextKeys::META => [
                'from' => $source,
            ],
        ]);
        $this->redirects->deleteRedirect($customerId, $source);
    }

    public function update(int $customerId, string $source, string $target, RedirectType $type): Redirect
    {
        Log::info('Redirects.RedirectService: Updating redirect', [
            LoggingContextKeys::CUSTOMER_ID => $customerId,
            LoggingContextKeys::META => [
                'from' => $source,
                'to' => $target,
                'type' => $type->value,
            ],
        ]);
        return $this->redirects->updateRedirect(
            $customerId,
            $source,
            $target,
            $type->value
        );
    }

    public function isSourceUnique(int $customerId, string $source): bool
    {
        return $this->redirects->isRedirectSourceUnique($customerId, $source);
    }

    /**
     * @throws ListRedirectsException
     *
     * @return list<array{source: string, target: string, type: string}>
     */
    public function listRedirects(Subscription $subscription): array
    {
        if (! $subscription->product->isRedirectProduct()) {
            throw new ListRedirectsException(
                contextUuid: Uuid::fromString($subscription->uuid),
                message: sprintf(
                    'Can only list redirects from redirect subscription, received %s from subscription [%s].',
                    $subscription->product->slug,
                    $subscription->uuid
                )
            );
        }

        $listRedirectsRequest = new ListRedirectsRequest(
            context: Uuid::fromString($subscription->uuid)
        );

        $listRedirectResult = $this->provisionGateway->request($listRedirectsRequest);

        if (! $listRedirectResult instanceof ListRedirectResult || $listRedirectResult->failed) {
            $this->logger->warning(
                sprintf('Redirect get list failed for subscription uuid %s', $subscription->uuid),
                $this->getContextFromSubscriptionAndResult($subscription, $listRedirectResult)
            );

            throw new ListRedirectsException(Uuid::fromString($subscription->uuid), previous: $listRedirectResult->exception);
        }

        $result = [];
        foreach ($listRedirectResult->redirects ?? [] as $redirect) {
            if ($redirect->failed) {
                $this->logger->warning(
                    sprintf('Could not load redirect from subscription uuid %s.', $subscription->uuid),
                );
                continue;
            }

            $result[] = [
                // @phpstan-ignore property.nonObject (PHPStan doesn't support parent::$prop::get() yet, see phpstan/phpstan#12336)
                'source' => $redirect->redirect->source,
                // @phpstan-ignore property.nonObject (PHPStan doesn't support parent::$prop::get() yet, see phpstan/phpstan#12336)
                'target' => $redirect->redirect->destination,
                // @phpstan-ignore property.nonObject (PHPStan doesn't support parent::$prop::get() yet, see phpstan/phpstan#12336)
                'type' => $redirect->redirect->redirectType->value,
            ];
        }

        return $result;
    }

    public function createRedirect(Subscription $subscription, string $domain, string $destination, RedirectType $redirectType): RedirectResult
    {
        $createRedirectRequest = new CreateRedirectRequest(
            domain: $domain,
            destinationUrl: $destination,
            redirectType: $redirectType,
            context: Uuid::fromString($subscription->uuid)
        );

        $redirectCreateResult = $this->provisionGateway->request($createRedirectRequest);

        if (! $redirectCreateResult instanceof RedirectResult || $redirectCreateResult->failed) {
            $this->logger->warning(
                sprintf('Redirect create failed for subscription uuid %s', $subscription->uuid),
                $this->getContextFromSubscriptionAndResult($subscription, $redirectCreateResult)
            );

            return new RedirectResult(
                provisionData: $redirectCreateResult->provisionData,
                provisionStatus: $redirectCreateResult->provisionStatus,
                exception: $redirectCreateResult->exception,
                validationResult: $redirectCreateResult->validationResult
            );
        }

        $this->logger->debug(
            sprintf('Redirect create succeeded for subscription uuid %s', $subscription->uuid),
            $this->getContextFromSubscriptionAndResult($subscription, $redirectCreateResult)
        );

        $sourceHost = $this->getRedirectSourceHost($domain);
        $this->redirectDnsService->provisionDnsRecords($this->getBaseDomainFromHost($sourceHost), $sourceHost, DnsRedirectProvisionOption::OVERRIDE);

        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->save();

        return $redirectCreateResult;
    }

    public function updateRedirect(Subscription $subscription, string $oldSource, string $newSource, string $destination, RedirectType $redirectType): RedirectResult
    {
        if ($oldSource !== $newSource) {
            $this->deleteRedirect($subscription, $oldSource);
            return $this->createRedirect($subscription, $oldSource, $newSource, $redirectType);
        }

        $updateRedirectRequest = new UpdateRedirectRequest(
            oldSource: $oldSource,
            newSource: $newSource,
            destinationUrl: $destination,
            redirectType: $redirectType,
            context: Uuid::fromString($subscription->uuid)
        );

        $redirectUpdateResult = $this->provisionGateway->request($updateRedirectRequest);

        if (! $redirectUpdateResult instanceof RedirectResult || $redirectUpdateResult->failed) {
            $this->logger->warning(
                sprintf('Redirect update failed for subscription uuid %s', $subscription->uuid),
                $this->getContextFromSubscriptionAndResult($subscription, $redirectUpdateResult)
            );

            return new RedirectResult(
                provisionData: $redirectUpdateResult->provisionData,
                provisionStatus: $redirectUpdateResult->provisionStatus,
                exception: $redirectUpdateResult->exception,
                validationResult: $redirectUpdateResult->validationResult
            );
        }

        $this->logger->debug(
            sprintf('Redirect update succeeded for subscription uuid %s', $subscription->uuid),
            $this->getContextFromSubscriptionAndResult($subscription, $redirectUpdateResult)
        );

        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->save();

        return $redirectUpdateResult;
    }

    public function deleteRedirect(Subscription $subscription, string $domain): RedirectResult
    {
        $deleteRedirectRequest = new DeleteRedirectRequest(
            domainName: $domain,
            context: Uuid::fromString($subscription->uuid)
        );

        $redirectDeleteResult = $this->provisionGateway->request($deleteRedirectRequest);

        if (! $redirectDeleteResult instanceof RedirectResult || $redirectDeleteResult->failed) {
            $this->logger->warning(
                sprintf('Redirect delete failed for subscription uuid %s', $subscription->uuid),
                $this->getContextFromSubscriptionAndResult($subscription, $redirectDeleteResult)
            );

            return new RedirectResult(
                provisionData: $redirectDeleteResult->provisionData,
                provisionStatus: $redirectDeleteResult->provisionStatus,
                exception: $redirectDeleteResult->exception,
                validationResult: $redirectDeleteResult->validationResult
            );
        }

        $this->logger->debug(
            sprintf('Redirect delete succeeded for subscription uuid %s', $subscription->uuid),
            $this->getContextFromSubscriptionAndResult($subscription, $redirectDeleteResult)
        );

        $sourceHost = $this->getRedirectSourceHost($domain);
        $this->redirectDnsService->cleanupDnsRecords($this->getBaseDomainFromHost($sourceHost), $sourceHost);

        $subscription->technical_status = TechnicalStatus::DELETED->value;
        $subscription->save();

        return $redirectDeleteResult;
    }

    public function terminateRedirect(Subscription $subscription): RedirectResult
    {
        $listRedirects = $this->listRedirects($subscription);
        foreach ($listRedirects as $redirect) {
            $this->deleteRedirect($subscription, $redirect['source']);
        }

        $terminateRedirectRequest = new TerminateRedirectsRequest(
            context: Uuid::fromString($subscription->uuid)
        );

        $terminateDeleteResult = $this->provisionGateway->request($terminateRedirectRequest);

        if (! $terminateDeleteResult instanceof RedirectResult || $terminateDeleteResult->failed) {
            $this->logger->warning(
                sprintf('Redirect terminate failed for subscription uuid %s', $subscription->uuid),
                $this->getContextFromSubscriptionAndResult($subscription, $terminateDeleteResult)
            );

            $subscription->technical_status = TechnicalStatus::FAILED->value;
            $subscription->save();

            return new RedirectResult(
                provisionData: $terminateDeleteResult->provisionData,
                provisionStatus: $terminateDeleteResult->provisionStatus,
                exception: $terminateDeleteResult->exception,
                validationResult: $terminateDeleteResult->validationResult
            );
        }

        $this->logger->debug(
            sprintf('Redirect terminate succeeded for subscription uuid %s', $subscription->uuid),
            $this->getContextFromSubscriptionAndResult($subscription, $terminateDeleteResult)
        );

        $subscription->technical_status = TechnicalStatus::DELETED->value;
        $subscription->save();

        return $terminateDeleteResult;
    }

    /**
     * @return array<LoggingContextKeys, mixed>
     */
    private function getContextFromSubscriptionAndResult(Subscription $subscription, ProvisionResultInterface $result): array
    {
        $exception = $result->exception;

        $context = [
            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
            LoggingContextKeys::PROVISIONING_REQUEST_ID => $result->provisionData->requestId,
            LoggingContextKeys::META => [
                'provision_result' => $result->provisionStatus->value,
                'provision_exception' => $exception?->getMessage(),
                'provision_validation' => $result->validationResult,
            ],
        ];

        if ($exception !== null) {
            $context[LoggingContextKeys::EXCEPTION] = $exception;
        }

        return $context;
    }

    private function getBaseDomainFromHost(string $sourceHost): string
    {
        return $this->publicSuffixList->getRegistrableDomain($sourceHost)
            ?? throw new UnexpectedValueException("Cannot parse domain of redirect source host: $sourceHost");
    }

    private function getRedirectSourceHost(string $source): string
    {
        return $this->publicSuffixList->getHostFromUrlOrDomain($source)
            ?? throw new UnexpectedValueException("Cannot parse host of redirect source: $source");
    }
}
