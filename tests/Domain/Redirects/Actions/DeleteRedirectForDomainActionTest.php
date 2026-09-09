<?php

declare(strict_types=1);

namespace Tests\Domain\Redirects\Actions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\SubscriptionFactory;
use Tests\TestCase;
use Waterfront\Domain\Redirects\Actions\DeleteRedirectForDomainAction;
use Waterfront\Domain\Redirects\Services\RedirectService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Configuration\ConfigurationInterface;

#[CoversClass(DeleteRedirectForDomainAction::class)]
class DeleteRedirectForDomainActionTest extends TestCase
{
    private const string REDIRECT_DNS = 'act-ingress.httpgate.io';

    private Subscription $subscription;

    private RedirectService&MockObject $redirectService;

    private DeleteRedirectForDomainAction $deleteRedirectForDomainAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscription = new SubscriptionFactory()->makeOne();
        $this->redirectService = self::createMock(RedirectService::class);
        $configuration = self::createStub(ConfigurationInterface::class);
        $configuration
            ->method('getAsString')
            ->willReturn(self::REDIRECT_DNS);

        $this->deleteRedirectForDomainAction = new DeleteRedirectForDomainAction(
            $this->redirectService,
            $configuration
        );
    }

    #[Test]
    public function shouldDeleteRedirectWhenRemovingCnamePointingToRedirectService(): void
    {
        $this->redirectService
            ->expects(self::once())
            ->method('deleteRedirect')
            ->with($this->subscription, 'test.nl');

        $oldRecord = [
            'name' => 'test.nl',
            'type' => 'CNAME',
            'content' => self::REDIRECT_DNS,
        ];

        $this->deleteRedirectForDomainAction->execute($this->subscription, $oldRecord);
    }

    #[Test]
    public function shouldDeleteRedirectWhenRemovingAliasPointingToRedirectService(): void
    {
        $this->redirectService
            ->expects(self::once())
            ->method('deleteRedirect')
            ->with($this->subscription, 'test.nl');

        $oldRecord = [
            'name' => 'test.nl',
            'type' => 'ALIAS',
            'content' => self::REDIRECT_DNS,
        ];

        $this->deleteRedirectForDomainAction->execute($this->subscription, $oldRecord);
    }

    #[Test]
    public function shouldNotDeleteRedirectWhenRemovingNonRedirectManagedRecord(): void
    {
        $this->redirectService
            ->expects(self::never())
            ->method('deleteRedirect');

        $oldRecord = [
            'name' => 'test.nl',
            'type' => 'TXT',
            'content' => self::REDIRECT_DNS,
        ];

        $this->deleteRedirectForDomainAction->execute($this->subscription, $oldRecord);
    }

    #[Test]
    public function shouldNotDeleteRedirectWhenRemovingRecordNotPointingToRedirectService(): void
    {
        $this->redirectService
            ->expects(self::never())
            ->method('deleteRedirect');

        $oldRecord = [
            'name' => 'test.nl',
            'type' => 'CNAME',
            'content' => 'other.example.net',
        ];

        $this->deleteRedirectForDomainAction->execute($this->subscription, $oldRecord);
    }

    #[Test]
    public function shouldDeleteRedirectWhenUpdatingManagedRecordToNonManagedRecord(): void
    {
        $this->redirectService
            ->expects(self::once())
            ->method('deleteRedirect')
            ->with($this->subscription, 'test.nl');

        $oldRecord = [
            'name' => 'test.nl',
            'type' => 'CNAME',
            'content' => self::REDIRECT_DNS,
        ];
        $newRecord = [
            'name' => 'test.nl',
            'type' => 'TXT',
            'content' => self::REDIRECT_DNS,
        ];

        $this->deleteRedirectForDomainAction->execute($this->subscription, $oldRecord, $newRecord);
    }

    #[Test]
    public function shouldDeleteRedirectWhenUpdatingRecordAwayFromRedirectService(): void
    {
        $this->redirectService
            ->expects(self::once())
            ->method('deleteRedirect')
            ->with($this->subscription, 'test.nl');

        $oldRecord = [
            'name' => 'test.nl',
            'type' => 'CNAME',
            'content' => self::REDIRECT_DNS,
        ];
        $newRecord = [
            'name' => 'test.nl',
            'type' => 'CNAME',
            'content' => 'out.test.nl',
        ];

        $this->deleteRedirectForDomainAction->execute($this->subscription, $oldRecord, $newRecord);
    }

    #[Test]
    public function shouldNotDeleteRedirectWhenDnsContentHasNotChanged(): void
    {
        $this->redirectService
            ->expects(self::never())
            ->method('deleteRedirect');

        $oldRecord = [
            'name' => 'test.nl',
            'type' => 'CNAME',
            'content' => self::REDIRECT_DNS,
        ];
        $newRecord = [
            'name' => 'test.nl',
            'type' => 'CNAME',
            'content' => self::REDIRECT_DNS,
        ];

        $this->deleteRedirectForDomainAction->execute($this->subscription, $oldRecord, $newRecord);
    }
}
