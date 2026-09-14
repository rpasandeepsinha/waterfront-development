<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    public function up(): void
    {
        $sqlUpdateDeployments = <<<SQL
        update domain_subscriptions set private_whois_enabled = true
        where id in (
        	select domain_deployments.id from subscriptions as privacy_protect_subscriptions
        	join products as privacy_protect_product on privacy_protect_subscriptions.product_uuid = privacy_protect_product."uuid"
        	join (
        		select extension_deployment.id, extension_subscriptions."domain" from subscriptions as extension_subscriptions
        		join products as extension_products on extension_subscriptions.product_uuid = extension_products."uuid"
        		join product_groups as extension_product_group on extension_product_group.id = extension_products.product_group_id
        		join domain_subscriptions as extension_deployment on extension_deployment.subscription_uuid = extension_subscriptions."uuid"
        		where extension_product_group.slug = 'extension' and extension_deployment.private_whois_enabled = false
        	) domain_deployments on domain_deployments."domain" = privacy_protect_subscriptions."domain"
        	where privacy_protect_product.slug = 'extension_privacy_protection'
        )
        SQL;

        $sqlUpdateSubscriptions = <<<SQL
        update subscriptions set administrative_status = 'archived', termination_date = NOW(), end_date = NOW()
        where id in (
        	select privacy_protect_subscriptions.id from subscriptions as privacy_protect_subscriptions
        	join products as privacy_protect_product on privacy_protect_subscriptions.product_uuid = privacy_protect_product."uuid"
        	where privacy_protect_product.slug = 'extension_privacy_protection' and administrative_status != 'archived'
        )
        SQL;

        $updateProducts = <<<SQL
        update products set deleted_at = NOW() where slug = 'extension_privacy_protection'
        SQL;

        $updateProductGroups = <<<SQL
        update product_groups set deleted_at = NOW() where slug = 'domein-uitbreiding'
        SQL;

        DB::statement($sqlUpdateDeployments);
        DB::statement($sqlUpdateSubscriptions);
        DB::statement($updateProducts);
        DB::statement($updateProductGroups);
    }
};
