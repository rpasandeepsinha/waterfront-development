<?php

declare(strict_types=1);

namespace Waterfront\Domain\Transfers\Interfaces;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Models\Subscription;

interface ExecuteExtensionTransferInterface
{
    public function execute(Subscription $subscription, Customer $receiver): void;
}
