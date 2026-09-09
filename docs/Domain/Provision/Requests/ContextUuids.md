# Context UUID

Context UUID's are used make sure deployments are related/linked to the same context (think of user, tenant, etc.).

If we take Microsft365 as an example. The first thing which needs to be created is the tenant,
for example `yourhosting.onmicrosoft.com`. This tenant will be the context for all further deployments.
With later requests the same context UUID is provided to make sure the seats are created for this tenant.
