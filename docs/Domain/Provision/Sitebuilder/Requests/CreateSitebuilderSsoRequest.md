# CreateSitebuilderSsoRequest

This request requires a tagUuid. In this case, the tag should be the unique tag for this deployment.
For example the subscription uuid.

This request returns a `\Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderSsoResult`.
When the request is successful, this object contains a ssoUrl. The customer should be redirected
to this url for the customer to be logged in in BaseKit.
