<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Services;

use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Exceptions\ListRedirectsException;
use Waterfront\Domain\Redirects\Services\RedirectsDatabase\Redirect;
use Waterfront\Domain\Subscriptions\Models\Subscription;

interface RedirectServiceInterface
{
    /** @return Redirect[] */
    public function index(int $customerId, string $domain): array;

    public function add(int $customerId, string $source, string $target, RedirectType $type): Redirect;

    public function remove(int $customerId, string $source): void;

    public function update(int $customerId, string $source, string $target, RedirectType $type): Redirect;

    public function isSourceUnique(int $customerId, string $source): bool;

    /**
     * @throws ListRedirectsException
     *
     * @return list<array{source: string, target: string, type: string}>
     */
    public function listRedirects(Subscription $subscription): array;
}
