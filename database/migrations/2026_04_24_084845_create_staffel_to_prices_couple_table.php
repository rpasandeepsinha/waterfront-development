<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('product_discount_prices', function (Blueprint $table) {
            $table->foreignId('product_discount_id')->constrained('product_discounts');
            $table->foreignId('price_id')->constrained('prices');

            $table->unique(['price_id']);
        });

        $query = <<<SQL
with src as (
    select
        pd.id as product_price_id, pp.product_id, pp.regular_price, pp.type, pp.contract_period, pp.billing_period, pp.orderable, pp.created_at, pp.updated_at,
        row_number() over () as "row_number"
    from product_discounts pd
    join product_prices pp on pd.id = pp.product_discount_id
    where pp."type" in ('registration', 'prolongation', 'transfer')
), insert_prices as (
    insert into prices (product_id,"type",contract_period,billing_period,price,orderable,created_at,updated_at,starts_at,expires_at)
    select
        src.product_id,
        case
            when src."type" = 'registration' then 'registration-staffel'
            when src."type" = 'prolongation' then 'prolongation-staffel'
            when src."type" = 'transfer' then 'transfer-staffel'
        end as "type",
        src.contract_period,
        src.billing_period,
        src.regular_price,
        src.orderable,
        src.created_at,
        src.updated_at,
        src.created_at as starts_at,
        null as expires_at
    from src
    returning id
), inserted_rows as (
    select id, row_number() over () as "row_number" from insert_prices
)
insert into product_discount_prices (product_discount_id, price_id)
select src.product_price_id, inserted_rows.id AS price_id
from inserted_rows
join src on src."row_number" = inserted_rows."row_number"
SQL;

        DB::statement($query);
    }
};
