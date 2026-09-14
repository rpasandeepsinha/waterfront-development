<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\DNS\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\DNS\Actions\NovaResetDnsTemplateAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Provision\Redirects\Exceptions\ListRedirectsException;
use Waterfront\Domain\Redirects\Enums\DnsRedirectProvisionOption;
use Waterfront\Domain\Redirects\Services\RedirectDnsServiceInterface;
use Waterfront\Domain\Redirects\Services\RedirectServiceInterface;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaResetDnsTemplateAction::class)]
#[AllowMockObjectsWithoutExpectations]
class NovaResetDnsTemplateActionTest extends IntegrationTestCase
{
    public const string DOMAIN = 'sandwave-test.com';

    private Subscription $domainSubscription;

    private Subscription $redirectSubscription;

    private DomainDeployment $domainDeployment;

    private DnsDeployment $dnsDeployment;

    private Subscription $dnsSubscription;

    private Subscription $hostingSubscription;

    private HostingDeployment $hostingDeployment;

    private Dispatcher&MockObject $mockDispatcher;

    private HostingDeploymentRepository $hostingDeploymentRepository;

    private RedirectServiceInterface&MockObject $mockRedirectService;

    private RedirectDnsServiceInterface&MockObject $mockRedirectDnsService;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();

        $this->domainSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain(self::DOMAIN)
            ->createOne();

        $this->domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($this->domainSubscription)
            ->createOne();

