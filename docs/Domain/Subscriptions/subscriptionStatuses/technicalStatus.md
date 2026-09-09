# Technical Status

This is a table of all possible statuses.

| Extension | mixed     | hosting, ssl, vps   |
|-----------|-----------|---------------------|
| ACT       | suspended | ok                  |
| FAI       |           | error               |
| DEL       |           | registration        |
| REQ       |           | pending             |
| PEN       |           | failed              |
| MIGRATING |           | deleting            |
| RRQ       |           | deleted             |
| SCH       |           | deleting_failed     |
|           |           | reinstalling        |
|           |           | suspending          |
|           |           | suspending_failed   |
|           |           | unsuspending        |
|           |           | unsuspending_failed |




## Process

When a subscription gets created, the initial value of the `technical_status` field will be `null`.

### General status

#### suspended
The `suspended` technical state can be achieved by triggering an action as customer support.

From this point, the `technical_status` field can only be set to:
- `ok`, by customer support, through an action.

### Extensions

#### registration
When a domain is ordered, one or more subscriptions will be created for the order.
After the system has started processing the order's subscriptions, the `technical_status` field will be set to `registration`.

From this point, the `technical_status` field can only be set to:
- `pending`, by the system (see next header).

#### pending
The `technical_status` field will be updated to `pending` after the system has, for example, ordered the desired domain from the register. This indicates that the order has been made to the third party, but the system does not yet know whether or not the third party was successful in completing that order.

From this point, the `technical_status` field can only be set to:
- `ok`, by the system (see `ok` header).

#### transfer
When a transfer is 'registered' the first `technical_status` will be `transfer`
During a domain transfer the registrar can give back a whole list of statuses for these statuses there is `ParseRtrTransferStatusToWfStatusAction`
The main statuses we can get out of the parser are `pending`, `failed` and `ok`.

If the transfer is successful the status will become `ok`.
If the transfer is failed the status will become `failed`
at last if the transfer gets pending back it will stay `pending`.

For the pending domains we fetch an RTR notification that can update the `technical_status` of the subscription to `pending`, `failed` and `ok` again.

### Hosting, Ssl and Vps

#### ok
Status `ok` means that the deployment was successful.

From this point, the `technical_status` field can only be set to:
- `suspended`, by customer support, through an action.
- `deleted`, by the system.

#### suspended
The `suspended` technical state can be achieved by triggering an action as customer support, as described in the `suspended` header in the documentation for [administrative_status](administrativeStatus.md).

#### deleted
When the subscription gets terminated the `technical_status` will get updated to `deleted` once its deleted externally.
This is normally done when the system picks up a subscription that has the `administrative_status` set to `cancelled`.

#### failed
When the deployment fails, the `technical_status` will be set to `failed`.
From here, the deployment can be retried.

#### suspending
The `suspending` status can be achieved by triggering the `suspendSubscription` action as customer support.

The `technical_status` of the subscription will then be updated to `suspending` to show that the actual suspending of the subscription has yet to occur, but will be done in the near future.

From this point, the `technical_status` field can only be set to:
- `suspended`, by the system, on successful suspension.
- `suspension_failed`, by the system, on unsuccessful suspension.

#### suspension_failed
The `suspension_failed` status is only achieved during the suspension process of a subscription.

The `technical_status` will be updated to `suspension_failed` when either the `SuspendDomainJob` or the `SuspendHostingJob` fails.

#### unsuspending
The `unsuspending` status can be achieved by triggering the `unsuspendSubscription` action as customer support.
This action is only available for subscriptions with the `suspended` status.

The `technical_status` of the subscription will then be updated to `unsuspending`, to show that it has yet to actually unsuspend the subscription, but will do so in the near future.

From this point, the `technical_status` field can only be set to:
- `ok`, by the system, on successful unsuspension.
- `unsuspending_failed`, by the system, on unsuccessful unsuspension.


#### unsuspending_failed
The `unsuspending_failed` status is only achieved during the unsuspension process of a subscription.

The `technical_status` will be updated to `unsuspending_failed` when either the `UnsuspendDomainJob` or the `UnsuspendHostingJob` fails
