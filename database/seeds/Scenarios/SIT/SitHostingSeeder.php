<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Scenarios\SIT;

use Database\Seeders\Products\ProductReference;
use Database\Seeders\Scenarios\SIT\Hosting\VersioHostingSeeder;
use Database\Seeders\Scenarios\SIT\Hosting\YourhostingHostingSeeder;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Waterfront\Domain\DNS\Models\DnsTemplate;
use Waterfront\Domain\DNS\Models\DnsTemplateRecordSet;
use Waterfront\Domain\DNS\Models\DnsTemplateRecordSetRow;
use Waterfront\Domain\Hosting\Models\SpamExpertsCluster;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Providers\Enums\ProviderSettingKey;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Providers\Models\ProviderSetting;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class SitHostingSeeder extends Seeder
{
    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly ReferenceRepository $referenceRepo,
    ) {
    }

    public function run(): void
    {
        $group = new ProductGroup();
        $group->uuid = Str::uuid()->toString();
        $group->name = 'Hosting';
        $group->ledger_code = 8008;
        $group->slug = ProductGroupType::HOSTING;
        $group->save();
        $this->referenceRepo->set(ProductReference::HOSTING_GROUP, $group);

        $tenantName = $this->configuration->getAsString('app.tenant_name');

        match ($tenantName) {
            'versio' => $this->seedVersio(),
            default => $this->seedYourhosting(),
        };

        $this->servers();
        $this->dnsTemplates();
        $this->providers();
        $this->spamExperts();
    }

    private function seedVersio(): void
    {
        // Also run the YourHosting defaults to set all the references as they are quite specifically set and
        // would not make sense in the context of Versio but are way too much work to properly set versio variations
        // on the other seeders. So just spawning some defaults for the other seeders is fine.
        $this->call(YourhostingHostingSeeder::class);
        $this->call(VersioHostingSeeder::class);
    }

    private function seedYourhosting(): void
    {
        $this->call(YourhostingHostingSeeder::class);
    }

    private function dnsTemplates(): void
    {
        $template = new DnsTemplate();
        $template->slug = 'hosting';
        $template->save();

        $set = new DnsTemplateRecordSet();
        $set->name = 'mail.{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = 'smtp.{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = 'www.{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '*.{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '{domain}';
        $set->type = 'MX';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '10 primary.sandwave.testing.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '20 fallback.sandwave.testing.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '{domain}';
        $set->type = 'TXT';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '"v=spf1 include:_spf.sandwave.testing mx a ~all"';
        $row->template_record_set_id = $set->id;
        $row->save();

        $template = new DnsTemplate();
        $template->slug = 'external-hosting';
        $template->save();

        $set = new DnsTemplateRecordSet();
        $set->name = 'mail.{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = 'smtp.{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = 'www.{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '*.{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '{domain}';
        $set->type = 'MX';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '10 primary.sandwave.testing.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '20 fallback.sandwave.testing.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '{domain}';
        $set->type = 'TXT';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '"v=spf1 include:_spf.sandwave.testing mx a ~all"';
        $row->template_record_set_id = $set->id;
        $row->save();
    }

    private function providers(): void
    {
        $tenantName = $this->configuration->getAsString('app.tenant_name');

        $provider = new Provider();
        $provider->slug = ProviderSlug::DIRECTADMIN;
        $provider->type = ProviderType::HOSTING;
        $provider->enabled = true;
        $provider->default = $tenantName === 'versio' || $tenantName === 'seasidehosting';
        $provider->save();
        $this->referenceRepo->set(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, $provider);
        $this->referenceRepo->set(ProductReference::HOSTING_PROVIDER_DEFAULT, $provider);

        $provider = new Provider();
        $provider->slug = ProviderSlug::PLESK;
        $provider->type = ProviderType::HOSTING;
        $provider->enabled = true;
        $provider->default = $tenantName === 'yourhosting';
        $provider->save();
        $this->referenceRepo->set(ProductReference::HOSTING_PROVIDER_PLESK, $provider);

        $provider = new Provider();
        $provider->slug = ProviderSlug::PLACEHOLDER;
        $provider->type = ProviderType::HOSTING;
        $provider->enabled = true;
        $provider->default = false;
        $provider->save();
        $this->referenceRepo->set(ProductReference::HOSTING_PROVIDER_PLACEHOLDER, $provider);

        $provider = new Provider();
        $provider->slug = ProviderSlug::PLESK;
        $provider->type = ProviderType::MAILONLY;
        $provider->enabled = true;
        $provider->default = $tenantName === 'yourhosting';
        $provider->save();

        $this->referenceRepo->set(ProductReference::HOSTING_PROVIDER_MAIL_ONLY_PLESK, $provider);

        $server = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_SERVER_DIRECT_ADMIN, Server::class);
        $provider = new Provider();
        $provider->type = ProviderType::MAILONLY;
        $provider->slug = ProviderSlug::DIRECTADMIN;
        $provider->enabled = true;
        $provider->default = $tenantName === 'versio' || $tenantName === 'seasidehosting';
        $provider->save();

        $providerSetting = new ProviderSetting();
        $providerSetting->key = ProviderSettingKey::LIMIT;
        $providerSetting->value = '99';
        $providerSetting->provider_id = $provider->id;
        $providerSetting->save();

        $providerSetting = new ProviderSetting();
        $providerSetting->key = ProviderSettingKey::QUOTA;
        $providerSetting->value = '6666';
        $providerSetting->provider_id = $provider->id;
        $providerSetting->save();

        $providerSetting = new ProviderSetting();
        $providerSetting->key = ProviderSettingKey::DEFAULTSERVERID;
        $providerSetting->value = (string) $server->id;
        $providerSetting->provider_id = $provider->id;
        $providerSetting->save();

        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_PROVIDER_DIRECT_ADMIN, $provider);

        $provider = new Provider();
        $provider->type = ProviderType::MAILONLY;
        $provider->slug = ProviderSlug::PLACEHOLDER;
        $provider->enabled = true;
        $provider->default = false;
        $provider->save();
        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_PROVIDER_PLACEHOLDER, $provider);
    }

    private function servers(): void
    {
        $mockHostname = $this->configuration->getAsString('app.mock_hostname');

        $server = new Server();
        $server->type = ServerType::PLESK;
        $server->name = ServerType::PLESK->value;
        $server->hostname = $mockHostname;
        $server->domain = $mockHostname;
        $server->ipv4 = '127.0.0.1';
        $server->ipv6 = '::2';
        $server->port = 443;
        $server->use_ssl = true;
        $server->allow_new_websites = true;
        $server->maximum_websites = 9999;
        $server->username = 'admin';
        $server->password = 'changeme';
        $server->loginkey = 'TestLoginKey';
        $server->endpoint = '/plesk';
        $server->secret_key = '53de495a-b405-ca64-35d3-54b0352edeec';
        $server->save();
        $this->referenceRepo->set(ProductReference::HOSTING_SERVER_PLESK, $server);

        $server = new Server();
        $server->type = ServerType::DIRECTADMIN;
        $server->name = ServerType::DIRECTADMIN->value;
        $server->hostname = $mockHostname;
        $server->domain = $mockHostname;
        $server->ipv4 = '127.0.0.1';
        $server->ipv6 = '::1';
        $server->port = 443;
        $server->use_ssl = true;
        $server->allow_new_websites = true;
        $server->maximum_websites = 9999;
        $server->username = 'admin';
        $server->password = 'changeme';
        $server->loginkey = 'TestLoginKey';
        $server->save();
        $this->referenceRepo->set(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, $server);

        $server = new Server();
        $server->type = ServerType::DIRECTADMIN_MAIL;
        $server->name = ServerType::DIRECTADMIN_MAIL->value;
        $server->hostname = $mockHostname;
        $server->domain = $mockHostname;
        $server->ipv4 = '127.0.0.1';
        $server->ipv6 = '::1';
        $server->port = 443;
        $server->use_ssl = true;
        $server->allow_new_websites = true;
        $server->maximum_websites = 9999;
        $server->username = 'admin';
        $server->password = 'changeme';
        $server->loginkey = 'TestLoginKey';
        $server->save();
        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_SERVER_DIRECT_ADMIN, $server);
    }

    private function spamExperts(): void
    {
        $mockHostname = $this->configuration->getAsString('app.mock_hostname');

        $cluster = new SpamExpertsCluster();
        $cluster->hostname = "https://$mockHostname";
        $cluster->business_unit = 'Versio';
        $cluster->username = 'mock';
        $cluster->password = 'mock_encrypted_password';
        $cluster->ssl = false; // this is the "verify" SSL setting
        $cluster->save();
    }
}
