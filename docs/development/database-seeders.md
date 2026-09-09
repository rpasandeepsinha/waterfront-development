# Guidelines for the writing of data seeders

## Definitions

### Product seeder
A seeder containing everything that is needed for setting up our platform so we can sell a specific group of product.
This includes things like:
- A product group
- Product prices
- Product specifications
- Providers
- etc.

### Scenario seeder
A scenario is a fully seeded customer set up for a specific use case. An example can be a "discount scenario" for setting up a customer
that has all possible variations of product discounts available. Very useful for reminding ourselves about all the possible use cases,
or doing frontend work in the shop, or having reasonable edge-cases available for updating our PDF invoices.

#### Test Kees
This is the name of the customer/seeder that contains a little bit of everything, and as a result is quite large. The intention is to have a single customer that can be used to broadly check "everything" when you log in. When you are adding a new type of product or significantly change an existing one it would make sense to add it to this seeder.
When edge cases are desired or a specific feature needs to be seeded under specific conditions it's recommended to create a new scenario specifically for that use case.

### Platform seeder
All models that don't fit into the other categories fall under this umbrella, you can also see them as "miscellaneous".

### Reference repository
Dependencies between data is inevitable if you don't have a single insert script. This means we have to seed data in one place and retrieve it elsewhere to do something with it.
To easily facilitate this there is the `ReferenceRepository` which allows us to add and retrieve model instances from memory based on an enum. The benefit is that no data has to
be retrieved from the database and that it shares a common reference. You don't have to figure out that it's best to retrieve a subscription based on a domain, or a customer by its last name. A descriptive enum variant will do.

## Price scenarios

Product prices are loaded from a CSV file and seeded in the database. The default scenario contains all possible valid combinations of billing and contract periods.

### Running a specific scenario

If no scenario is specified, the default scenario will be run. If you want to run a specific scenario, you can specify it with the `db:seed` command by using the `--scenario` option. For example, to run the `yh-prod.csv`, you can use the following command: `php artisan db:seed --scenario=yh-prod`

Note: make sure you clean the database first with `php artisan migrate:fresh`

### Updating a scenario

Use the following query to retrieve all prices from the environment that you want. This can be an export from production or from changes that you made locally. Save the output as a CSV file in `database/seeds/Support/Data/`

```sql
select
	products.slug,
	product_prices.billing_period,
	product_prices."type",
	product_prices.regular_price,
	product_prices.promotion_price,
	product_prices.product_discount_id,
	product_prices.contract_period,
	product_prices.translation_key_id,
	product_prices.introduction_price,
	product_prices.orderable,
	product_prices.action_period,
	product_prices.action_period_price,
	product_prices.is_default
from product_prices
join products on product_prices.product_id = products.id
order by
	products.slug,
	product_prices."type",
	product_prices.contract_period,
	product_prices.billing_period,
	product_prices.is_default
```


## Tips
### Readability is important
The intention of the seeders is to immediately be obvious from the `run()` function. When a seeder is set up most time is spent on updating something small or
adding an extra use case. The idea is to facilitate this as quickly as possible by seeing the `run()` function as a table of contents.

### Avoid control structures
Seeders are a long list of insert statements with some variation (either now, or in the future).
It is fine to copy and paste an example you found elsewhere, in order to change it as needed.

Writing conditional statements, loops, functions etc. to make code more efficient/readable/D.R.Y. is very normal.
It is also tempting to do that when writing seeders, but these control structures greatly reduce flexibility when trying to expand them with a slightly different use case.

Examples based on real world learnings:
- A loop that iterates over all product groups and creates subscriptions and deployments for them
  - What if a certain product group works differently? What if three product groups work differently? Before you know it you need to untangle a spaghetti dish and add all kinds of new exceptions before you can add your small new subscription.
  - The loops pick the registration price for the subscriptions, but you get asked to develop work on subscription renewal. It would certainly be nice to automatically have a subscription with a prolongation price!
- A chain of functions calling each other and returning values
  - A human reads from top to bottom and quickly loses context when having to go back and forth. This quickly becomes problematic with data that has a lot of relations and dependencies.
  - A function returning a single domain contact might suddenly need to return two. Will you return a collection and then iterate over it to process them further, or is it simpler to just have two insert statements and use the reference repository for processing them further?
### It's totally fine to have verbose seeders
### Reflect real world as closely as (reasonably) possible
Try to set up the seeders as close to the production environment as possible. This means setting up all the relations between data just like when an order is placed in the real world. A subscription is ordered so there should be an order with order line items etc.
It's tempting to strictly seed what is needed for a certain scenario, but history has taught that you'll quickly need its relations.

Some reasonable exceptions to this tip apply. For example, it wouldn't make much sense to seed an audit for every database entry/update. Or seed a response from an external entity (like RTR) for every SSL certificate that is set up, but it might be!
### Only put used references in the reference repository
There's no need to put things in the reference repository that are never retrieved as it makes it easier to see what data is being used. It's fine to seed a certain domain TLD product and not have it present in the repository and not have it in use elsewhere. That domain is still available for purchase in the shop and visible in the admin panel.
### Perfect doesn't exist
Everything above is a tip based on experience, but doesn't have to be followed religiously. There are exceptions to every rule so feel free to ignore a tip based on your own judgement. However, they exist for a reason.

## Good to know
- Events have been disabled by the trait `WithoutModelEvents`. This means all logic that results from event hooks (incl. boot functions and observers) is not ran. Data that is set as a result of these events has to be manually seeded by ourselves.
- Exceptions are forbidden (not blocked, but please avoid them at all cost). Things like `firstOrFail` or manually throwing exceptions when data dependencies don't exist shouldn't be needed. If something doesn't exist it's a bug, we should fix the seeder, not conditionally insert something only when data is present.
