<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Services\RedirectsDatabase;

interface RedirectsRepositoryInterface
{
    /** @return Redirect[] */
    public function listRedirects(int $customerId, string $domain): array;

    public function findBySourceForMigrations(string $source): RedirectDatabaseRepository|null;

    public function createRedirect(int $customerId, string $source, string $destination, string $type): Redirect;

    public function updateRedirect(int $customerId, string $source, string $destination, string $type): Redirect;

    public function deleteRedirect(int $customerId, string $source): void;

    public function isRedirectSourceUnique(int $customerId, string $source): bool;
}
