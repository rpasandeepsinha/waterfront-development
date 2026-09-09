<?php

declare(strict_types=1);

namespace Tests\Domain\Redirects\Services;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use UnexpectedValueException;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Redirects\DTO\Redirect as RedirectDTO;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Exceptions\ListRedirectsException;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\DeleteRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\ListRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\TerminateRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UpdateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Results\GetRedirectResult;
use Waterfront\Domain\Provision\Redirects\Results\ListRedirectResult;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;
use Waterfront\Domain\Redirects\Enums\DnsRedirectProvisionOption;
use Waterfront\Domain\Redirects\Services\RedirectDnsServiceInterface;
use Waterfront\Domain\Redirects\Services\RedirectsDatabase\Redirect;
use Waterfront\Domain\Redirects\Services\RedirectsDatabase\RedirectsRepositoryInterface;
use Waterfront\Domain\Redirects\Services\RedirectService;
use Waterfront\Domain\Redirects\Services\RedirectServiceInterface;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(RedirectsRepositoryInterface::class)]
#[AllowMockObjectsWithoutExpectations]
class RedirectServiceTest extends IntegrationTestCase
{
    private Subscription $subscription;

    private ProvisionGateway&MockObject $mockProvisionGateway;

    private LoggerInterface&MockObject $mockLogger;

    private RedirectDnsServiceInterface&MockObject $mockRedirectDnsService;

    private PublicSuffixList&MockObject $publicSuffixList;

    private RedirectService $redirectService;

    public function setUp(): void
    {
        parent::setUp();

        $redirectProduct = ProductFactory::new()->redirect()->createOne();
        $this->subscription = new SubscriptionFactory()
            ->technicalStatusOk()
            ->for($redirectProduct)
            ->withCustomer()
            ->createOne();

        $this->mockProvisionGateway = self::createMock(ProvisionGateway::class);
        $this->mockLogger = self::createMock(LoggerInterface::class);
        $this->mockRedirectDnsService = self::createMock(RedirectDnsServiceInterface::class);
        $this->publicSuffixList = self::createMock(PublicSuffixList::class);

        $this->redirectService = new RedirectService(
            redirects: self::resolve(RedirectsRepositoryInterface::class),
            provisionGateway: $this->mockProvisionGateway,
            logger: $this->mockLogger,
            redirectDnsService: $this->mockRedirectDnsService,
            publicSuffixList: $this->publicSuffixList
        );
    }

    #[Test]
    public function list(): void
    {
        $mock = self::createMock(RedirectsRepositoryInterface::class);
        $mock->expects(self::once())->method('listRedirects')->willReturn([
            new Redirect(1, 'test.nl', 'nl', 'in.test.nl', 'https://out.test.nl', RedirectType::TEMPORARY->value),
        ]);
        $this->app->bind(RedirectsRepositoryInterface::class, fn (): RedirectsRepositoryInterface => $mock);

        self::assertCount(1, self::resolve(RedirectServiceInterface::class)->index(1, 'test.nl'));
    }

    #[Test]
    public function create(): void
    {
        $mock = self::createMock(RedirectsRepositoryInterface::class);
        $mock->expects(self::once())->method('createRedirect')->willReturn(
            new Redirect(1, 'test.nl', 'nl', 'in.test.nl', 'https://out.test.nl', RedirectType::TEMPORARY->value)
        );
        $this->app->bind(RedirectsRepositoryInterface::class, fn (): RedirectsRepositoryInterface => $mock);

        self::assertInstanceOf(Redirect::class, self::resolve(RedirectServiceInterface::class)->add(1, 'in.test.nl', 'https://out.test.nl', RedirectType::TEMPORARY));
    }

    #[Test]
    public function update(): void
    {
        $mock = self::createMock(RedirectsRepositoryInterface::class);
        $mock->expects(self::once())->method('updateRedirect')->willReturn(
            new Redirect(1, 'test.nl', 'nl', 'in.test.nl', 'https://out.test.nl', RedirectType::TEMPORARY->value)
        );
        $this->app->bind(RedirectsRepositoryInterface::class, fn (): RedirectsRepositoryInterface => $mock);

        self::assertInstanceOf(Redirect::class, self::resolve(RedirectServiceInterface::class)->update(1, 'in.test.nl', 'https://out.test.nl', RedirectType::TEMPORARY));
    }

