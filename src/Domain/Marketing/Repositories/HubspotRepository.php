<?php

declare(strict_types=1);

namespace Waterfront\Domain\Marketing\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Marketing\Models\HubspotObjectSync;

class HubspotRepository
{
    public function getObjectBySubscriptionUuid(UuidInterface $uuid): ?HubspotObjectSync
    {
        return HubspotObjectSync::query()->where('sandwave_object_id', $uuid)->first();
    }

    public function updateSyncedAt(UuidInterface $uuid): void
    {
        DB::statement('update hubspot_object_sync set synced_at = ? where sandwave_object_id = ?;', [
            CarbonImmutable::now(),
            $uuid,
        ]);
    }

    /**
     * @return object{id: int}[]
     */
    public function getCustomerIdsReadyToBeSyncedByUpdatedAt(): array
    {
        return DB::select(
            <<<SQL
            select candidates.id from (
                select c.id, greatest(c.updated_at, max(s.updated_at)) as "max_updated"
                from subscriptions s
                join customers c on s.customer_id = c.id
                left join hubspot_object_sync hos on hos.sandwave_object_id = s.uuid::uuid
                where hos.id is null
                    or (hos.synced_at + interval '5 minute' < s.updated_at or (s.updated_at + interval '5 hour' < now() and hos.synced_at < s.updated_at))
                    or (hos.synced_at + interval '5 minute' < c.updated_at or (c.updated_at + interval '5 hour' < now() and hos.synced_at < c.updated_at))
                group by c.id
                union
                select c.id, greatest(c.updated_at, max(ots.updated_at)) as "max_updated"
                from one_time_services ots
                join customers c on ots.customer_id = c.id
                left join hubspot_object_sync hos on hos.sandwave_object_id = ots.uuid
                where hos.id is null
                    or (hos.synced_at + interval '5 minute' < ots.updated_at or (ots.updated_at + interval '5 hour' < now() and hos.synced_at < ots.updated_at))
                    or (hos.synced_at + interval '5 minute' < c.updated_at or (c.updated_at + interval '5 hour' < now() and hos.synced_at < c.updated_at))
                group by c.id
            ) as candidates
            left join (
            	select distinct on (customer_id) customer_id, id, status, created_at
            	from hubspot_events
            	where hubspot_events."event" NOT IN ('Anonymizing customer in Hubspot','Synchronizing customer to Hubspot','Enable marketing emails','Disable marketing emails')
            	order by customer_id, created_at desc
            ) as hubspot_events on hubspot_events.customer_id = candidates.id
            where hubspot_events.id is null
                or (hubspot_events.status = 'failed' and hubspot_events.created_at + interval '5 hour' < NOW())
                or (hubspot_events.status != 'failed' and hubspot_events.created_at + interval '5 minute' < now() and hubspot_events.created_at < candidates.max_updated)
            group by candidates.id
            limit 100;
            SQL,
        );
    }
}
