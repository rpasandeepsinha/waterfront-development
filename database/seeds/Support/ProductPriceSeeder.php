<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\Enums\ProductPriceType;

class ProductPriceSeeder
{
    public function load(string $scenarioFile): void
    {
        $filename = $this->getFilename($scenarioFile);
        if (! file_exists($filename)) {
            throw new RuntimeException(sprintf('Scenario "%s" does not exist.', $filename));
        }

        $this->loadFile($filename);
    }

    private function getFilename(string $scenarioFile): string
    {
        return sprintf('%s/Data/%s.csv', __DIR__, $scenarioFile);
    }

    private function loadFile(string $filename): void
    {
        $file = fopen($filename, 'rb');
        if ($file === false) {
            throw new RuntimeException(sprintf('Could not open file "%s".', $filename));
        }

        $header = false;
        while (! feof($file)) {
            $row = fgetcsv($file, 0, escape: '\\');
            if (! $header) {
                $header = true;
                continue;
            }

            if (! is_array($row)) {
                continue;
            }

            $this->productPrices($row);
            $this->registrations($row);
            $this->prolongations($row);

            if ($row[5] === '' || $row[4] !== '') {
                $this->promotions($row);
            }

            if ($row[8] !== '') {
                $this->introductions($row);
            }
        }
    }

    /**
     * @param list<string|null> $row
     */
    private function productPrices(array $row): void
    {
        $query = <<<SQL

        insert into public.product_prices (product_id,billing_period,"type",regular_price,promotion_price,created_at,updated_at,product_discount_id,contract_period,translation_key_id,introduction_price,orderable,action_period,action_period_price,is_default)
        select
            id,
            :billingPeriod,
            :type,
            :regularPrice,
            :promotionPrice,
            now(),
            now(),
            :productDiscountId,
            :contractPeriod,
            :translationKey,
            :introductionPrice,
            :orderable,
            :actionPeriod,
            :actionPeriodPrice,
            :isDefault
        from products
        where (slug = :productSlug or slug = concat('local-', :productSlug))
          and exists (select 1 from product_discounts where id = :productDiscountId or :productDiscountId is null)
        on conflict on constraint unique_product_price do update set
            regular_price = EXCLUDED.regular_price,
            promotion_price = EXCLUDED.promotion_price,
            updated_at = now(),
            product_discount_id = EXCLUDED.product_discount_id,
            contract_period = EXCLUDED.contract_period,
            translation_key_id = EXCLUDED.translation_key_id,
            introduction_price = EXCLUDED.introduction_price,
            orderable = EXCLUDED.orderable,
            action_period = EXCLUDED.action_period,
            action_period_price = EXCLUDED.action_period_price,
            is_default = EXCLUDED.is_default
        SQL;

        DB::statement($query, [
            'productSlug' => $row[0],
            'billingPeriod' => (int) $row[1],
            'type' => $row[2],
            'regularPrice' => (int) $row[3],
            'promotionPrice' => $row[4] === '' ? null : (int) $row[4],
            'productDiscountId' => $row[5] === '' ? null : (int) $row[5],
            'contractPeriod' => (int) $row[6],
            'translationKey' => $row[7] === '' ? null : $row[7],
            'introductionPrice' => $row[8] === '' ? null : (int) $row[8],
            'orderable' => $row[9] === 'true',
            'actionPeriod' => $row[10] === '' ? null : $row[10],
            'actionPeriodPrice' => $row[11] === '' ? null : (int) $row[11],
            'isDefault' => $row[12] === 'true',
        ]);

        if ($row[5] !== '' && $row[2] !== null) {
            $priceType = match (ProductPriceType::from($row[2])) {
                ProductPriceType::REGISTRATION => PriceComponentType::REGISTRATION_STAFFEL,
                ProductPriceType::PROLONGATION => PriceComponentType::PROLONGATION_STAFFEL,
            };

            $query = <<<SQL
            insert into public.product_price_components (product_id,"type",billing_period,price,created_at,updated_at,contract_period,orderable,starts_at)
            select
                id,
                :type,
                :billingPeriod,
                :staffelPrice,
                now(),
                now(),
                :contractPeriod,
                :orderable,
                now()
            from products
            where (slug = :productSlug or slug = concat('local-', :productSlug))
            SQL;

            DB::statement($query, [
                'productSlug' => $row[0],
                'type' => $priceType->value,
                'billingPeriod' => (int) $row[1],
                'staffelPrice' => (int) $row[3],
                'contractPeriod' => (int) $row[6],
                'orderable' => $row[9] === 'true',
            ]);
        }
    }

