# HubSpot technical documentation

## What is HubSpot?
HubSpot is a CRM platform with all the software, integrations, and resources companies need to connect marketing, sales,
content management, and customer service. HubSpot's connected platform enables companies to grow their business faster by
focusing on their customers.

## How do we use HubSpot?
We sync all customer and subscription changes to HubSpot. This enables marketing to connect with our customers based on
their products. HubSpot is also used to send newsletters. Customers can subscribe to the newsletter via Atlantis or Coast
and unsubscribe via Coast. Marketing can can also create leads in HubSpot who later might become customers.

## How is HubSpot implemented?
Changes are synced to HubSpot via Laravel model events (created and updated events). For more details check
the customer and subscription observers in: `\App\Observers\`.
The events dispatch a job on the `\App\Enums\QueueName::CRM` job queue and then interact with the HubSpot API.
Because the HubSpot subscription object also uses data from other models (product name/slug and product group name/slug)
these changes are also tracked. If a value changes all relevant subscriptions get a new `updated_at` timestamp so that
they are going to be picked up by the out of sync subscription command (see `ProductGroupObserver` and
`ProductObserver`). Contacts (customers) are default entities in HubSpot but Subscriptions are
[custom objects](https://developers.hubspot.com/docs/api/crm/crm-custom-objects).

There are also four commands that sync missing or out of sync customers and subscriptions.
As soon as everything is in sync these commands shouldn’t have a lot of work and most of the time only retry failed
jobs once per day (e.g. customers with an email address that's not valid for HubSpot).
These commands can be found in this namespace: `Waterfront\Apps\Console\Commands\HubSpot`.

A third way that we interact with the HubSpot API is for the newsletter setting. For Atlantis the newsletter is part of
the order payload (`\Waterfront\Domain\Orders\Services\OrderService::processCartToOrder`) but this is something that we want to
change ([ATLANTIS-1608](https://yh-jira.atlassian.net/browse/ATLANTIS-1608)).
For Coast we use the newsletter API (`\Waterfront\Apps\API\Waterfront\Controllers\NewsLetterController`).
The `isSubscribed` method is the only direct API call to HubSpot, all other communication is done via jobs.

And finally there are also two Nova actions (`NovaAnonymizeCustomerAction` and `NovaHubspotSyncSingleCustomerAction`)
that interact with the HubSpot API.

Most HubSpot related code can be found in two namespaces:
- `Waterfront\Domain\Marketing`
- `Waterfront\Infra\HubspotClient`

But this is going to change in the future (see [WATER-5322](https://yh-jira.atlassian.net/browse/WATER-5322)).

A nice example to talk about some of the details of the HubSpot implementation is the `SyncSubscriptionToHubspotJob`.
The first thing this job does is check if the customer is already created. This check is needed because the subscription
needs to be connected to a customer in HubSpot. To minimise the amount of API calls we look at the `hubspot_events`
table for a succesful customer sync. If the customer was not yet synced to HubSpot a `SyncCustomerToHubspotJob` is
dispatched and the subscription job is delayed to retry again later.

The next thing the subscription job does is check if the customer and/or subscription was recently created. This is
needed to prevent race conditions because the HubSpot API has a slight delay on create actions (no API is called for
this check). Delay the subscription job and retry later.

After all validation is done a pending subscription event is created in the `hubspot_events` table but this has some
extra functionality build in. This also calls `cleanupSubscriptionEvents` which deletes old log records because this
table can contain a lot of data in the future. It also calls `resetLatestSubscriptionEvent` to mark all other log
records for this subscription as `latest = false`. This boolean was added to make queries smaller and faster because
there were some performance issues in the past. All HubSpot events are visible in Nova.

Next we check if the subscription belongs to a sub-customer and throw an error if this is the case because sub-customers
and their subscriptions should not be synced to HubSpot.

After the subscription is successfully created or updated in HubSpot `associateToContact` is called to connect the
subscription to the contact (customers are called contacts in HubSpot). And lastly the HubSpot event is marked as
successful in the event log table.

Of course things can also go wrong. The `HubspotThrottledException` for example, this happens regularly. This exception
is thrown when a rate limit is hit. Most of the time this happens because there were to many jobs per 10 seconds. This
error can be recognized in the event log by the "API Rate limit reached, retrying..." message. We've tried to solve this
problem with the [Laraval rate limiting middleware](https://laravel.com/docs/10.x/queues#rate-limiting) but this caused
more problems than it solved, so we removed this and added a sleep for a few seconds to let the API cool down before we
retry the job again. Please check the
[rate limit page](https://developers.hubspot.com/docs/api/usage-details#rate-limits) on the HubSpot website for more
details.

Failed jobs are retried three times but each job also has a
[backoff](https://laravel.com/docs/10.x/queues#dealing-with-failed-jobs) value of 3 seconds to prevent rate limit
errors. Jobs are also made [unique](https://laravel.com/docs/10.x/queues#unique-jobs) with the customer or subscription
uuid.

## Debugging/support
If you want to know if the HubSpot integration is still working you can check the
[HubSpot event log](https://admin.account.yourhosting.nl/nova/resources/nova-hubspot-event-resources) in Nova and see if
there are recent jobs. Or you can use the "Sync customer to HubSpot" Nova action and check the event log. Other things
you can do are running some commands in Postman or checking the job queue.

If extra fields need to be added to the HubSpot API, you want access to the HubSpot website or have other questions,
Joost Pisters from Yourhosting can help you out.

## Set up HubSpot on your local machine
1. Set Redis queue driver in `.env`:
    ```
    QUEUE_DRIVER='redis'
    ```
1. Collect settings/credentials for the Versio or Yourhosting sandbox environment add set values in `.env`:
    ```
    HUBSPOT_API_ACCESS_TOKEN=""
    HUBSPOT_SUBSCRIPTION_OBJECT_TYPE_ID=""
    HUBSPOT_SUBSCRIPTION_CONTACT_ID=""
    ```
1. Try updating a customer and check the event log if the job is successful:
https://admin.sandwaveio.dev/nova/resources/hubspot-events

1. If you see this error message: `Communication with hubspot failed: Hubspot credentials not configured`, you may need
to restart horizon to load the `.env` values:
    ```
    docker exec -it sandwave-waterfront-backend-1 bash -c "php artisan horizon:terminate"
    ```







