<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting;

interface HostingOfferingInterface extends FerryWebhookInterface
{
    public function getPackage(): string;

    public function getMaxAmountDomains(): int;

    public function getMaxAmountMailAccounts(): int;

    public function getMaxAmountDatabases(): int;

    public function getMaxNetworkTrafficInMB(): int;

    public function getMaxDiskSpaceInMB(): int;
}
