# Subscription expiration

When a subscription with administrative_status `canceled` has passed the `end_date`, the system will set the `administrative_satus` to `expired`.
The system will determine a `termination_date` and remove the technical deployment on that date (in case of no grace period this will usually be the same day). When the termination_date has been reached and the technical deployment is deleted the Administrative status will become `deleted`
In case of a product with a grace period, the system will gracefully suspend the subscription at the moment of expiring. During the grace period a subscription can be resumed. Resuming a subscription will unsuspend the subscription, put the `administrative_status` back to `active`, remove the `termination_date` and `cancel_date`.
