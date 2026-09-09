<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Subscriptions\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaResetDnsSecBulkAction;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;

#[CoversClass(NovaResetDnsSecBulkAction::class)]
class NovaResetDnsSecBulkActionTest extends IntegrationTestCase
{
    #[Test]
    public function novaResetDnsSecBulkAction(): void
    {
        $this->app->bind(DomainService::class, fn () => self::createStub(DomainService::class));
        $csv = 'test.nl,bla.nl';
        $driver = ProviderSlug::OPEN_PROVIDER;

        $action = self::resolve(NovaResetDnsSecBulkAction::class);

        $results = $action->handle(
            new ActionFields(new Collection(['domains' => $csv, 'driver' => $driver->value]), new Collection()),
            new Collection(),
        );

        $message = $results->jsonSerialize()['message'];

        self::assertInstanceOf(Message::class, $message);
        self::assertSame('reset dnssec executed', $message->text);
    }
}
