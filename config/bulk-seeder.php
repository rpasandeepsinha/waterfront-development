<?php

declare(strict_types=1);

return [
    // customers and base subscriptions
    'customer_amount' => 5000,
    'customers_for_100_subscriptions' => 10,
    'customers_for_1000_subscriptions' => 1,
    'customers_for_10000_subscriptions' => 1,
    'customers_coupled_to_discount' => 1,

    // child subscriptions
    'child_subscriptions_amount' => 300,

    // renewals
    'amount_of_renewables' => 500,
    'amount_of_years_in_the_past_renewables' => 3,
    'amount_of_years_in_the_past_renewables_amount' => 50,

    // invoicing
    'amount_of_subscriptions_to_be_invoiced' => 1500,
    'amount_of_months_in_the_past_invoicing' => 18,

    // cancellations
    'amount_of_subscriptions_to_be_canceled' => 500,

    // Ferry migrations
    'amount_of_customers_with_migrated_subscriptions' => 100,
];
