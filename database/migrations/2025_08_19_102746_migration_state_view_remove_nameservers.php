<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS public.migration_state');

        /*
         * In this version we removed the "waterfront_nameservers" field:
         * https://yh-jira.atlassian.net/browse/SWD-9204
         *
         * Now that we've done a lot of migrations the migration state overview page that uses this gave timeouts. (15 secs+ on the request)
         * The main reason is that views are "a lot" slower because the database server can't optimize them.
         */
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
                dcdp.external_contact AS domain_contact_handle_id
            FROM public.subscriptions s
                JOIN public.products p ON s.product_uuid = p.uuid
                JOIN public.product_groups pg ON p.product_group_id = pg.id
                JOIN public.migrated_subscription_subscription mss ON s.id = mss.subscription_id
                JOIN public.migrated_subscriptions ms ON mss.migrated_subscription_id = ms.id
                JOIN public.migrated_customer_migrated_subscription mcms ON ms.id = mcms.migrated_subscription_id
                JOIN public.migrated_customers mc ON mcms.migrated_customer_id = mc.id
                JOIN public.customers c ON s.customer_id = c.id
                JOIN public.customer_addresses ca ON c.id = ca.customer_id
                LEFT JOIN public.customer_contacts cc ON c.id = cc.customer_id
                LEFT JOIN public.customer_wallets cw ON c.id = cw.customer_id
                LEFT JOIN public.domain_deployments ds ON s.uuid = ds.subscription_uuid
                LEFT JOIN public.providers dp ON ds.provider_id = dp.id
                LEFT JOIN public.domain_contacts dc ON c.id = dc.customer_id AND ds.contact_owner_id = dc.id
                LEFT JOIN public.domain_contact_provider dcdp ON dc.id = dcdp.domain_contact_id
                LEFT JOIN public.hosting_deployments hs ON s.uuid = hs.subscription_uuid
                LEFT JOIN public.providers hph ON hs.provider_id = hph.id
                LEFT JOIN public.providers hpm ON hs.mail_only_provider_id = hpm.id
                LEFT JOIN public.providers hps ON hs.sitebuilder_provider_id = hps.id
                LEFT JOIN public.hosting_servers hser ON hser.id = hs.server_id
                LEFT JOIN public.hosting_servers hsb ON hsb.id = hs.basekit_server_id
                LEFT JOIN public.hosting_servers hsm ON hsm.id = hs.mail_only_server_id
                LEFT JOIN public.ssl_deployments ss ON ss.subscription_uuid = s.uuid
                LEFT JOIN public.providers sp ON ss.provider_id = sp.id
            GROUP BY mc.reference_customer_number, c.id, c.customer_number, mc.enable_invoicing, mc.group_type, mc.reference_name, c.email, c.first_name, c.last_name, c.organization, c.coc_number, c.vat_number, c.vat_rate, ca.country_code, c.phone_country_code, c.phone_area_code, c.phone_subscriber_number, cw.id, cw.amount, cw.refund_requested_at, ms.reference_subscription_id, s.id, s.domain, p.slug, s.gross_price, s.net_price, pg.slug, s.administrative_status, s.technical_status, s.updated_at, s.end_date, s.cancel_date, s.next_billing_date, dp.slug, dcdp.external_contact, ds.id, hph.slug, hpm.slug, hps.slug, sp.slug, hser.hostname, hsm.hostname, hsb.hostname
            ORDER BY s.id DESC
          SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS public.migration_state');

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
    }
};
