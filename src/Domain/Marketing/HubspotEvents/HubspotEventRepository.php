<?php

declare(strict_types=1);

namespace Waterfront\Domain\Marketing\HubspotEvents;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Marketing\Models\HubspotEvent;

class HubspotEventRepository
{
    public function createPendingEvent(int $customerId, string $description): HubspotEvent
    {
        $event = new HubspotEvent();
        $event->event = $description;
        $event->message = '';
        $event->customer_id = $customerId;
        $event->status = HubspotEventStatus::PENDING;
        $event->save();

        return $event;
    }

    public function markEventAsSuccessful(HubspotEvent $event, ?string $message = null): void
    {
        $event->message = substr($message ?? '', 0, 4096);
        $event->status = HubspotEventStatus::SUCCESS;
        $event->save();
    }

    public function markEventAsFailed(HubspotEvent $event, string $message): void
    {
        $event->message = substr($message, 0, 4096);
        $event->status = HubspotEventStatus::FAILED;
        $event->save();
    }

    public function wasRecentlyCreatedCustomer(Customer $customer): bool
    {
        $firstSuccessfulEvent = HubspotEvent::query()
            ->where('customer_id', $customer->id)
            ->where('status', HubspotEventStatus::SUCCESS->value)
            ->first();
        if ($firstSuccessfulEvent === null) {
            return false;
        }

        return $firstSuccessfulEvent->created_at?->isAfter(CarbonImmutable::now()->subSeconds(30)) ?? false;
    }

    public function pruneEvents(int $retention): void
    {
        // Split records by customer, type of event, status and hubspot ID
        // Calculate the most recent events based on the creation date
        // Delete everything that's above the retention threshold
        // Resulting in deleting all the oldest records

        $sql = <<<SQL
        delete from hubspot_events
        using (
        	select
        		id,
        		row_number () over (
        			partition by customer_id, event, status
        			order by created_at desc
        		) as "row_number"
        	from hubspot_events he
        ) sub
        where sub.id = hubspot_events.id
        	and sub."row_number" > :retention
        SQL;
        DB::statement($sql, ['retention' => $retention]);
    }
}
