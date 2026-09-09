<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\FerryInternalNameserverFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\FerryInternalNameserverController;
use Waterfront\Domain\Ferry\Models\FerryInternalNameserver;

#[CoversClass(FerryInternalNameserverController::class)]
class InternalNameserverControllerTest extends IntegrationTestCase
{
    #[Test]
    public function indexReturnsThePaginatedNameservers(): void
    {
        FerryInternalNameserverFactory::new()->createOne(['nameserver_hostname' => 'ns1.ferry.internal']);
        FerryInternalNameserverFactory::new()->createOne(['nameserver_hostname' => 'ns2.ferry.internal']);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.migrations.internal-nameservers.list'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['nameserver_hostname' => 'ns1.ferry.internal'])
            ->assertJsonFragment(['nameserver_hostname' => 'ns2.ferry.internal'])
            ->assertJsonStructure(['data' => [['id', 'nameserver_hostname', 'created_at', 'updated_at']], 'meta' => ['total']]);
    }

    #[Test]
    public function indexRespectsThePageSize(): void
    {
        FerryInternalNameserverFactory::new()->count(3)->create();

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.migrations.internal-nameservers.list') . '?pageSize=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    public function storeCreatesTheNameserver(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.migrations.internal-nameservers.store'), [
                'nameserver_hostname' => 'ns1.ferry.internal',
            ])
            ->assertCreated()
            ->assertJsonStructure(['message', 'errors']);

        self::assertDatabaseHas(FerryInternalNameserver::class, ['nameserver_hostname' => 'ns1.ferry.internal']);
    }

    #[Test]
    public function storeRejectsAHostnameThatAlreadyExists(): void
    {
        FerryInternalNameserverFactory::new()->createOne(['nameserver_hostname' => 'ns1.ferry.internal']);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.migrations.internal-nameservers.store'), [
                'nameserver_hostname' => 'ns1.ferry.internal',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nameserver_hostname']);
    }

    #[Test]
    public function storeRejectsAMissingHostname(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.migrations.internal-nameservers.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nameserver_hostname']);
    }

    #[Test]
    public function updateChangesTheHostname(): void
    {
        $nameserver = FerryInternalNameserverFactory::new()->createOne(['nameserver_hostname' => 'ns1.ferry.internal']);

        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.migrations.internal-nameservers.update', ['ferryInternalNameserver' => $nameserver->id]),
                ['nameserver_hostname' => 'ns2.ferry.internal']
            )
            ->assertOk()
            ->assertJsonStructure(['message', 'errors']);

        $nameserver->refresh();
        self::assertSame('ns2.ferry.internal', $nameserver->nameserver_hostname);
    }

    #[Test]
    public function updateAllowsKeepingTheSameHostname(): void
    {
        $nameserver = FerryInternalNameserverFactory::new()->createOne(['nameserver_hostname' => 'ns1.ferry.internal']);

        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.migrations.internal-nameservers.update', ['ferryInternalNameserver' => $nameserver->id]),
                ['nameserver_hostname' => 'ns1.ferry.internal']
            )
            ->assertOk();

        self::assertDatabaseHas(FerryInternalNameserver::class, ['nameserver_hostname' => 'ns1.ferry.internal']);
    }

    #[Test]
    public function updateRejectsAHostnameOwnedByAnotherNameserver(): void
    {
        FerryInternalNameserverFactory::new()->createOne(['nameserver_hostname' => 'ns1.ferry.internal']);
        $nameserver = FerryInternalNameserverFactory::new()->createOne(['nameserver_hostname' => 'ns2.ferry.internal']);

        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.migrations.internal-nameservers.update', ['ferryInternalNameserver' => $nameserver->id]),
                ['nameserver_hostname' => 'ns1.ferry.internal']
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nameserver_hostname']);

        $nameserver->refresh();
        self::assertSame('ns2.ferry.internal', $nameserver->nameserver_hostname);
    }

    #[Test]
    public function destroyRemovesTheNameserver(): void
    {
        $nameserver = FerryInternalNameserverFactory::new()->createOne(['nameserver_hostname' => 'ns1.ferry.internal']);

        $this->actingAsEmployee()
            ->deleteJson($this->generateRoute('admin.migrations.internal-nameservers.destroy', ['ferryInternalNameserver' => $nameserver->id]))
            ->assertOk()
            ->assertJsonStructure(['message', 'errors']);

        $this->assertDatabaseMissing($nameserver);
    }
}
