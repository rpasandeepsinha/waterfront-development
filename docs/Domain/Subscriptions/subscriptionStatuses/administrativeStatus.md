# Administrative Status

This is a table of all possible statuses.

| status    |
|-----------|
| active    |
| canceled  |
| archiving |
| archived  |
| suspended |
| expired   |
| inactive  |

The `administrative_status` is determined by Waterfront, the outcome of which is based on what requests the customer or customer support agent perform in Coast or Nova.
All status changes end up in and are processed by Waterfront.

## state flows

### Active
When a subscription is successfully created, the initial value of `administrative_status` will be `active`,
While this field is set to `active`, the subscription is eligible for invoicing.

From this point, the `administrative_status` field can only be set to:
- `suspended`, by customer support.
- `canceled`, by customer support and the customer.

### Canceled
Reaching the `canceled` state is done via an action by customer support or the customer themselves.

From this point, the `administrative_status` field can only be set to:
- `active`, by customer support, if and when the customer wants to revert the cancellation before the system processed the deletion.
- `expired`, by the system, once it has finished processing the cancellation of the subscription to third parties.

### Archived
When the `administrative_status` is `archived`, the subscription is not visible to the customer anymore. Renewals are disabled and the system will cease the invoicing of the subscription.

From this point, the `administrative_status` field can only be set to:
- `active`, by customer support. Depending on which product is bound to the subscription, the system won't actually do anything with this. In most cases, the customer support agent will want to create a new subscription instead.

### Suspended
The `administrative_status` field can only be set to `suspended` by customer support.
It is usually only applied when the customer has yet to pay over a considerable amount of time, or partakes in practices considered against terms of use.

From this point, the `administrative_status` field can only be set to:
- `active`, by customer support, when they deem that the customer should be allowed to make use of our services again.

### Expired
When the subscription `end_date` is reached the state will become `expired`, and then the system will determine whether the subscription is eligible for a grace period.

From this point, the `administrative_status` field can only be set to:
- `active`, once the customer has renewed the subscription.
- `archived`, by the system, once it the grace period has ended.

### Inactive
The `inactive` status was used in the m365 migration to have it be renewed but not invoiced.

**This status is not in use anymore**
