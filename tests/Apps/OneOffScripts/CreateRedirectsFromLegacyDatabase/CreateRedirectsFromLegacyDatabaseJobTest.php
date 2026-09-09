<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\CreateRedirectsFromLegacyDatabase;

use Exception;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Support\Collection;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\OneOffScripts\CreateRedirectsFromLegacyDatabase\CreateRedirectsFromLegacyDatabaseJob;
use Waterfront\Apps\OneOffScripts\CreateRedirectsFromLegacyDatabase\Exceptions\RedirectWithoutDomainException;
use Waterfront\Apps\OneOffScripts\CreateRedirectsFromLegacyDatabase\NovaCreateRedirectsFromLegacyDatabaseAction;
use Waterfront\Apps\OneOffScripts\CreateRedirectsFromLegacyDatabase\RedirectDnsRecordUpdater;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Models\CaddyContext;
use Waterfront\Domain\Provision\Redirects\Models\RedirectDeployment;
use Waterfront\Domain\Provision\Redirects\Repositories\RedirectContextRepository;
use Waterfront\Domain\Provision\Redirects\Repositories\RedirectDeploymentRepository;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Provision\Results\ProvisionResult;
use Waterfront\Domain\Redirects\Services\RedirectsDatabase\Redirect;
use Waterfront\Domain\Redirects\Services\RedirectsDatabase\RedirectDatabaseRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(CreateRedirectsFromLegacyDatabaseJob::class)]
class CreateRedirectsFromLegacyDatabaseJobTest extends IntegrationTestCase
{
    private LoggerInterface&MockObject $logger;

    private Factory&MockInterface $redisFactory;

    private RedirectContextRepository&MockObject $redirectContextRepository;

    private RedirectDeploymentRepository&MockObject $redirectDeploymentRepository;

    private RedirectDatabaseRepository&MockObject $legacyRepository;

    private ProvisionGateway&MockObject $gateway;

    private RedirectDnsRecordUpdater&MockObject $dnsUpdater;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->redirectContextRepository = $this->createMock(RedirectContextRepository::class);
        $this->redirectDeploymentRepository = $this->createMock(RedirectDeploymentRepository::class);
        $this->legacyRepository = $this->createMock(RedirectDatabaseRepository::class);
        $this->gateway = $this->createMock(ProvisionGateway::class);
        $this->dnsUpdater = $this->createMock(RedirectDnsRecordUpdater::class);
        $this->redisFactory = $this->mock(Factory::class);

