<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS public.migration_state');
        DB::statement('DROP VIEW IF EXISTS public.migration_invoicing_state');

        Schema::rename('hosting_subscriptions', 'hosting_deployments');
        Schema::rename('reseller_hosting_subscriptions', 'reseller_hosting_deployments');
        Schema::rename('domain_subscriptions', 'domain_deployments');
        Schema::rename('ssl_subscriptions', 'ssl_deployments');
        Schema::rename('dns_nameserver_domain_subscription', 'dns_nameserver_domain_deployments');
        Schema::rename('microsoft365_subscriptions', 'microsoft365_deployments');
        Schema::rename('cloudstack_vm_subscriptions', 'cloudstack_vm_deployments');
        Schema::rename('cloudstack_managerdomain_subscriptions', 'cloudstack_managerdomain_deployments');
        Schema::rename('cloudstack_volume_subscriptions', 'cloudstack_volume_deployments');

        Schema::table('domain_provider_status', function (Blueprint $table) {
            $table->renameColumn('domain_subscription_id', 'domain_deployment_id');
        });
        Schema::table('cloudstack_vm_deployments', function (Blueprint $table) {
            $table->renameColumn('manager_domain_subscription_id', 'manager_domain_deployment_id');
        });
        Schema::table('microsoft365_sync_log', function (Blueprint $table) {
            $table->renameColumn('microsoft365_subscription_id', 'microsoft365_deployment_id');
        });
        Schema::table('cloudstack_jobs', function (Blueprint $table) {
            $table->renameColumn('vm_subscription_id', 'vm_deployment_id');
        });
        Schema::table('dns_nameserver_domain_deployments', function (Blueprint $table) {
            $table->renameColumn('domain_subscription_id', 'domain_deployment_id');
        });
        Schema::table('cloudstack_volume_deployments', function (Blueprint $table) {
            $table->renameColumn('manager_domain_subscription_id', 'manager_domain_deployment_id');
        });

        DB::statement(<<<SQL
            CREATE VIEW public.migration_state AS
            SELECT row_number() OVER () AS id,
                mc.reference_customer_number AS external_customer_id,
                c.id AS waterfront_customer_id,
                c.customer_number AS waterfront_customer_number,
                mc.enable_invoicing AS enabled_invoicing,
                mc.group_type AS batch_group,
                mc.reference_name,
                c.email,
                c.first_name,
                c.last_name,
                c.organization,
                c.coc_number,
                c.vat_number,
                c.vat_rate,
                ca.country_code,
                c.phone_country_code,
                c.phone_area_code,
                c.phone_subscriber_number,
                cw.id AS wallet_id,
                cw.amount AS wallet_amount,
                cw.refund_requested_at,
                ms.reference_subscription_id AS legacy_subscription_id,
                s.id AS subscription_id,
                s.domain,
                pg.slug AS product_group,
                p.slug AS product,
                s.gross_price,
                s.net_price,
                s.administrative_status,
                s.technical_status,
                s.updated_at AS subscription_last_updated,
                s.end_date AS renewal_date,
                s.cancel_date,
                s.next_billing_date,
                    CASE
                        WHEN (dp.slug IS NOT NULL) THEN dp.slug
                        WHEN (hph.slug IS NOT NULL) THEN hph.slug
                        WHEN (hpm.slug IS NOT NULL) THEN hpm.slug
                        WHEN (hps.slug IS NOT NULL) THEN hps.slug
                        WHEN (sp.slug IS NOT NULL) THEN sp.slug
                        ELSE NULL::character varying
                    END AS technical_driver,
                    CASE
                        WHEN (hph.slug IS NOT NULL) THEN hser.hostname
                        WHEN (hpm.slug IS NOT NULL) THEN hsm.hostname
                        WHEN (hps.slug IS NOT NULL) THEN hsb.hostname
                        ELSE NULL::character varying
                    END AS server_hostname,
                dcdp.external_contact AS domain_contact_handle_id,
                ( SELECT string_agg((dn.nameserver)::text, ';'::text) AS string_agg
                    FROM (public.dns_nameservers dn
                        JOIN public.dns_nameserver_domain_deployments dnds ON ((dn.id = dnds.dns_nameserver_id)))
                    WHERE (dnds.domain_deployment_id = ds.id)) AS waterfront_nameservers
            FROM (((((((((((((((((((((((public.subscriptions s
                JOIN public.products p ON ((s.product_uuid = p.uuid)))
                JOIN public.product_groups pg ON ((p.product_group_id = pg.id)))
                JOIN public.migrated_subscription_subscription mss ON ((s.id = mss.subscription_id)))
                JOIN public.migrated_subscriptions ms ON ((mss.migrated_subscription_id = ms.id)))
                JOIN public.migrated_customer_migrated_subscription mcms ON ((ms.id = mcms.migrated_subscription_id)))
                JOIN public.migrated_customers mc ON ((mcms.migrated_customer_id = mc.id)))
                JOIN public.customers c ON ((s.customer_id = c.id)))
                JOIN public.customer_addresses ca ON ((c.id = ca.customer_id)))
                LEFT JOIN public.customer_contacts cc ON ((c.id = cc.customer_id)))
                LEFT JOIN public.customer_wallets cw ON ((c.id = cw.customer_id)))
                LEFT JOIN public.domain_deployments ds ON ((s.uuid = ds.subscription_uuid)))
                LEFT JOIN public.providers dp ON ((ds.provider_id = dp.id)))
                LEFT JOIN public.domain_contacts dc ON (((c.id = dc.customer_id) AND (ds.contact_owner_id = dc.id))))
                LEFT JOIN public.domain_contact_provider dcdp ON ((dc.id = dcdp.domain_contact_id)))
                LEFT JOIN public.hosting_deployments hs ON ((s.uuid = hs.subscription_uuid)))
                LEFT JOIN public.providers hph ON ((hs.provider_id = hph.id)))
                LEFT JOIN public.providers hpm ON ((hs.mail_only_provider_id = hpm.id)))
                LEFT JOIN public.providers hps ON ((hs.sitebuilder_provider_id = hps.id)))
                LEFT JOIN public.hosting_servers hser ON ((hser.id = hs.server_id)))
                LEFT JOIN public.hosting_servers hsb ON ((hsb.id = hs.basekit_server_id)))
                LEFT JOIN public.hosting_servers hsm ON ((hsm.id = hs.mail_only_server_id)))
                LEFT JOIN public.ssl_deployments ss ON ((ss.subscription_uuid = s.uuid)))
                LEFT JOIN public.providers sp ON ((ss.provider_id = sp.id)))
            GROUP BY mc.reference_customer_number, c.id, c.customer_number, mc.enable_invoicing, mc.group_type, mc.reference_name, c.email, c.first_name, c.last_name, c.organization, c.coc_number, c.vat_number, c.vat_rate, ca.country_code, c.phone_country_code, c.phone_area_code, c.phone_subscriber_number, cw.id, cw.amount, cw.refund_requested_at, ms.reference_subscription_id, s.id, s.domain, p.slug, s.gross_price, s.net_price, pg.slug, s.administrative_status, s.technical_status, s.updated_at, s.end_date, s.cancel_date, s.next_billing_date, dp.slug, dcdp.external_contact, ds.id, hph.slug, hpm.slug, hps.slug, sp.slug, hser.hostname, hsm.hostname, hsb.hostname
            ORDER BY s.id DESC
          SQL);

        DB::statement(<<<SQL
          CREATE VIEW public.migration_invoicing_state AS
           SELECT base.batch_group,
                  CASE
                      WHEN (base.sent_to_harbor_at IS NOT NULL) THEN 'successfully propagated'::text
                      WHEN (base.sent_to_harbor_at IS NULL) THEN 'stuck in waterfront'::text
                      ELSE NULL::text
                  END AS invoicing_state,
              count(base.invoice_line_id) AS invoice_item_count,
              sum(base.invoice_line_net_price) AS net_sum_in_cents
             FROM ( SELECT mc.group_type AS batch_group,
                      ms.reference_subscription_id AS legacy_subscription_id,
                      inv.id AS invoice_line_id,
                      inv.net_price AS invoice_line_net_price,
                      inv.sent_to_harbor_at
                     FROM (((((((((((((public.invoices inv
                       JOIN public.subscriptions s ON ((s.id = inv.subscription_id)))
                       JOIN public.products p ON ((s.product_uuid = p.uuid)))
                       JOIN public.product_groups pg ON ((p.product_group_id = pg.id)))
                       JOIN public.migrated_subscription_subscription mss ON ((s.id = mss.subscription_id)))
                       JOIN public.migrated_subscriptions ms ON ((mss.migrated_subscription_id = ms.id)))
                       JOIN public.migrated_customer_migrated_subscription mcms ON ((ms.id = mcms.migrated_subscription_id)))
                       JOIN public.migrated_customers mc ON ((mcms.migrated_customer_id = mc.id)))
                       JOIN public.customers c ON ((s.customer_id = c.id)))
                       JOIN public.customer_addresses ca ON ((c.id = ca.customer_id)))
                       LEFT JOIN public.customer_contacts cc ON ((c.id = cc.customer_id)))
                       LEFT JOIN public.customer_wallets cw ON ((c.id = cw.customer_id)))
                       LEFT JOIN public.domain_deployments ds ON ((s.uuid = ds.subscription_uuid)))
                       LEFT JOIN public.domain_contacts dc ON (((c.id = dc.customer_id) AND (ds.contact_owner_id = dc.id))))
                    GROUP BY mc.group_type, ms.reference_subscription_id, inv.id, inv.net_price, inv.sent_to_harbor_at) base
            GROUP BY base.batch_group,
                  CASE
                      WHEN (base.sent_to_harbor_at IS NOT NULL) THEN 'successfully propagated'::text
                      WHEN (base.sent_to_harbor_at IS NULL) THEN 'stuck in waterfront'::text
                      ELSE NULL::text
                  END
            ORDER BY base.batch_group,
                  CASE
                      WHEN (base.sent_to_harbor_at IS NOT NULL) THEN 'successfully propagated'::text
                      WHEN (base.sent_to_harbor_at IS NULL) THEN 'stuck in waterfront'::text
                      ELSE NULL::text
                  END;
          SQL);
    }
};
