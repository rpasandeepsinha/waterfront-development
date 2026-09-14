<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Redirects;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\RedirectController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Services\DnsLogService;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Redirects\DTO\Redirect as RedirectDTO;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\DeleteRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Requests\ListRedirectsRequest;
use Waterfront\Domain\Provision\Redirects\Requests\UpdateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Results\GetRedirectResult;
use Waterfront\Domain\Provision\Redirects\Results\ListRedirectResult;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(RedirectController::class)]
#[AllowMockObjectsWithoutExpectations]
class RedirectTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    public const string DOMAIN = 'testdomain.nl';

    private Customer $customer;

    private Subscription $subscription;

    private ProvisionGateway&MockObject $mockProvisionGateway;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $redirectProduct = ProductFactory::new()->redirect()->createOne();

        $this->subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $redirectProduct->uuid,
            'domain' => self::DOMAIN,
        ]);

        $mockDnsLogService = self::createStub(DnsLogService::class);
        $this->app->bind(DnsLogService::class, fn (): DnsLogService => $mockDnsLogService);
        $this->mockProvisionGateway = self::createMock(ProvisionGateway::class);
        $this->app->bind(ProvisionGateway::class, fn (): ProvisionGateway => $this->mockProvisionGateway);
    }

    #[Test]
    public function index(): void
    {
        $source = 'in.testdomain.nl';
        $target = 'https://out.testdomain.nl';
        $type = RedirectType::TEMPORARY;

        $listRedirectResult = new ListRedirectResult(
            provisionData: new ListRedirectsRequest(context: Uuid::fromString($this->subscription->uuid)),
            provisionStatus: ProvisionStatus::SUCCESS,
            redirects: [
                new GetRedirectResult(
                    provisionData: new ListRedirectsRequest(context: Uuid::fromString($this->subscription->uuid)),
                    provisionStatus: ProvisionStatus::SUCCESS,
                    redirect: new RedirectDTO(
                        source: $source,
                        destination: $target,
                        redirectType: $type,
                    ),
                ),
            ],
        );

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (ListRedirectsRequest $request) => $request->context->toString() === $this->subscription->uuid,
                ),
            )
            ->willReturn($listRedirectResult);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.redirects.getDeployment', [
                    'subscription' => $this->subscription,
                ]),
            )
            ->assertOk()
            ->assertExactJson([
                'administrative_subscription_uuid' => $this->subscription->uuid,
                'forwards' => [
                    [
                        'source' => $source,
                        'target' => $target,
                        'type' => $type->value,
                    ],
                ],
            ]);
    }

    #[Test]
    public function indexNoRedirects(): void
    {
        $listRedirectResult = new ListRedirectResult(
            provisionData: new ListRedirectsRequest(context: Uuid::fromString($this->subscription->uuid)),
            provisionStatus: ProvisionStatus::SUCCESS,
            redirects: [],
        );

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (ListRedirectsRequest $request) => $request->context->toString() === $this->subscription->uuid,
                ),
            )
            ->willReturn($listRedirectResult);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.redirects.getDeployment', [
                    'subscription' => $this->subscription,
                ]),
            )
            ->assertOk()
            ->assertExactJson([
                'administrative_subscription_uuid' => $this->subscription->uuid,
                'forwards' => [],
            ]);
    }

    #[Test]
    public function indexUnauthorized(): void
    {
        $this->actingAsCustomer(new CustomerFactory()->createOne())
            ->getJson(
                $this->generateRoute('partners.redirects.getDeployment', [
                    'subscription' => $this->subscription,
                ]),
            )
            ->assertForbidden();
    }

    #[Test]
    public function store(): void
    {
        $source = 'in.testdomain.nl';
        $target = 'https://out.testdomain.nl';
        $type = RedirectType::TEMPORARY;

        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                201,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
        ]);

        $this->pdns($pdns);

        $provisionData = new CreateRedirectRequest(
            domain: $source,
            destinationUrl: $target,
            redirectType: $type,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $provisionData->requestId = 1;

        $redirectResult = new RedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->willReturnCallback(function (mixed $request) use (
                $redirectResult,
                $source,
                $target,
                $type,
            ): RedirectResult {
                self::assertInstanceOf(CreateRedirectRequest::class, $request);
                self::assertSame($this->subscription->uuid, $request->context->toString());
                self::assertSame($source, $request->domain);
                self::assertSame($target, $request->destinationUrl);
                self::assertSame($type, $request->redirectType);

                return $redirectResult;
            });

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.redirects.store', [
                    'subscription' => $this->subscription,
                ]),
                [
                    'source' => $source,
                    'target' => $target,
                    'type' => $type->value,
                ],
            )
            ->assertCreated()
            ->assertExactJson([
                'data' => [
                    [
                        'source' => $source,
                        'target' => $target,
                        'type' => $type->value,
                    ],
                ],
                'errors' => [],
            ]);
    }

    #[Test]
    public function storeFailed(): void
    {
        $source = 'in.testdomain.nl';
        $target = 'https://out.testdomain.nl';
        $type = RedirectType::TEMPORARY;

        $provisionData = new CreateRedirectRequest(
            domain: $source,
            destinationUrl: $target,
            redirectType: $type,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $provisionData->requestId = 1;

        $redirectResult = new RedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::FAILED,
        );

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->willReturnCallback(function (mixed $request) use (
                $redirectResult,
                $source,
                $target,
                $type,
            ): RedirectResult {
                self::assertInstanceOf(CreateRedirectRequest::class, $request);
                self::assertSame($this->subscription->uuid, $request->context->toString());
                self::assertSame($source, $request->domain);
                self::assertSame($target, $request->destinationUrl);
                self::assertSame($type, $request->redirectType);

                return $redirectResult;
            });

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.redirects.store', [
                    'subscription' => $this->subscription,
                ]),
                [
                    'source' => $source,
                    'target' => $target,
                    'type' => $type->value,
                ],
            )
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => self::resolve(TranslatorInterface::class)->translate('redirects.create-failed'),
                'errors' => [],
            ]);
    }

    #[Test]
    public function storeUnauthorized(): void
    {
        $customer = new CustomerFactory()->createOne();
        $this->actingAsCustomer($customer)
            ->postJson(
                $this->generateRoute('partners.redirects.store', [
                    'subscription' => $this->subscription,
                ]),
                [
                    'source' => 'subdomain.testdomain.nl',
                    'target' => 'https://newdomainhere.nl/',
                    'type' => RedirectType::TEMPORARY->value,
                ],
            )
            ->assertForbidden();
    }

    #[Test]
    public function storeInvalid(): void
    {
        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.redirects.store', [
                    'subscription' => $this->subscription,
                ]),
                [
                    'source' => 'subdomain',
                    'target' => 'https/newdomainhere.nl/',
                    'type' => RedirectType::TEMPORARY->value,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'errors' => [
                    'source' => [
                        self::resolve(TranslatorInterface::class)->translate('validation.domain_name'),
                    ],
                    'target' => [
                        'Dit veld is geen geldige link.',
                    ],
                ],
            ]);
    }

    #[Test]
    public function updateDifferentSources(): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                201,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
        ]);

        $this->pdns($pdns);

        $oldSource = 'subdomain.testdomain.nl';
        $newSource = 'in.testdomain.nl';
        $newTarget = 'https://out2.testdomain.nl';
        $newType = RedirectType::PERMANENT;

        $deleteProvisionData = new DeleteRedirectRequest(
            domainName: $oldSource,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $deleteProvisionData->requestId = 1;

        $deleteResult = new RedirectResult(
            provisionData: $deleteProvisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $createProvisionData = new CreateRedirectRequest(
            domain: $oldSource,
            destinationUrl: $newSource,
            redirectType: $newType,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $createProvisionData->requestId = 1;

        $createResult = new RedirectResult(
            provisionData: $createProvisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $this->mockProvisionGateway
            ->expects(self::exactly(2))
            ->method('request')
            ->willReturnCallback(function (mixed $request) use (
                $deleteResult,
                $createResult,
                $oldSource,
                $newSource,
                $newType,
            ): RedirectResult {
                if ($request instanceof DeleteRedirectRequest) {
                    self::assertSame($oldSource, $request->domainName);
                    self::assertEquals(Uuid::fromString($this->subscription->uuid), $request->context);

                    return $deleteResult;
                }

                self::assertInstanceOf(CreateRedirectRequest::class, $request);
                self::assertSame($oldSource, $request->domain);
                self::assertSame($newSource, $request->destinationUrl);
                self::assertSame($newType, $request->redirectType);

                return $createResult;
            });

        $this->actingAsCustomer($this->customer)
            ->patchJson(
                $this->generateRoute('partners.redirects.update', [
                    'subscription' => $this->subscription,
                ]),
                [
                    'old' => [
                        'source' => $oldSource,
                        'target' => 'https://out.testdomain.nl',
                        'type' => RedirectType::TEMPORARY->value,
                    ],
                    'new' => [
                        'source' => $newSource,
                        'target' => $newTarget,
                        'type' => $newType->value,
                    ],
                ],
            )
            ->assertOk()
            ->assertExactJson([
                'message' => 'success',
                'errors' => [],
            ]);
    }

    #[Test]
    public function updateSameSources(): void
    {
        $source = 'subdomain.testdomain.nl';
        $newTarget = 'https://out2.testdomain.nl';
        $newType = RedirectType::PERMANENT;

        $provisionData = new UpdateRedirectRequest(
            oldSource: $source,
            newSource: $source,
            destinationUrl: $newTarget,
            redirectType: $newType,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $provisionData->requestId = 1;

        $redirectResult = new RedirectResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (UpdateRedirectRequest $request) => (
                        $request->context->toString() === $this->subscription->uuid
                        && $request->oldSource === $source
                        && $request->newSource === $source
                        && $request->destinationUrl === $newTarget
                        && $request->redirectType === $newType
                    ),
                ),
            )
            ->willReturn($redirectResult);

        $this->actingAsCustomer($this->customer)
            ->patchJson(
                $this->generateRoute('partners.redirects.update', [
                    'subscription' => $this->subscription,
                ]),
                [
                    'old' => [
                        'source' => $source,
                        'target' => 'https://out.testdomain.nl',
                        'type' => RedirectType::TEMPORARY->value,
                    ],
                    'new' => [
                        'source' => $source,
                        'target' => $newTarget,
                        'type' => $newType->value,
                    ],
                ],
            )
            ->assertOk()
            ->assertExactJson([
                'message' => 'success',
                'errors' => [],
            ]);
    }

    #[Test]
    public function updateFailed(): void
    {
        $source = 'subdomain.testdomain.nl';
        $newTarget = 'https://out2.testdomain.nl';
        $newType = RedirectType::PERMANENT;

        $provisionData = new UpdateRedirectRequest(
            oldSource: $source,
            newSource: $source,
            destinationUrl: $newTarget,
            redirectType: $newType,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $provisionData->requestId = 1;

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (UpdateRedirectRequest $request) => (
                        $request->context->toString() === $this->subscription->uuid
                        && $request->oldSource === $source
                        && $request->newSource === $source
                        && $request->destinationUrl === $newTarget
                        && $request->redirectType === $newType
                    ),
                ),
            )
            ->willReturn(new RedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
            ));

        $this->actingAsCustomer($this->customer)
            ->patchJson(
                $this->generateRoute('partners.redirects.update', [
                    'subscription' => $this->subscription,
                ]),
                [
                    'old' => [
                        'source' => $source,
                        'target' => 'https://out.testdomain.nl',
                        'type' => RedirectType::TEMPORARY->value,
                    ],
                    'new' => [
                        'source' => $source,
                        'target' => $newTarget,
                        'type' => $newType->value,
                    ],
                ],
            )
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => self::resolve(TranslatorInterface::class)->translate('redirects.update-failed'),
                'errors' => [],
            ]);
    }

    #[Test]
    public function updateUnauthorized(): void
    {
        $customer = new CustomerFactory()->createOne();
        $this->actingAsCustomer($customer)
            ->patchJson(
                $this->generateRoute('partners.redirects.update', [
                    'subscription' => $this->subscription,
                ]),
                [
                    'old' => [
                        'source' => 'subdomain.testdomain.nl',
                        'target' => 'https://olddomainhere.nl/',
                        'type' => RedirectType::TEMPORARY->value,
                    ],
                    'new' => [
                        'source' => 'seconddomain.testdomain.nl',
                        'target' => 'https://newdomainhere.nl/',
                        'type' => RedirectType::PERMANENT->value,
                    ],
                ],
            )
            ->assertForbidden();
    }

    #[Test]
    public function updateInvalid(): void
    {
        $this->actingAsCustomer($this->customer)
            ->patchJson(
                $this->generateRoute('partners.redirects.update', [
                    'subscription' => $this->subscription,
                ]),
                [
                    'old' => [
                        'source' => 'subdomain.testdomain.nl',
                    ],
                    'new' => [
                        'source' => 'seconddomain',
                        'target' => 'https:/newdomainhere.nl/',
                    ],
                ],
            )
            ->assertJsonFragment([
                'errors' => [
                    'old.target' => [
                        self::resolve(TranslatorInterface::class)->translate('validation.required'),
                    ],
                    'new.source' => [
                        self::resolve(TranslatorInterface::class)->translate('validation.domain_name'),
                    ],
                    'new.target' => [
                        'Dit veld is geen geldige link.',
                    ],
                ],
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function destroy(): void
    {
        $source = 'in.testdomain.nl';

        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::DOMAIN),
            ),
        ]);

        $this->pdns($pdns);

        $provisionData = new DeleteRedirectRequest(
            domainName: $source,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $provisionData->requestId = 1;

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (DeleteRedirectRequest $request) => (
                        $request->context->toString() === $this->subscription->uuid
                        && $request->domainName === $source
                    ),
                ),
            )
            ->willReturn(new RedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::SUCCESS,
            ));

        $this->actingAsCustomer($this->customer)
            ->deleteJson(
                $this->generateRoute('partners.redirects.destroy', [
                    'subscription' => $this->subscription,
                ]),
                [
                    'source' => $source,
                ],
            )
            ->assertOk();
    }

    #[Test]
    public function destroyFailed(): void
    {
        $source = 'in.testdomain.nl';

        $provisionData = new DeleteRedirectRequest(
            domainName: $source,
            context: Uuid::fromString($this->subscription->uuid),
        );
        $provisionData->requestId = 1;

        $this->mockProvisionGateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (DeleteRedirectRequest $request) => (
                        $request->context->toString() === $this->subscription->uuid
                        && $request->domainName === $source
                    ),
                ),
            )
            ->willReturn(new RedirectResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
            ));

        $this->actingAsCustomer($this->customer)
            ->deleteJson(
                $this->generateRoute('partners.redirects.destroy', [
                    'subscription' => $this->subscription,
                ]),
                [
                    'source' => $source,
                ],
            )
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => self::resolve(TranslatorInterface::class)->translate('redirects.delete-failed'),
                'errors' => [],
            ]);
    }

    #[Test]
    public function destroyUnauthorized(): void
    {
        $customer = new CustomerFactory()->createOne();
        $this->actingAsCustomer($customer)
            ->deleteJson(
                $this->generateRoute('partners.redirects.destroy', [
                    'subscription' => $this->subscription,
                ]),
                [
                    'source' => 'subdomain.testdomain.nl',
                ],
            )
            ->assertForbidden();
    }
}