        $this->dnsSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->dns())->premiumDns())
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain(self::DOMAIN)
            ->parentSubscription($this->domainSubscription)
            ->createOne();

        $this->dnsDeployment = new DnsDeploymentFactory()->for($this->dnsSubscription)->createOne();

        $this->hostingSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting())->hostingBrons())
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain(self::DOMAIN)
            ->createOne();

        $this->hostingDeployment = new HostingDeploymentFactory()->for($this->hostingSubscription)->createOne();

        $this->mockDispatcher = self::createMock(Dispatcher::class);
        $this->hostingDeploymentRepository = self::resolve(HostingDeploymentRepository::class);

        $this->mockRedirectService = self::createMock(RedirectServiceInterface::class);
        $this->mockRedirectDnsService = self::createMock(RedirectDnsServiceInterface::class);

        $this->redirectSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->redirect())
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain(self::DOMAIN)
            ->createOne();
    }

    #[Test]
    public function noConfirmation(): void
    {
        $result = $this->runAction($this->dnsSubscription, false);

        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.confirmation_checkbox_error', $danger->text);
    }

    #[Test]
    public function noDomain(): void
    {
        $this->dnsSubscription->domain = null;
        $this->dnsSubscription->save();

        $result = $this->runAction($this->dnsSubscription);

        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.error.no_domain_found', $danger->text);
    }

    #[Test]
    public function noDnsDeployment(): void
    {
        $this->dnsDeployment->delete();

        $result = $this->runAction($this->dnsSubscription);

        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.error.no_dns_deployment_found', $danger->text);
    }

    #[Test]
    public function noDomainSubscription(): void
    {
        $this->dnsSubscription->parent_subscription_id = null;
        $this->dnsSubscription->save();
        $this->domainDeployment->delete();
        $this->domainSubscription->delete();

        $result = $this->runAction($this->dnsSubscription);

        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.error.no_domain_subscription_found', $danger->text);
    }

    #[Test]
    public function noDomainDeployment(): void
    {
        $this->domainDeployment->delete();

        $result = $this->runAction($this->dnsSubscription);

        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.error.no_domain_deployment_found', $danger->text);
    }

    #[Test]
    public function noHostingSubscription(): void
    {
        $this->hostingSubscription->delete();

        $result = $this->runAction($this->dnsSubscription);

        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('Er zijn geen hosting instellingen gekoppeld aan dit abonnement.', $danger->text);
    }

    #[Test]
    public function noHostingDeployment(): void
    {
        $this->hostingDeployment->delete();

        $result = $this->runAction($this->dnsSubscription);

        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.error.no_hosting_deployment_found', $danger->text);
    }

    #[Test]
    public function hostingDeploymentNoServer(): void
    {
        self::assertNotNull($this->hostingDeployment->server);
        $this->hostingDeployment->server->delete();

        $result = $this->runAction($this->dnsSubscription);

        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.error.hosting_deployment_without_server', $danger->text);
    }

    #[Test]
    public function fullyWorkingAction(): void
    {
        $this->mockDispatcher->expects(self::once())->method('dispatch');

        $result = $this->runAction($this->dnsSubscription);

        self::assertInstanceOf(ActionResponse::class, $result);
        $message = $result['message'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.success.reset_dns_template', $message->text);
    }

    #[Test]
    public function redirectResetWithMultipleRedirects(): void
    {
        $this->mockRedirectService
            ->expects(self::once())
            ->method('listRedirects')
            ->with($this->redirectSubscription)
            ->willReturn([
                [
                    'source' => 'sub.' . self::DOMAIN,
                    'target' => 'https://example.com',
                    'type' => 'permanent',
                ],
                [
                    'source' => 'other.' . self::DOMAIN,
                    'target' => 'https://example.com',
                    'type' => 'temporary',
                ],
            ]);

        $this->mockRedirectDnsService
            ->expects(self::exactly(2))
            ->method('provisionDnsRecords')
            ->with(
                ...self::withConsecutive(
                    [
                        self::DOMAIN,
                        'sub.' . self::DOMAIN,
                        DnsRedirectProvisionOption::OVERRIDE,
                    ],
                    [
                        self::DOMAIN,
                        'other.' . self::DOMAIN,
                        DnsRedirectProvisionOption::OVERRIDE,
                    ],
                ),
            );

        $result = $this->runAction($this->redirectSubscription);

        self::assertInstanceOf(ActionResponse::class, $result);
        $message = $result['message'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.success.reset_dns_template', $message->text);
    }

    #[Test]
    public function redirectResetWithPathAndQuery(): void
    {
        $redirectSource = 'sub.' . self::DOMAIN . '/path?utm_source=newsletter';

        $this->mockRedirectService
            ->expects(self::once())
            ->method('listRedirects')
            ->with($this->redirectSubscription)
            ->willReturn([
                [
                    'source' => $redirectSource,
                    'target' => 'https://example.com',
                    'type' => 'permanent',
                ],
            ]);

        $this->mockRedirectDnsService
            ->expects(self::once())
            ->method('provisionDnsRecords')
            ->with(
                self::DOMAIN,
                'sub.' . self::DOMAIN,
                DnsRedirectProvisionOption::OVERRIDE,
            );

        $result = $this->runAction($this->redirectSubscription);

        self::assertInstanceOf(ActionResponse::class, $result);
        $message = $result['message'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.success.reset_dns_template', $message->text);
    }

    #[Test]
    public function redirectResetWithEmptyRedirects(): void
    {
        $this->mockRedirectService
            ->expects(self::once())
            ->method('listRedirects')
            ->with($this->redirectSubscription)
            ->willReturn([]);

        $this->mockRedirectDnsService->expects(self::never())->method('provisionDnsRecords');

        $result = $this->runAction($this->redirectSubscription);

        self::assertInstanceOf(ActionResponse::class, $result);
        $message = $result['message'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.success.reset_dns_template', $message->text);
    }

    #[Test]
    public function redirectResetListFails(): void
    {
        $this->mockRedirectService
            ->expects(self::once())
            ->method('listRedirects')
            ->with($this->redirectSubscription)
            ->willThrowException(
                new ListRedirectsException(
                    Uuid::fromString($this->redirectSubscription->uuid),
                ),
            );

        $this->mockRedirectDnsService->expects(self::never())->method('provisionDnsRecords');

        $result = $this->runAction($this->redirectSubscription);

        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.reset-dns.error.redirect-list-failed', $danger->text);
    }

    #[Test]
    public function unsupportedSubscriptionType(): void
    {
        $customer = new CustomerFactory()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->ssl()))
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain(self::DOMAIN)
            ->createOne();

        $result = $this->runAction($subscription);

        self::assertInstanceOf(ActionResponse::class, $result);
        $danger = $result['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.reset-dns.error.unsupported-subscription-type', $danger->text);
    }

    private function runAction(Subscription $subscription, bool $confirmAction = true): ActionResponse|Action
    {
        $action = new NovaResetDnsTemplateAction(
            translator: self::resolve(TranslatorInterface::class),
            jobDispatcher: $this->mockDispatcher,
            hostingDeploymentRepository: $this->hostingDeploymentRepository,
            dnsService: self::createStub(DnsService::class),
            storeNoteAction: self::createStub(StoreNoteAction::class),
            redirectService: $this->mockRedirectService,
            redirectDnsService: $this->mockRedirectDnsService,
            publicSuffixList: self::resolve(PublicSuffixList::class),
        );

        $fields = new Collection(['warning' => false, 'confirm_action' => $confirmAction]);

        return $action->handle(
            new ActionFields($fields, new Collection()),
            new Collection([$subscription]),
        );
    }
}
