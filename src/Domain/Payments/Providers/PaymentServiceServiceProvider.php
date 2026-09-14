<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Waterfront\Domain\Payments\Events\PaymentUpdatedEvent;
use Waterfront\Domain\Payments\Listeners\PaymentUpdatedListener;

class PaymentServiceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Event::listen(
            PaymentUpdatedEvent::class,
            [PaymentUpdatedListener::class, 'handle'],
        );
    }
}
