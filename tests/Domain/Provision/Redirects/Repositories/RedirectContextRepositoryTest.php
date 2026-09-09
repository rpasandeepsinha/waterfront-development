<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Redirects\Repositories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CaddyContextFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Redirects\Repositories\RedirectContextRepository;

#[CoversClass(RedirectContextRepository::class)]
class RedirectContextRepositoryTest extends IntegrationTestCase
{
    private RedirectContextRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = self::resolve(RedirectContextRepository::class);
    }

    #[Test]
    public function createOrRestoreInsertsNewRowWhenContextDoesNotExist(): void
    {
        $context = Uuid::uuid4();
        $host = 'example.com';

        self::assertDatabaseCount('redirects_context_caddy', 0);

        $result = $this->repository->createOrRestore($context, $host);

        self::assertSame($context->toString(), $result->context_uuid->toString());
        self::assertSame($host, $result->host);
        self::assertFalse($result->trashed());

        self::assertDatabaseCount('redirects_context_caddy', 1);
        self::assertDatabaseHas('redirects_context_caddy', [
            'context_uuid' => $context->toString(),
            'host' => $host,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function createOrRestoreUpdatesHostWhenContextAlreadyExists(): void
    {
        $context = Uuid::uuid4();

        $existing = CaddyContextFactory::new()->createOne([
            'context_uuid' => $context,
            'host' => 'old-host.example',
        ]);

        $result = $this->repository->createOrRestore($context, 'new-host.example');

        self::assertSame($existing->id, $result->id);
        self::assertSame('new-host.example', $result->host);
        self::assertFalse($result->trashed());

        self::assertDatabaseCount('redirects_context_caddy', 1);
        self::assertDatabaseHas('redirects_context_caddy', [
            'id' => $existing->id,
            'context_uuid' => $context->toString(),
            'host' => 'new-host.example',
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function createOrRestoreRestoresSoftDeletedContext(): void
    {
        $context = Uuid::uuid4();

        $existing = CaddyContextFactory::new()->createOne([
            'context_uuid' => $context,
            'host' => 'old-host.example',
        ]);
        $existing->delete();

        self::assertSoftDeleted('redirects_context_caddy', [
            'id' => $existing->id,
        ]);

        $result = $this->repository->createOrRestore($context, 'new-host.example');

        self::assertSame($existing->id, $result->id);
        self::assertSame('new-host.example', $result->host);
        self::assertFalse($result->trashed());

        self::assertDatabaseCount('redirects_context_caddy', 1);
        self::assertDatabaseHas('redirects_context_caddy', [
            'id' => $existing->id,
            'context_uuid' => $context->toString(),
            'host' => 'new-host.example',
            'deleted_at' => null,
        ]);
    }
}
