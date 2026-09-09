# UpdateSitebuilderRequest

Most params should be clear what they should contain, but here is some extra explanation about some.

## Packages

This is an array of integers containing the package ID's created in basekit. This should contain
the new state of the packages which need to be enabled. On the provision side we check which
package are currently enabled and disable those which are not in this array and enable packages
which are in this array but not yet enabled.

If only the contract period needs to be changed, then still all packages have to be provided with this request.

Creating these packages in Basekit can't be done by us, for this we would need to contact our
account manager at basekit. We can find the possible packages added to our account in the GUI
of Basekit.

## Contract period

Only a single contract period can be set for all packages. It's not possible to have a different
contract period for one of the packages.

If the current contract period was 1 month for an example and the new contract period is 12, then
all packages will be updated to the new contract period.
