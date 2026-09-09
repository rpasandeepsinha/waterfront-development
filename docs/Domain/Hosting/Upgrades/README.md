# Upgrades

Hosting packages can be upgraded within our system, they do have certain requirements to successfully run the upgrade:

Free redirect and redirect subscriptions do **NOT** need a hosting deployment, they follow a different flow from the normal upgrade.

All other hosting subscriptions require a hosting deployment to start the upgrade flow. Not having one will result in an exception.

The code can be found here:

`src/Apps/API/Waterfront/Controllers/HostingController.php`
`src/Domain/Hosting/Actions/ChangeHostingAction.php`

## Charging

Upgrades are performed through Coast and are done on credit, meaning the user will get the invoice after it has been completed.
If the customer has insufficient credit it will throw an exception.