    #[Test]
    public function remove(): void
    {
        $mock = self::createMock(RedirectsRepositoryInterface::class);
        $mock->expects(self::once())->method('deleteRedirect');
        $this->app->bind(RedirectsRepositoryInterface::class, fn (): RedirectsRepositoryInterface => $mock);

        self::resolve(RedirectServiceInterface::class)->remove(1, 'in.test.nl');
    }

    #[Test]
    public function listRedirects(): void
    {
        $source = 'test.com';
        $destination = 'yourhosting.com';
        $redirectType = RedirectType::TEMPORARY;

        $listRedirectResult = new ListRedirectResult(
            provisionData: new ListRedirectsRequest(context: Uuid::fromString($this->subscription->uuid)),
            provisionStatus: ProvisionStatus::SUCCESS,
            redirects: [
                new GetRedirectResult(
                    provisionData: new ListRedirectsRequest(context: Uuid::fromString($this->subscription->uuid)),
                    provisionStatus: ProvisionStatus::SUCCESS,
                    redirect: new RedirectDTO(
                        source: $source,
                        destination: $destination,
                        redirectType: $redirectType,
                    ),
                ),
            ]
        );

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (ListRedirectsRequest $request) => $request->context->toString() === $this->subscription->uuid
                )
            )
            ->willReturn($listRedirectResult);

        $result = $this->redirectService->listRedirects($this->subscription);

        self::assertCount(1, $result);
        self::assertSame($source, $result[0]['source']);
        self::assertSame($destination, $result[0]['target']);
        self::assertSame($redirectType->value, $result[0]['type']);
    }

    #[Test]
    public function listRedirectsFailed(): void
    {
        $provisionData = new ListRedirectsRequest(context: Uuid::fromString($this->subscription->uuid));
        $provisionData->requestId = 1;

        $listRedirectResult = new ListRedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::FAILED
        );

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (ListRedirectsRequest $request) => $request->context->toString() === $this->subscription->uuid
                )
            )
            ->willReturn($listRedirectResult);

        $this->mockLogger->expects(self::once())
            ->method('warning')
            ->with(
                sprintf('Redirect get list failed for subscription uuid %s', $this->subscription->uuid),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => $provisionData->requestId,
                    LoggingContextKeys::META => [
                        'provision_result' => $listRedirectResult->provisionStatus->value,
                        'provision_exception' => $listRedirectResult->exception?->getMessage(),
                        'provision_validation' => $listRedirectResult->validationResult,
                    ],
                ]
            );

        self::expectException(ListRedirectsException::class);

        $this->redirectService->listRedirects($this->subscription);
    }

    #[Test]
    public function createRedirect(): void
    {
        $domain = 'test.nl';
        $destinationUrl = 'https://out.test.nl';
        $redirectType = RedirectType::PERMANENT;

        $provisionData = new CreateRedirectRequest(
            domain: $domain,
            destinationUrl: $destinationUrl,
            redirectType: $redirectType,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $provisionData->requestId = 1;

        $redirectCreateResult = new RedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->willReturnCallback(function (mixed $request) use ($redirectCreateResult, $domain, $destinationUrl, $redirectType): RedirectResult {
                self::assertInstanceOf(CreateRedirectRequest::class, $request);
                self::assertSame($this->subscription->uuid, $request->context->toString());
                self::assertSame($domain, $request->domain);
                self::assertSame($destinationUrl, $request->destinationUrl);
                self::assertSame($redirectType, $request->redirectType);
                return $redirectCreateResult;
            });

        $this->publicSuffixList
            ->expects(self::once())
            ->method('getHostFromUrlOrDomain')
            ->with($domain)
            ->willReturn($domain);

        $this->publicSuffixList
            ->expects(self::once())
            ->method('getRegistrableDomain')
            ->with($domain)
            ->willReturn($domain);

        $result = $this->redirectService->createRedirect($this->subscription, $domain, $destinationUrl, $redirectType);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        $this->subscription->refresh();
        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
    }

    #[Test]
    public function createRedirectWithPathAndQueryProvisionsDnsForHost(): void
    {
        $source = 'test.caddy-redirect.nl/test?utm_source=newsletter';
        $sourceHost = 'test.caddy-redirect.nl';
        $baseDomain = 'caddy-redirect.nl';
        $destinationUrl = 'https://google.nl';
        $redirectType = RedirectType::PERMANENT;

        $provisionData = new CreateRedirectRequest(
            domain: $source,
            destinationUrl: $destinationUrl,
            redirectType: $redirectType,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $provisionData->requestId = 1;

        $redirectCreateResult = new RedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->willReturnCallback(function (mixed $request) use ($redirectCreateResult, $source, $destinationUrl, $redirectType): RedirectResult {
                self::assertInstanceOf(CreateRedirectRequest::class, $request);
                self::assertSame($source, $request->domain);
                self::assertSame($destinationUrl, $request->destinationUrl);
                self::assertSame($redirectType, $request->redirectType);
                return $redirectCreateResult;
            });

        $this->publicSuffixList
            ->expects(self::once())
            ->method('getHostFromUrlOrDomain')
            ->with($source)
            ->willReturn($sourceHost);

        $this->publicSuffixList
            ->expects(self::once())
            ->method('getRegistrableDomain')
            ->with($sourceHost)
            ->willReturn($baseDomain);

        $this->mockRedirectDnsService
            ->expects(self::once())
            ->method('provisionDnsRecords')
            ->with($baseDomain, $sourceHost, DnsRedirectProvisionOption::OVERRIDE);

        $result = $this->redirectService->createRedirect($this->subscription, $source, $destinationUrl, $redirectType);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        $this->subscription->refresh();
        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
    }

    #[Test]
    public function createRedirectFailed(): void
    {
        $domain = 'test.nl';
        $destinationUrl = 'https://out.test.nl';
        $redirectType = RedirectType::PERMANENT;

        $provisionData = new CreateRedirectRequest(
            domain: $domain,
            destinationUrl: $destinationUrl,
            redirectType: $redirectType,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $provisionData->requestId = 1;

        $redirectCreateResult = new RedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::FAILED,
        );

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->willReturnCallback(function (mixed $request) use ($redirectCreateResult): RedirectResult {
                self::assertInstanceOf(CreateRedirectRequest::class, $request);
                self::assertSame($this->subscription->uuid, $request->context->toString());
                return $redirectCreateResult;
            });

        $this->mockLogger->expects(self::once())
            ->method('warning')
            ->with(
                sprintf('Redirect create failed for subscription uuid %s', $this->subscription->uuid),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => $provisionData->requestId,
                    LoggingContextKeys::META => [
                        'provision_result' => $redirectCreateResult->provisionStatus->value,
                        'provision_exception' => $redirectCreateResult->exception?->getMessage(),
                        'provision_validation' => $redirectCreateResult->validationResult,
                    ],
                ]
            );

        $result = $this->redirectService->createRedirect($this->subscription, $domain, $destinationUrl, $redirectType);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        $this->subscription->refresh();

        // If a single redirect create fails, it doesn't mean the entire redirect technical status has failed
        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
    }

    #[Test]
    public function updateRedirect(): void
    {
        $oldSource = 'test.nl';
        $newSource = 'test.nl';
        $destinationUrl = 'https://out.test.nl';
        $redirectType = RedirectType::PERMANENT;

        $provisionData = new UpdateRedirectRequest(
            oldSource: $oldSource,
            newSource: $newSource,
            destinationUrl: $destinationUrl,
            redirectType: $redirectType,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $provisionData->requestId = 1;

        $redirectUpdateResult = new RedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (UpdateRedirectRequest $request) => $request->context->toString() === $this->subscription->uuid
                        && $request->oldSource === $oldSource
                        && $request->newSource === $newSource
                        && $request->destinationUrl === $destinationUrl
                        && $request->redirectType === $redirectType
                )
            )
            ->willReturn($redirectUpdateResult);

        $result = $this->redirectService->updateRedirect($this->subscription, $oldSource, $newSource, $destinationUrl, $redirectType);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        $this->subscription->refresh();
        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
    }

    #[Test]
    public function updateRedirectFailed(): void
    {
        $oldSource = 'test.nl';
        $newSource = 'test.nl';
        $destinationUrl = 'https://out.test.nl';
        $redirectType = RedirectType::PERMANENT;

        $provisionData = new UpdateRedirectRequest(
            oldSource: $oldSource,
            newSource: $newSource,
            destinationUrl: $destinationUrl,
            redirectType: $redirectType,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $provisionData->requestId = 1;

        $redirectUpdateResult = new RedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::FAILED,
        );

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (UpdateRedirectRequest $request) => $request->context->toString() === $this->subscription->uuid
                )
            )
            ->willReturn($redirectUpdateResult);

        $this->mockLogger->expects(self::once())
            ->method('warning')
            ->with(
                sprintf('Redirect update failed for subscription uuid %s', $this->subscription->uuid),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => $provisionData->requestId,
                    LoggingContextKeys::META => [
                        'provision_result' => $redirectUpdateResult->provisionStatus->value,
                        'provision_exception' => $redirectUpdateResult->exception?->getMessage(),
                        'provision_validation' => $redirectUpdateResult->validationResult,
                    ],
                ]
            );

        $result = $this->redirectService->updateRedirect($this->subscription, $oldSource, $newSource, $destinationUrl, $redirectType);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        $this->subscription->refresh();

        // If a redirect update fails, it doesn't mean the entire redirect technical status has failed
        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
    }

    #[Test]
    public function deleteRedirect(): void
    {
        $domain = 'test.nl';

        $provisionData = new DeleteRedirectRequest(
            domainName: $domain,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $provisionData->requestId = 1;

        $redirectDeleteResult = new RedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (DeleteRedirectRequest $request) => $request->context->toString() === $this->subscription->uuid
                        && $request->domainName === $domain
                )
            )
            ->willReturn($redirectDeleteResult);

        $this->publicSuffixList
            ->expects(self::once())
            ->method('getHostFromUrlOrDomain')
            ->with($domain)
            ->willReturn($domain);

        $this->publicSuffixList
            ->expects(self::once())
            ->method('getRegistrableDomain')
            ->with($domain)
            ->willReturn($domain);

        $result = $this->redirectService->deleteRedirect($this->subscription, $domain);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        $this->subscription->refresh();
        self::assertSame(TechnicalStatus::DELETED->value, $this->subscription->technical_status);
    }

    #[Test]
    public function deleteRedirectWithPathAndQueryCleansDnsForHost(): void
    {
        $source = 'test.caddy-redirect.nl/test?utm_source=newsletter';
        $sourceHost = 'test.caddy-redirect.nl';
        $baseDomain = 'caddy-redirect.nl';

        $provisionData = new DeleteRedirectRequest(
            domainName: $source,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $provisionData->requestId = 1;

        $redirectDeleteResult = new RedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->willReturnCallback(function (mixed $request) use ($redirectDeleteResult, $source): RedirectResult {
                self::assertInstanceOf(DeleteRedirectRequest::class, $request);
                self::assertSame($source, $request->domainName);
                self::assertSame($this->subscription->uuid, $request->context->toString());
                return $redirectDeleteResult;
            });

        $this->publicSuffixList
            ->expects(self::once())
            ->method('getHostFromUrlOrDomain')
            ->with($source)
            ->willReturn($sourceHost);

        $this->publicSuffixList
            ->expects(self::once())
            ->method('getRegistrableDomain')
            ->with($sourceHost)
            ->willReturn($baseDomain);

        $this->mockRedirectDnsService
            ->expects(self::once())
            ->method('cleanupDnsRecords')
            ->with($baseDomain, $sourceHost);

        $result = $this->redirectService->deleteRedirect($this->subscription, $source);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        $this->subscription->refresh();
        self::assertSame(TechnicalStatus::DELETED->value, $this->subscription->technical_status);
    }

    #[Test]
    public function deleteRedirectFailed(): void
    {
        $domain = 'test.nl';

        $provisionData = new DeleteRedirectRequest(
            domainName: $domain,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $provisionData->requestId = 1;

        $redirectDeleteResult = new RedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::FAILED,
        );

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (DeleteRedirectRequest $request) => $request->context->toString() === $this->subscription->uuid
                )
            )
            ->willReturn($redirectDeleteResult);

        $this->mockLogger->expects(self::once())
            ->method('warning')
            ->with(
                sprintf('Redirect delete failed for subscription uuid %s', $this->subscription->uuid),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => $provisionData->requestId,
                    LoggingContextKeys::META => [
                        'provision_result' => $redirectDeleteResult->provisionStatus->value,
                        'provision_exception' => $redirectDeleteResult->exception?->getMessage(),
                        'provision_validation' => $redirectDeleteResult->validationResult,
                    ],
                ]
            );

        $result = $this->redirectService->deleteRedirect($this->subscription, $domain);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        $this->subscription->refresh();

        // If a single redirect delete fails, it doesn't mean the entire redirect technical status has failed
        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
    }

    #[Test]
    public function terminateRedirect(): void
    {
        $source = 'test.nl';
        $destination = 'yourhosting.nl';
        $redirectType = RedirectType::TEMPORARY;

        $listProvisionData = new ListRedirectsRequest(context: Uuid::fromString($this->subscription->uuid));
        $listProvisionData->requestId = 1;

        $listRedirectResult = new ListRedirectResult(
            provisionData: $listProvisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
            redirects: [
                new GetRedirectResult(
                    provisionData: $listProvisionData,
                    provisionStatus: ProvisionStatus::SUCCESS,
                    redirect: new RedirectDTO(
                        source: $source,
                        destination: $destination,
                        redirectType: $redirectType,
                    ),
                ),
            ]
        );

        $deleteProvisionData = new DeleteRedirectRequest(
            domainName: $source,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $deleteProvisionData->requestId = 2;

        $deleteRedirectResult = new RedirectResult(
            provisionData: $deleteProvisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $terminateProvisionData = new TerminateRedirectsRequest(
            context: Uuid::fromString($this->subscription->uuid),
        );
        $terminateProvisionData->requestId = 3;

        $terminateResult = new RedirectResult(
            provisionData: $terminateProvisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $this->mockProvisionGateway
            ->expects(self::exactly(3))
            ->method('request')
            ->willReturnCallback(fn ($request) => match (true) {
                $request instanceof ListRedirectsRequest => $listRedirectResult,
                $request instanceof DeleteRedirectRequest => $deleteRedirectResult,
                $request instanceof TerminateRedirectsRequest => $terminateResult,
                default => throw new UnexpectedValueException('Unexpected request type: ' . $request::class),
            });

        $this->publicSuffixList
            ->expects(self::once())
            ->method('getHostFromUrlOrDomain')
            ->with($source)
            ->willReturn($source);

        $this->publicSuffixList
            ->expects(self::once())
            ->method('getRegistrableDomain')
            ->with($source)
            ->willReturn($source);

        $result = $this->redirectService->terminateRedirect($this->subscription);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        $this->subscription->refresh();
        self::assertSame(TechnicalStatus::DELETED->value, $this->subscription->technical_status);
    }

    #[Test]
    public function terminateRedirectFailed(): void
    {
        $source = 'test.nl';
        $destination = 'yourhosting.nl';
        $redirectType = RedirectType::TEMPORARY;

        $listProvisionData = new ListRedirectsRequest(context: Uuid::fromString($this->subscription->uuid));
        $listProvisionData->requestId = 1;

        $listRedirectResult = new ListRedirectResult(
            provisionData: $listProvisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
            redirects: [
                new GetRedirectResult(
                    provisionData: $listProvisionData,
                    provisionStatus: ProvisionStatus::SUCCESS,
                    redirect: new RedirectDTO(
                        source: $source,
                        destination: $destination,
                        redirectType: $redirectType,
                    ),
                ),
            ]
        );

        $deleteProvisionData = new DeleteRedirectRequest(
            domainName: $source,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $deleteProvisionData->requestId = 2;

        $deleteRedirectResult = new RedirectResult(
            provisionData: $deleteProvisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $terminateProvisionData = new TerminateRedirectsRequest(
            context: Uuid::fromString($this->subscription->uuid),
        );
        $terminateProvisionData->requestId = 3;

        $terminateResult = new RedirectResult(
            provisionData: $terminateProvisionData,
            provisionStatus: ProvisionStatus::FAILED,
        );

        $this->mockProvisionGateway
            ->expects(self::exactly(3))
            ->method('request')
            ->willReturnCallback(fn ($request) => match (true) {
                $request instanceof ListRedirectsRequest => $listRedirectResult,
                $request instanceof DeleteRedirectRequest => $deleteRedirectResult,
                $request instanceof TerminateRedirectsRequest => $terminateResult,
                default => throw new UnexpectedValueException('Unexpected request type: ' . $request::class),
            });

        $this->publicSuffixList
            ->expects(self::once())
            ->method('getHostFromUrlOrDomain')
            ->with($source)
            ->willReturn($source);

        $this->publicSuffixList
            ->expects(self::once())
            ->method('getRegistrableDomain')
            ->with($source)
            ->willReturn($source);

        $this->mockLogger->expects(self::once())
            ->method('warning')
            ->with(
                sprintf('Redirect terminate failed for subscription uuid %s', $this->subscription->uuid),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => $terminateProvisionData->requestId,
                    LoggingContextKeys::META => [
                        'provision_result' => $terminateResult->provisionStatus->value,
                        'provision_exception' => $terminateResult->exception?->getMessage(),
                        'provision_validation' => $terminateResult->validationResult,
                    ],
                ]
            );

        $result = $this->redirectService->terminateRedirect($this->subscription);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        $this->subscription->refresh();
        self::assertSame(TechnicalStatus::FAILED->value, $this->subscription->technical_status);
    }
}