    /**
     * @param list<string|null> $row
     */
    private function registrations(array $row): void
    {
        $query = <<<SQL

        insert into public.product_price_components (product_id,"type",billing_period,price,created_at,updated_at,contract_period,orderable,starts_at)
        select
            id,
            :type,
            :billingPeriod,
            :registrationPrice,
            now(),
            now(),
            :contractPeriod,
            :orderable,
            now()
        from products
        where (slug = :productSlug or slug = concat('local-', :productSlug))
        SQL;

        DB::statement($query, [
            'productSlug' => $row[0],
            'type' => 'registration',
            'billingPeriod' => (int) $row[1],
            'registrationPrice' => (int) $row[3],
            'contractPeriod' => (int) $row[6],
            'orderable' => $row[9] === 'true',
        ]);
    }

    /**
     * @param list<string|null> $row
     */
    private function prolongations(array $row): void
    {
        $query = <<<SQL

        insert into public.product_price_components (product_id,"type",billing_period,price,created_at,updated_at,contract_period,orderable,starts_at)
        select
            id,
            :type,
            :billingPeriod,
            :registrationPrice,
            now(),
            now(),
            :contractPeriod,
            :orderable,
            now()
        from products
        where (slug = :productSlug or slug = concat('local-', :productSlug))
        SQL;

        DB::statement($query, [
            'productSlug' => $row[0],
            'type' => 'prolongation',
            'billingPeriod' => (int) $row[1],
            'registrationPrice' => (int) $row[3],
            'contractPeriod' => (int) $row[6],
            'orderable' => $row[9] === 'true',
        ]);
    }

    /**
     * @param list<string|null> $row
     */
    private function promotions(array $row): void
    {
        $query = <<<SQL

        insert into public.product_price_components (product_id,"type",billing_period,price,created_at,updated_at,contract_period,orderable,starts_at)
        select
            id,
            :type,
            :billingPeriod,
            :promotionPrice,
            now(),
            now(),
            :contractPeriod,
            :orderable,
            now()
        from products
        where (slug = :productSlug or slug = concat('local-', :productSlug))
        SQL;

        DB::statement($query, [
            'productSlug' => $row[0],
            'type' => 'promotion',
            'billingPeriod' => (int) $row[1],
            'promotionPrice' => (int) $row[4],
            'contractPeriod' => (int) $row[6],
            'orderable' => $row[9] === 'true',
        ]);
    }

    /**
     * @param list<string|null> $row
     */
    private function introductions(array $row): void
    {
        $query = <<<SQL

        insert into public.product_price_components (product_id,"type",billing_period,price,created_at,updated_at,contract_period,orderable,starts_at)
        select
            id,
            :type,
            :billingPeriod,
            :introductionPrice,
            now(),
            now(),
            :contractPeriod,
            :orderable,
            now()
        from products
        where (slug = :productSlug or slug = concat('local-', :productSlug))
        SQL;

        DB::statement($query, [
            'productSlug' => $row[0],
            'type' => 'introduction',
            'billingPeriod' => (int) $row[1],
            'introductionPrice' => (int) $row[8],
            'contractPeriod' => (int) $row[6],
            'orderable' => $row[9] === 'true',
        ]);
    }
}
