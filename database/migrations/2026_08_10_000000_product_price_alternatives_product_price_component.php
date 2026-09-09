<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement(<<<SQL
alter table product_price_alternatives
    add column product_id int8 null,
    add column billing_period int4 null,
    add column contract_period int4 null
SQL);

        DB::statement(<<<SQL
update product_price_alternatives
set billing_period = product_prices.billing_period,
	contract_period = product_prices.contract_period,
	product_id = product_prices.product_id
from product_prices
where product_prices.id = product_price_alternatives.product_price_id
SQL);

        DB::statement(<<<SQL
alter table product_price_alternatives
    alter column product_id set not null,
    alter column billing_period set not null,
    alter column contract_period set not null,
    drop column product_price_id,
    add constraint product_price_alternatives_products_fk foreign key (product_id) references products(id);
SQL);
    }
};
