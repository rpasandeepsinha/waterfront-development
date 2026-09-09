<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting\Models;

use Illuminate\Database\Eloquent\Model;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 *
 * @property ?Server      $server
 * @property ?Server      $mailOnlyServer
 * @property Subscription $subscription
 * @property string       $uuid
 * @property ?string      $directadmin_customer_username
 *
 * @mixin Model
 */
interface HostingDeploymentInterface
{
}
