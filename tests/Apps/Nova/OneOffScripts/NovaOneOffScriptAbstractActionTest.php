<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\OneOffScripts;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptAbstractAction;
use Waterfront\Apps\OneOffScripts\OneOffScript;

#[CoversClass(NovaOneOffScriptAbstractAction::class)]
class NovaOneOffScriptAbstractActionTest extends IntegrationTestCase
{
    #[Test]
    public function oneOffScriptIsRegisteredInDatabase(): void
    {
        // We expect nothing in the db at first
        self::assertSame(0, OneOffScript::query()->count());

        // Now initiate an action 'test-foo-bar' which extends the abstract
        $novaOneOffAction = new class() extends NovaOneOffScriptAbstractAction {
            protected function getOneOffScriptSlug(): string
            {
                return 'test-foo-bar';
            }

            protected function getOneOffScriptTicketUrl(): string
            {
                return 'test-ticket-ref';
            }

            // Define a simple action handle method to be able to test the `registerExecution()`
            // that's defined in the abstract class.
            public function handle(): void
            {
                $this->registerExecution();
            }
        };

        // A record with the slug should now exist
        $oneOff = OneOffScript::query()->where('slug', 'test-foo-bar')->first();
        self::assertInstanceOf(OneOffScript::class, $oneOff);
        self::assertSame('test-foo-bar', $oneOff->slug);
        self::assertSame('test-ticket-ref', $oneOff->ticket_ref);
        self::assertNull($oneOff->last_executed_at);

        // Test that the handle updates the last executed at.
        $novaOneOffAction->handle();

        $oneOff->refresh();
        self::assertNotNull($oneOff->last_executed_at);
    }
}
