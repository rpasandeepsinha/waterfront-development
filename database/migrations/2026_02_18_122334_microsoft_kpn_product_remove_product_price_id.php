<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        $addColumns = <<<SQL
ALTER TABLE microsoft365_kpn_product
    ADD product_id bigint NULL,
    ADD contract_period int4 NULL,
    ADD CONSTRAINT microsoft365_kpn_product_products_fk FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE;
SQL;

        $changeColumns = <<<SQL
ALTER TABLE microsoft365_kpn_product ALTER COLUMN product_id SET NOT NULL, ALTER COLUMN contract_period SET NOT NULL, DROP COLUMN product_price_id;
SQL;

        $updateColumns = <<<SQL
update microsoft365_kpn_product set product_id = product_prices.product_id, contract_period = product_prices.contract_period
from product_prices
where microsoft365_kpn_product.product_price_id = product_prices.id;
SQL;

        DB::statement($addColumns);
        DB::statement($updateColumns);
        DB::statement($changeColumns);
    }
};
