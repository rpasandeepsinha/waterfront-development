<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS public.cancellation_individual_subscription');

        DB::statement(<<<SQL
            CREATE VIEW public.cancellation_individual_subscription AS
             SELECT s.id AS subscription_id,
                s.customer_id,
                s.start_date AS started_at,
                s.cancel_date AS canceled_at,
                s.end_date,
                    CASE
                        WHEN (mss.migrated_subscription_id IS NOT NULL) THEN 'yes'::text
                        WHEN (mss.migrated_subscription_id IS NULL) THEN 'no'::text
                        ELSE NULL::text
                    END AS is_migrated,
                ms.created_at AS migrated_at,
                pg.slug AS product_group_slug,
                p.slug AS product_slug,
                s.net_price AS current_net_price,
                ( SELECT string_agg(DISTINCT (ppi.net_price)::text, ','::text) AS previously_paid_net_prices
                       FROM public.invoices ppi
                      WHERE (ppi.subscription_id = s.id)) AS previously_paid_net_prices
               FROM (((((public.subscriptions s
                 LEFT JOIN public.migrated_subscription_subscription mss ON ((s.id = mss.subscription_id)))
                 LEFT JOIN public.migrated_subscriptions ms ON ((mss.migrated_subscription_id = ms.id)))
                 JOIN public.products p ON ((s.product_uuid = p.uuid)))
                 JOIN public.product_groups pg ON ((p.product_group_id = pg.id))))
              WHERE (((s.administrative_status)::text = 'canceled'::text) AND (s.end_date > CURRENT_DATE))
              GROUP BY s.id, s.customer_id, s.start_date, s.cancel_date, s.end_date, ms.created_at, pg.slug, p.slug, s.net_price, mss.migrated_subscription_id
              ORDER BY s.id DESC;
          SQL);

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['product_price_id']);
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('product_price_id');
        });
    }
};
