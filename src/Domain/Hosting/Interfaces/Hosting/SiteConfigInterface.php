<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting;

interface SiteConfigInterface extends FerryWebhookInterface
{
    public function isDnsControlEnabled(): bool;

    public function getDomain(): string|null;

    public function getPackage(): string;

    public function hasSsoEnabled(): bool;

    public function hasSslEnabled(): bool;

    public function isRegularUser(): bool;

    public function isReseller(): bool;

    public function isAdmin(): bool;

    public function getMaxAmountDomains(): int;

    public function getMaxAmountMailAccounts(): int;

    public function getMaxAmountDatabases(): int;

    public function getMaxNetworkTrafficInMB(): int;

    public function getMaxDiskSpaceInMB(): int;
}
