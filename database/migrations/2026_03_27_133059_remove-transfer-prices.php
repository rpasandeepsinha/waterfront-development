<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        $sqlDeletePrices = <<<SQL
delete from product_prices where id in (
    SELECT ppro.id
    FROM product_prices ppro
             JOIN product_prices ppreg
                   ON ppreg.contract_period = ppro.contract_period
                       AND ppreg.billing_period = ppro.billing_period
                       AND ppreg.regular_price = ppro.regular_price
                       AND ppro.product_id = ppreg.product_id
                       AND ppreg.product_discount_id is null
                       and ppro.product_discount_id is null
                       and ppro.promotion_price is null
                       and ppro.introduction_price is null
                       and ppreg.type = 'registration'
    WHERE ppro.type = 'transfer'
);
SQL;

        DB::statement($sqlDeletePrices);
    }
};
