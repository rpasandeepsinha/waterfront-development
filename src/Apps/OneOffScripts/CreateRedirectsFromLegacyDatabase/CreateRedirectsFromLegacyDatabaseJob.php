<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\CreateRedirectsFromLegacyDatabase;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Contracts\Redis\Factory;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Apps\OneOffScripts\CreateRedirectsFromLegacyDatabase\Exceptions\RedirectWithoutDomainException;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Models\CaddyContext;
use Waterfront\Domain\Provision\Redirects\Repositories\RedirectContextRepository;
use Waterfront\Domain\Provision\Redirects\Repositories\RedirectDeploymentRepository;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Redirects\Services\RedirectsDatabase\Redirect;
use Waterfront\Domain\Redirects\Services\RedirectsDatabase\RedirectDatabaseRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class CreateRedirectsFromLegacyDatabaseJob extends AbstractQueueableJob
{
    private LoggerInterface $logger;

    private RedirectDnsRecordUpdater $dnsUpdater;

    private RedirectContextRepository $redirectContextRepository;

    public function __construct(
        private readonly bool $dryRun,
        public readonly Subscription $subscription,
    ) {
        parent::__construct();
    }

    /**
     * @throws RedirectWithoutDomainException
     * @throws Exception
     */
    public function handle(
        RedirectContextRepository $redirectContextRepository,
        LoggerInterface $logger,
        RedirectDnsRecordUpdater $redirectDnsUpdater,
        Factory $redisFactory,
    ): void {
        $this->logger = $logger;
        $this->redirectContextRepository = $redirectContextRepository;

        $this->dnsUpdater = $redirectDnsUpdater;
        $this->dnsUpdater->dryRun = $this->dryRun;

        $domain = $this->subscription->domain;

        if ($domain === null) {
            throw new RedirectWithoutDomainException(
                message: sprintf(
                    'Redirect subscription id:[%d] - uuid: [%s] has no domain',
                    $this->subscription->id,
                    $this->subscription->uuid,
                ),
            );
        }

        $caddyContext = $this->findOrCreateContext($domain);
        $this->createRedirectsFromLegacy($caddyContext);

        $redisFactory->connection()->sAdd(
            NovaCreateRedirectsFromLegacyDatabaseAction::REDIS_PROCESSED_KEY,
            $this->subscription->uuid,
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::ONE_TIME_SCRIPTS;
    }

    private function createRedirectsFromLegacy(CaddyContext $caddyContext): void
    {
        $domain = $caddyContext->host;
        $context = $caddyContext->context_uuid;

        $gateway = Container::getInstance()->make(ProvisionGateway::class);
        $legacyRepository = Container::getInstance()->make(RedirectDatabaseRepository::class);
        $redirectDeploymentRepository = Container::getInstance()->make(RedirectDeploymentRepository::class);

        $legacyRedirects = $legacyRepository->listRedirects(
            customerId: $this->subscription->customer_id,
            domain: $domain,
        );

        $existingRedirects = $redirectDeploymentRepository->findAllByContext($context)->pluck('source')->toArray();

        foreach ($legacyRedirects as $legacyRedirect) {
            if (in_array($legacyRedirect->source, $existingRedirects, true)) {
                $this->handleExistingRedirect(legacyRedirect: $legacyRedirect, domain: $domain, context: $context);
                continue;
            }

            $redirectString = sprintf(
                '[%s] --[%s]--> [%s].',
                $legacyRedirect->source,
                $legacyRedirect->type,
                $legacyRedirect->destination,
            );

            $createRequest = new CreateRedirectRequest(
                domain: $legacyRedirect->source,
                destinationUrl: $legacyRedirect->destination,
                redirectType: $this->mapLegacyType($legacyRedirect->type),
                context: $context,
            );

            if (! $this->dryRun) {
                $result = $gateway->request($createRequest);

                if ($result->failed) {
                    throw $result->exception ?? new Exception('Unable to provision new redirect' . $redirectString);
                }
            }

            $this->logger->info(
                'Created redirect ' . $redirectString,
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                    LoggingContextKeys::META => [
                        'dry-run' => $this->dryRun,
                        'provision_result' => $this->dryRun ? 'N/A' : $result->provisionStatus->value,
                        'provision_exception' => $this->dryRun ? 'N/A' : $result->exception?->getMessage(),
                        'provision_request_id' => $this->dryRun ? 'N/A' : $result->provisionData->requestId,
                        'provision_validation' => $this->dryRun ? 'N/A' : $result->validationResult,
                    ],
                ],
            );

            $this->dnsUpdater->updateDnsRecordToCaddy(rootDomain: $domain, source: $legacyRedirect->source);
        }
    }

    private function mapLegacyType(string $legacyType): RedirectType
    {
        return match ($legacyType) {
            '301' => RedirectType::PERMANENT,
            '302' => RedirectType::TEMPORARY,
            'frame', 'iframe' => RedirectType::FRAME,
            default => RedirectType::TEMPORARY,
        };
    }

    private function findOrCreateContext(string $host): CaddyContext
    {
        $context = Uuid::fromString($this->subscription->uuid);
        $caddyContext = $this->redirectContextRepository->findByContext($context);

        if ($caddyContext !== null) {
            $this->logger->info('Context already exists for subscription, skipping context creation', [
                LoggingContextKeys::DOMAIN_NAME => $host,
                LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                LoggingContextKeys::META => ['dry-run' => $this->dryRun],
            ]);

            return $caddyContext;
        }

        $caddyContext = new CaddyContext();
        $caddyContext->context_uuid = $context;
        $caddyContext->host = $host;

        if (! $this->dryRun) {
            $caddyContext->save();
        }

        $this->logger->info('Created redirect context from database', [
            LoggingContextKeys::DOMAIN_NAME => $host,
            LoggingContextKeys::PROVISIONING_CONTEXT => $context,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
            LoggingContextKeys::META => [
                'dry-run' => $this->dryRun,
                'caddy_context_id' => $this->dryRun ? 'N/A' : $caddyContext->id,
            ],
        ]);

        return $caddyContext;
    }

    private function handleExistingRedirect(Redirect $legacyRedirect, string $domain, UuidInterface $context): void
    {
        $this->logger->info(
            sprintf(
                'Redirect with source [%s] already exists for context [%s], skipping creation',
                $legacyRedirect->source,
                $context,
            ),
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                LoggingContextKeys::META => ['dry-run' => $this->dryRun],
            ],
        );

        $this->dnsUpdater->updateDnsRecordToCaddy(rootDomain: $domain, source: $legacyRedirect->source);
    }
}