        $this->app->bind(RedirectDatabaseRepository::class, fn () => $this->legacyRepository);
        $this->app->bind(ProvisionGateway::class, fn () => $this->gateway);
        $this->app->bind(RedirectDeploymentRepository::class, fn () => $this->redirectDeploymentRepository);
    }

    #[Test]
    public function throwsExceptionWhenSubscriptionHasNoDomain(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->redirect())
            ->createOne(['domain' => null]);

        $this->logger
            ->expects(self::never())
            ->method('info');

        $this->redirectContextRepository
            ->expects(self::never())
            ->method('findByContext');

        $this->redirectDeploymentRepository
            ->expects(self::never())
            ->method('findAllByContext');

        $this->legacyRepository
            ->expects(self::never())
            ->method('listRedirects');

        $this->gateway
            ->expects(self::never())
            ->method('request');

        $this->dnsUpdater
            ->expects(self::never())
            ->method('updateDnsRecordToCaddy');

        $this->redisFactory->expects('connection->sAdd')
            ->with(
                NovaCreateRedirectsFromLegacyDatabaseAction::REDIS_PROCESSED_KEY,
                $subscription->uuid
            )->never();

        $this->expectException(RedirectWithoutDomainException::class);

        $job = new CreateRedirectsFromLegacyDatabaseJob(
            dryRun: false,
            subscription: $subscription,
        );

        $job->handle(
            redirectContextRepository: $this->redirectContextRepository,
            logger: $this->logger,
            redirectDnsUpdater: $this->dnsUpdater,
            redisFactory: $this->redisFactory
        );
    }

    #[Test]
    public function createsNewContextAndProvisionNewRedirects(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->redirect())
            ->createOne(['domain' => 'example.com']);

        $contextUuid = Uuid::fromString($subscription->uuid);

        $this->redirectContextRepository
            ->expects(self::once())
            ->method('findByContext')
            ->with(self::equalTo($contextUuid))
            ->willReturn(null);

        $this->redirectDeploymentRepository
            ->expects(self::once())
            ->method('findAllByContext')
            ->with(self::equalTo($contextUuid))
            ->willReturn(new Collection());

        $legacyRedirects = [
            new Redirect(
                customerId: $subscription->customer_id,
                domainBody: 'example',
                tld: 'com',
                source: 'example.com',
                destination: 'https://destination.com',
                type: '301',
            ),
            new Redirect(
                customerId: $subscription->customer_id,
                domainBody: 'example',
                tld: 'com',
                source: 'sub.example.com',
                destination: 'https://other-destination.com',
                type: '302',
            ),
        ];

        $this->legacyRepository
            ->expects(self::once())
            ->method('listRedirects')
            ->with($subscription->customer_id, 'example.com')
            ->willReturn($legacyRedirects);

        $createRequest = new CreateRedirectRequest(
            domain: 'example.com',
            destinationUrl: 'https://destination.com',
            redirectType: RedirectType::PERMANENT,
            context: $contextUuid,
        );
        $createRequest->requestId = 1;

        $provisionResult = new ProvisionResult(
            provisionData: $createRequest,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $this->gateway
            ->expects(self::exactly(2))
            ->method('request')
            ->with(self::isInstanceOf(CreateRedirectRequest::class))
            ->willReturn($provisionResult);

        $this->dnsUpdater
            ->expects(self::exactly(2))
            ->method('updateDnsRecordToCaddy')
            ->with(...self::withConsecutive(['example.com', 'example.com'], ['example.com', 'sub.example.com']));

        $this->logger
            ->expects(self::exactly(3))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        'Created redirect context from database',
                        self::callback(function (array $context) use ($contextUuid): bool {
                            self::assertSame('example.com', $context[LoggingContextKeys::DOMAIN_NAME]);
                            self::assertEquals($contextUuid, $context[LoggingContextKeys::PROVISIONING_CONTEXT]);
                            self::assertSame(ProvisionProvider::CADDY, $context[LoggingContextKeys::PROVISIONING_PROVIDER]);
                            self::assertSame(ProvisionType::REDIRECT, $context[LoggingContextKeys::PROVISIONING_TYPE]);
                            self::assertSame(NovaCreateRedirectsFromLegacyDatabaseAction::SLUG, $context[LoggingContextKeys::ONE_OFF_SCRIPT]);
                            self::assertFalse($context[LoggingContextKeys::META]['dry-run']);
                            self::assertIsInt($context[LoggingContextKeys::META]['caddy_context_id']);

                            return true;
                        }),
                    ],
                    [
                        'Created redirect [example.com] --[301]--> [https://destination.com].',
                        [
                            LoggingContextKeys::DOMAIN_NAME => 'example.com',
                            LoggingContextKeys::PROVISIONING_CONTEXT => $contextUuid,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'provision_result' => ProvisionStatus::SUCCESS->value,
                                'provision_exception' => null,
                                'provision_request_id' => 1,
                                'provision_validation' => null,
                            ],
                        ],
                    ],
                    [
                        'Created redirect [sub.example.com] --[302]--> [https://other-destination.com].',
                        [
                            LoggingContextKeys::DOMAIN_NAME => 'example.com',
                            LoggingContextKeys::PROVISIONING_CONTEXT => $contextUuid,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'provision_result' => ProvisionStatus::SUCCESS->value,
                                'provision_exception' => null,
                                'provision_request_id' => 1,
                                'provision_validation' => null,
                            ],
                        ],
                    ],
                )
            );

        $this->redisFactory->expects('connection->sAdd')
            ->with(
                NovaCreateRedirectsFromLegacyDatabaseAction::REDIS_PROCESSED_KEY,
                $subscription->uuid
            )->once();

        $job = new CreateRedirectsFromLegacyDatabaseJob(
            dryRun: false,
            subscription: $subscription,
        );

        $job->handle(
            redirectContextRepository: $this->redirectContextRepository,
            logger: $this->logger,
            redirectDnsUpdater: $this->dnsUpdater,
            redisFactory: $this->redisFactory
        );
    }

    #[Test]
    public function reusesExistingContextWhenAlreadyCreated(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->redirect())
            ->createOne(['domain' => 'example.com']);

        $contextUuid = Uuid::fromString($subscription->uuid);

        $existingContext = new CaddyContext();
        $existingContext->id = 99;
        $existingContext->host = 'example.com';
        $existingContext->context_uuid = $contextUuid;

        $this->redirectContextRepository
            ->expects(self::once())
            ->method('findByContext')
            ->with(self::equalTo($contextUuid))
            ->willReturn($existingContext);

        $this->redirectDeploymentRepository
            ->expects(self::once())
            ->method('findAllByContext')
            ->with(self::equalTo($contextUuid))
            ->willReturn(new Collection());

        $this->legacyRepository
            ->expects(self::once())
            ->method('listRedirects')
            ->with($subscription->customer_id, 'example.com')
            ->willReturn([]);

        $this->gateway
            ->expects(self::never())
            ->method('request');

        $this->dnsUpdater
            ->expects(self::never())
            ->method('updateDnsRecordToCaddy');

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Context already exists for subscription, skipping context creation',
                [
                    LoggingContextKeys::DOMAIN_NAME => 'example.com',
                    LoggingContextKeys::PROVISIONING_CONTEXT => $contextUuid,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                    LoggingContextKeys::META => ['dry-run' => false],
                ],
            );

        $this->redisFactory->expects('connection->sAdd')
            ->with(
                NovaCreateRedirectsFromLegacyDatabaseAction::REDIS_PROCESSED_KEY,
                $subscription->uuid
            )->once();

        $job = new CreateRedirectsFromLegacyDatabaseJob(
            dryRun: false,
            subscription: $subscription,
        );

        $job->handle(
            redirectContextRepository: $this->redirectContextRepository,
            logger: $this->logger,
            redirectDnsUpdater: $this->dnsUpdater,
            redisFactory: $this->redisFactory
        );
    }

    #[Test]
    public function skipsExistingRedirectAndCreatesNewOne(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->redirect())
            ->createOne(['domain' => 'example.com']);

        $contextUuid = Uuid::fromString($subscription->uuid);

        $existingContext = new CaddyContext();
        $existingContext->id = 99;
        $existingContext->host = 'example.com';
        $existingContext->context_uuid = $contextUuid;

        $this->redirectContextRepository
            ->expects(self::once())
            ->method('findByContext')
            ->with(self::equalTo($contextUuid))
            ->willReturn($existingContext);

        $existingDeployment = new RedirectDeployment();
        $existingDeployment->forceFill(['source' => 'example.com']);

        $this->redirectDeploymentRepository
            ->expects(self::once())
            ->method('findAllByContext')
            ->with(self::equalTo($contextUuid))
            ->willReturn(new Collection([$existingDeployment]));

        $legacyRedirects = [
            new Redirect(
                customerId: $subscription->customer_id,
                domainBody: 'example',
                tld: 'com',
                source: 'example.com',
                destination: 'https://destination.com',
                type: '301',
            ),
            new Redirect(
                customerId: $subscription->customer_id,
                domainBody: 'example',
                tld: 'com',
                source: 'sub.example.com',
                destination: 'https://other-destination.com',
                type: '302',
            ),
        ];

        $this->legacyRepository
            ->expects(self::once())
            ->method('listRedirects')
            ->with($subscription->customer_id, 'example.com')
            ->willReturn($legacyRedirects);

        $createRequest = new CreateRedirectRequest(
            domain: 'sub.example.com',
            destinationUrl: 'https://other-destination.com',
            redirectType: RedirectType::TEMPORARY,
            context: $contextUuid,
        );
        $createRequest->requestId = 1;

        $provisionResult = new ProvisionResult(
            provisionData: $createRequest,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $this->gateway
            ->expects(self::once())
            ->method('request')
            ->with(self::isInstanceOf(CreateRedirectRequest::class))
            ->willReturn($provisionResult);

        $this->dnsUpdater
            ->expects(self::exactly(2))
            ->method('updateDnsRecordToCaddy')
            ->with(...self::withConsecutive(['example.com', 'example.com'], ['example.com', 'sub.example.com']));

        $this->logger
            ->expects(self::exactly(3))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        'Context already exists for subscription, skipping context creation',
                        [
                            LoggingContextKeys::DOMAIN_NAME => 'example.com',
                            LoggingContextKeys::PROVISIONING_CONTEXT => $contextUuid,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => ['dry-run' => false],
                        ],
                    ],
                    [
                        sprintf('Redirect with source [example.com] already exists for context [%s], skipping creation', $contextUuid),
                        [
                            LoggingContextKeys::DOMAIN_NAME => 'example.com',
                            LoggingContextKeys::PROVISIONING_CONTEXT => $contextUuid,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => ['dry-run' => false],
                        ],
                    ],
                    [
                        'Created redirect [sub.example.com] --[302]--> [https://other-destination.com].',
                        [
                            LoggingContextKeys::DOMAIN_NAME => 'example.com',
                            LoggingContextKeys::PROVISIONING_CONTEXT => $contextUuid,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'provision_result' => ProvisionStatus::SUCCESS->value,
                                'provision_exception' => null,
                                'provision_request_id' => 1,
                                'provision_validation' => null,
                            ],
                        ],
                    ],
                )
            );

        $this->redisFactory->expects('connection->sAdd')
            ->with(
                NovaCreateRedirectsFromLegacyDatabaseAction::REDIS_PROCESSED_KEY,
                $subscription->uuid
            )->once();

        $job = new CreateRedirectsFromLegacyDatabaseJob(
            dryRun: false,
            subscription: $subscription,
        );

        $job->handle(
            redirectContextRepository: $this->redirectContextRepository,
            logger: $this->logger,
            redirectDnsUpdater: $this->dnsUpdater,
            redisFactory: $this->redisFactory
        );
    }

    #[Test]
    public function updatesDnsForExistingRedirect(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->redirect())
            ->createOne(['domain' => 'example.com']);

        $contextUuid = Uuid::fromString($subscription->uuid);

        $existingContext = new CaddyContext();
        $existingContext->id = 99;
        $existingContext->host = 'example.com';
        $existingContext->context_uuid = $contextUuid;

        $this->redirectContextRepository
            ->expects(self::once())
            ->method('findByContext')
            ->willReturn($existingContext);

        $existingDeployment = new RedirectDeployment();
        $existingDeployment->forceFill(['source' => 'example.com']);

        $this->redirectDeploymentRepository
            ->expects(self::once())
            ->method('findAllByContext')
            ->willReturn(new Collection([$existingDeployment]));

        $legacyRedirects = [
            new Redirect(
                customerId: $subscription->customer_id,
                domainBody: 'example',
                tld: 'com',
                source: 'example.com',
                destination: 'https://destination.com',
                type: '301',
            ),
        ];

        $this->legacyRepository
            ->expects(self::once())
            ->method('listRedirects')
            ->willReturn($legacyRedirects);

        $this->gateway
            ->expects(self::never())
            ->method('request');

        $this->dnsUpdater
            ->expects(self::once())
            ->method('updateDnsRecordToCaddy')
            ->with('example.com', 'example.com');

        $this->logger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        'Context already exists for subscription, skipping context creation',
                        [
                            LoggingContextKeys::DOMAIN_NAME => 'example.com',
                            LoggingContextKeys::PROVISIONING_CONTEXT => $contextUuid,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => ['dry-run' => false],
                        ],
                    ],
                    [
                        sprintf('Redirect with source [example.com] already exists for context [%s], skipping creation', $contextUuid),
                        [
                            LoggingContextKeys::DOMAIN_NAME => 'example.com',
                            LoggingContextKeys::PROVISIONING_CONTEXT => $contextUuid,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => ['dry-run' => false],
                        ],
                    ],
                )
            );

        $this->redisFactory->expects('connection->sAdd')
        ->with(
            NovaCreateRedirectsFromLegacyDatabaseAction::REDIS_PROCESSED_KEY,
            $subscription->uuid
        );

        $job = new CreateRedirectsFromLegacyDatabaseJob(
            dryRun: false,
            subscription: $subscription,
        );

        $job->handle(
            redirectContextRepository: $this->redirectContextRepository,
            logger: $this->logger,
            redirectDnsUpdater: $this->dnsUpdater,
            redisFactory: $this->redisFactory
        );
    }

    #[Test]
    public function updatesDnsForNewRedirect(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->redirect())
            ->createOne(['domain' => 'example.com']);

        $contextUuid = Uuid::fromString($subscription->uuid);

        $existingContext = new CaddyContext();
        $existingContext->id = 99;
        $existingContext->host = 'example.com';
        $existingContext->context_uuid = $contextUuid;

        $this->redirectContextRepository
            ->expects(self::once())
            ->method('findByContext')
            ->willReturn($existingContext);

        $this->redirectDeploymentRepository
            ->expects(self::once())
            ->method('findAllByContext')
            ->willReturn(new Collection());

        $legacyRedirects = [
            new Redirect(
                customerId: $subscription->customer_id,
                domainBody: 'example',
                tld: 'com',
                source: 'example.com',
                destination: 'https://destination.com',
                type: '301',
            ),
        ];

        $this->legacyRepository
            ->expects(self::once())
            ->method('listRedirects')
            ->willReturn($legacyRedirects);

        $createRequest = new CreateRedirectRequest(
            domain: 'example.com',
            destinationUrl: 'https://destination.com',
            redirectType: RedirectType::PERMANENT,
            context: $contextUuid,
        );
        $createRequest->requestId = 1;

        $provisionResult = new ProvisionResult(
            provisionData: $createRequest,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $this->gateway
            ->expects(self::once())
            ->method('request')
            ->willReturn($provisionResult);

        $this->dnsUpdater
            ->expects(self::once())
            ->method('updateDnsRecordToCaddy')
            ->with('example.com', 'example.com');

        $this->logger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        'Context already exists for subscription, skipping context creation',
                        [
                            LoggingContextKeys::DOMAIN_NAME => 'example.com',
                            LoggingContextKeys::PROVISIONING_CONTEXT => $contextUuid,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => ['dry-run' => false],
                        ],
                    ],
                    [
                        'Created redirect [example.com] --[301]--> [https://destination.com].',
                        [
                            LoggingContextKeys::DOMAIN_NAME => 'example.com',
                            LoggingContextKeys::PROVISIONING_CONTEXT => $contextUuid,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'provision_result' => ProvisionStatus::SUCCESS->value,
                                'provision_exception' => null,
                                'provision_request_id' => 1,
                                'provision_validation' => null,
                            ],
                        ],
                    ],
                )
            );

        $this->redisFactory->expects('connection->sAdd')
            ->with(
                NovaCreateRedirectsFromLegacyDatabaseAction::REDIS_PROCESSED_KEY,
                $subscription->uuid
            )->once();

        $job = new CreateRedirectsFromLegacyDatabaseJob(
            dryRun: false,
            subscription: $subscription,
        );

        $job->handle(
            redirectContextRepository: $this->redirectContextRepository,
            logger: $this->logger,
            redirectDnsUpdater: $this->dnsUpdater,
            redisFactory: $this->redisFactory
        );
    }

    #[Test]
    public function throwsExceptionWhenProvisioningFails(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->redirect())
            ->createOne(['domain' => 'example.com']);

        $contextUuid = Uuid::fromString($subscription->uuid);

        $existingContext = new CaddyContext();
        $existingContext->id = 42;
        $existingContext->host = 'example.com';
        $existingContext->context_uuid = $contextUuid;

        $this->redirectContextRepository
            ->expects(self::once())
            ->method('findByContext')
            ->willReturn($existingContext);

        $this->redirectDeploymentRepository
            ->expects(self::once())
            ->method('findAllByContext')
            ->willReturn(new Collection());

        $legacyRedirects = [
            new Redirect(
                customerId: $subscription->customer_id,
                domainBody: 'example',
                tld: 'com',
                source: 'example.com',
                destination: 'https://destination.com',
                type: '301',
            ),
        ];

        $this->legacyRepository
            ->expects(self::once())
            ->method('listRedirects')
            ->willReturn($legacyRedirects);

        $createRequest = new CreateRedirectRequest(
            domain: 'example.com',
            destinationUrl: 'https://destination.com',
            redirectType: RedirectType::PERMANENT,
            context: $contextUuid,
        );
        $createRequest->requestId = 1;

        $provisionResult = new ProvisionResult(
            provisionData: $createRequest,
            provisionStatus: ProvisionStatus::FAILED,
            exception: new Exception('Provisioning error'),
        );

        $this->gateway
            ->expects(self::once())
            ->method('request')
            ->willReturn($provisionResult);

        $this->dnsUpdater
            ->expects(self::never())
            ->method('updateDnsRecordToCaddy');

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Context already exists for subscription, skipping context creation',
                [
                    LoggingContextKeys::DOMAIN_NAME => 'example.com',
                    LoggingContextKeys::PROVISIONING_CONTEXT => $contextUuid,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                    LoggingContextKeys::META => ['dry-run' => false],
                ],
            );

        $this->redisFactory->expects('connection->sAdd')
            ->with(
                NovaCreateRedirectsFromLegacyDatabaseAction::REDIS_PROCESSED_KEY,
                $subscription->uuid
            )->never();

        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs('Provisioning error');

        $job = new CreateRedirectsFromLegacyDatabaseJob(
            dryRun: false,
            subscription: $subscription,
        );

        $job->handle(
            redirectContextRepository: $this->redirectContextRepository,
            logger: $this->logger,
            redirectDnsUpdater: $this->dnsUpdater,
            redisFactory: $this->redisFactory
        );
    }

    #[Test]
    public function mapsLegacyFrameRedirectTypeCorrectly(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->redirect())
            ->createOne(['domain' => 'example.com']);

        $contextUuid = Uuid::fromString($subscription->uuid);

        $existingContext = new CaddyContext();
        $existingContext->id = 42;
        $existingContext->host = 'example.com';
        $existingContext->context_uuid = $contextUuid;

        $this->redirectContextRepository
            ->expects(self::once())
            ->method('findByContext')
            ->willReturn($existingContext);

        $this->redirectDeploymentRepository
            ->expects(self::once())
            ->method('findAllByContext')
            ->willReturn(new Collection());

        $legacyRedirects = [
            new Redirect(
                customerId: $subscription->customer_id,
                domainBody: 'example',
                tld: 'com',
                source: 'example.com',
                destination: 'https://destination.com',
                type: 'frame',
            ),
        ];

        $this->legacyRepository
            ->expects(self::once())
            ->method('listRedirects')
            ->willReturn($legacyRedirects);

        $createRequest = new CreateRedirectRequest(
            domain: 'example.com',
            destinationUrl: 'https://destination.com',
            redirectType: RedirectType::FRAME,
            context: $contextUuid,
        );
        $createRequest->requestId = 1;

        $provisionResult = new ProvisionResult(
            provisionData: $createRequest,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $this->gateway
            ->expects(self::once())
            ->method('request')
            ->with(self::callback(function (CreateRedirectRequest $request): bool {
                self::assertSame(RedirectType::FRAME, $request->redirectType);

                return true;
            }))
            ->willReturn($provisionResult);

        $this->dnsUpdater
            ->expects(self::once())
            ->method('updateDnsRecordToCaddy')
            ->with('example.com', 'example.com');

        $this->logger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        'Context already exists for subscription, skipping context creation',
                        [
                            LoggingContextKeys::DOMAIN_NAME => 'example.com',
                            LoggingContextKeys::PROVISIONING_CONTEXT => $contextUuid,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => ['dry-run' => false],
                        ],
                    ],
                    [
                        'Created redirect [example.com] --[frame]--> [https://destination.com].',
                        [
                            LoggingContextKeys::DOMAIN_NAME => 'example.com',
                            LoggingContextKeys::PROVISIONING_CONTEXT => $contextUuid,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'provision_result' => ProvisionStatus::SUCCESS->value,
                                'provision_exception' => null,
                                'provision_request_id' => 1,
                                'provision_validation' => null,
                            ],
                        ],
                    ],
                )
            );

        $this->redisFactory->expects('connection->sAdd')
            ->with(
                NovaCreateRedirectsFromLegacyDatabaseAction::REDIS_PROCESSED_KEY,
                $subscription->uuid
            )->once();

        $job = new CreateRedirectsFromLegacyDatabaseJob(
            dryRun: false,
            subscription: $subscription,
        );

        $job->handle(
            redirectContextRepository: $this->redirectContextRepository,
            logger: $this->logger,
            redirectDnsUpdater: $this->dnsUpdater,
            redisFactory: $this->redisFactory
        );
    }
}
