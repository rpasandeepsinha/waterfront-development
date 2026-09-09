# Context UUID for Sitebuilder

For Sitebuilder it is technically possible to create multiple websites for a single context
(user in this scenario).

The problem with this is that BaseKit adds packages for a user and not a site. This means
that all sites for a certain user all have the same packages enabled.

Because of this we decided that for each site needs to provide it's own context uuid, this
can be the (parent) subscription uuid.
That way a customer can have a domain with Website builder enabled and another domain with
Website builder + booking enabled.
