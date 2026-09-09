<?php

declare(strict_types=1);

namespace Database\Seeders\Products;

use Database\Seeders\Support\ProductPriceGenerator;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\ProductAllowedChange;

class AcronisSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepo,
    ) {
    }

    public function run(): void
    {
        $group = new ProductGroup();
        $group->uuid = Str::uuid()->toString();
        $group->name = 'Backup';
        $group->slug = ProductGroupType::BACKUP;
        $group->ledger_code = 9998;
        $group->save();

        foreach ($this->backupPlans() as $plan) {
            $this->seedBackupPlan(group: $group, plan: $plan);
        }

        $this->acronisProviders();
        $this->upgrades();
    }

    /**
     * @return array<int, array{
     *   size:int,
     *   basePrice:int,
     *   weight:int,
     *   cloud_storage_gb:int,
     *   local_storage_gb:int,
     *   mobile_devices:int,
     *   workstations:int,
     *   virtual_machines:int,
     *   servers:int,
     *   hosting_servers:int,
     *   m365_seats:int,
     *   m365_sharepoint_sites:int,
     *   m365_teams:int,
     *   google_workspace_seats:int,
     *   websites:int
     * }>
     */
    private function backupPlans(): array
    {
        return [
            [
                'size' => 50,
                'basePrice' => 1000,
                'weight' => 9999,
                'cloud_storage_gb' => 50,
                'local_storage_gb' => 50,
                'mobile_devices' => 1,
                'workstations' => 1,
                'virtual_machines' => 0,
                'servers' => 0,
                'hosting_servers' => 0,
                'm365_seats' => 0,
                'm365_sharepoint_sites' => 0,
                'm365_teams' => 0,
                'google_workspace_seats' => 0,
                'websites' => 0,
            ],
            [
                'size' => 100,
                'basePrice' => 1500,
                'weight' => 9999,
                'cloud_storage_gb' => 100,
                'local_storage_gb' => 100,
                'mobile_devices' => 2,
                'workstations' => 2,
                'virtual_machines' => 0,
                'servers' => 0,
                'hosting_servers' => 0,
                'm365_seats' => 0,
                'm365_sharepoint_sites' => 0,
                'm365_teams' => 0,
                'google_workspace_seats' => 0,
                'websites' => 0,
            ],
            [
                'size' => 250,
                'basePrice' => 2500,
                'weight' => 9999,
                'cloud_storage_gb' => 250,
                'local_storage_gb' => 250,
                'mobile_devices' => 5,
                'workstations' => 5,
                'virtual_machines' => 1,
                'servers' => 0,
                'hosting_servers' => 0,
                'm365_seats' => 0,
                'm365_sharepoint_sites' => 0,
                'm365_teams' => 0,
                'google_workspace_seats' => 0,
                'websites' => 0,
            ],
            [
                'size' => 500,
                'basePrice' => 4000,
                'weight' => 9999,
                'cloud_storage_gb' => 500,
                'local_storage_gb' => 500,
                'mobile_devices' => 10,
                'workstations' => 10,
                'virtual_machines' => 2,
                'servers' => 1,
                'hosting_servers' => 1,
                'm365_seats' => 1,
                'm365_sharepoint_sites' => 1,
                'm365_teams' => 1,
                'google_workspace_seats' => 1,
                'websites' => 1,
            ],
            [
                'size' => 1000,
                'basePrice' => 7000,
                'weight' => 9999,
                'cloud_storage_gb' => 1000,
                'local_storage_gb' => 1000,
                'mobile_devices' => 25,
                'workstations' => 25,
                'virtual_machines' => 5,
                'servers' => 2,
                'hosting_servers' => 2,
                'm365_seats' => 2,
                'm365_sharepoint_sites' => 2,
                'm365_teams' => 2,
                'google_workspace_seats' => 2,
                'websites' => 2,
            ],
            [
                'size' => 2000,
                'basePrice' => 12000,
                'weight' => 9999,
                'cloud_storage_gb' => 2000,
                'local_storage_gb' => 2000,
                'mobile_devices' => 50,
                'workstations' => 50,
                'virtual_machines' => 10,
                'servers' => 5,
                'hosting_servers' => 5,
                'm365_seats' => 5,
                'm365_sharepoint_sites' => 5,
                'm365_teams' => 5,
                'google_workspace_seats' => 5,
                'websites' => 5,
            ],
        ];
    }

    /**
     * @param array{
     *   size:int,
     *   basePrice:int,
     *   weight:int,
     *   cloud_storage_gb:int,
     *   local_storage_gb:int,
     *   mobile_devices:int,
     *   workstations:int,
     *   virtual_machines:int,
     *   servers:int,
     *   hosting_servers:int,
     *   m365_seats:int,
     *   m365_sharepoint_sites:int,
     *   m365_teams:int,
     *   google_workspace_seats:int,
     *   websites:int
     * } $plan
     */
    private function seedBackupPlan(ProductGroup $group, array $plan): void
    {
        $size = $plan['size'];

        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = sprintf('Backup %d', $size);
        $product->slug = sprintf('backup-%d', $size);
        $product->description = sprintf('Acronis backup %d', $size);
        $product->orderable = true;
        $product->weight = $plan['weight'];
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => ProductSpecName::ACRONIS_CLOUD_STORAGE_GB->value, 'value' => $plan['cloud_storage_gb'], 'product_id' => $product->id],
            ['name' => ProductSpecName::ACRONIS_LOCAL_STORAGE_GB->value, 'value' => $plan['local_storage_gb'], 'product_id' => $product->id],
            ['name' => ProductSpecName::ACRONIS_MOBILE_DEVICES->value, 'value' => $plan['mobile_devices'], 'product_id' => $product->id],
            ['name' => ProductSpecName::ACRONIS_WORKSTATIONS->value, 'value' => $plan['workstations'], 'product_id' => $product->id],
            ['name' => ProductSpecName::ACRONIS_VMS->value, 'value' => $plan['virtual_machines'], 'product_id' => $product->id],
            ['name' => ProductSpecName::ACRONIS_SERVERS->value, 'value' => $plan['servers'], 'product_id' => $product->id],
            ['name' => ProductSpecName::ACRONIS_HOSTING_SERVERS->value, 'value' => $plan['hosting_servers'], 'product_id' => $product->id],
            ['name' => ProductSpecName::ACRONIS_M365_SEATS->value, 'value' => $plan['m365_seats'], 'product_id' => $product->id],
            ['name' => ProductSpecName::ACRONIS_M365_SHAREPOINT_SITES->value, 'value' => $plan['m365_sharepoint_sites'], 'product_id' => $product->id],
            ['name' => ProductSpecName::ACRONIS_M365_TEAMS->value, 'value' => $plan['m365_teams'], 'product_id' => $product->id],
            ['name' => ProductSpecName::ACRONIS_GOOGLE_WORKSPACE_SEATS->value, 'value' => $plan['google_workspace_seats'], 'product_id' => $product->id],
            ['name' => ProductSpecName::ACRONIS_WEBSITES->value, 'value' => $plan['websites'], 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, $plan['basePrice']);

        $this->referenceRepo->set($this->productReferenceForSize($size), $product);

        $defaultPrice = $prices
            ->where('contract_period', 1)
            ->where('billing_period', 1)
            ->firstOrFail();

        $this->referenceRepo->set($this->priceReferenceForSize($size), $defaultPrice);
    }

    private function productReferenceForSize(int $size): ProductReference
    {
        return match ($size) {
            50 => ProductReference::BACKUP_ACRONIS_50,
            100 => ProductReference::BACKUP_ACRONIS_100,
            250 => ProductReference::BACKUP_ACRONIS_250,
            500 => ProductReference::BACKUP_ACRONIS_500,
            1000 => ProductReference::BACKUP_ACRONIS_1000,
            2000 => ProductReference::BACKUP_ACRONIS_2000,
            default => throw new InvalidArgumentException(sprintf('Unsupported Acronis backup size: %d', $size)),
        };
    }

    private function priceReferenceForSize(int $size): ProductReference
    {
        return match ($size) {
            50 => ProductReference::BACKUP_ACRONIS_50_REGISTRATION_PRICE,
            100 => ProductReference::BACKUP_ACRONIS_100_REGISTRATION_PRICE,
            250 => ProductReference::BACKUP_ACRONIS_250_REGISTRATION_PRICE,
            500 => ProductReference::BACKUP_ACRONIS_500_REGISTRATION_PRICE,
            1000 => ProductReference::BACKUP_ACRONIS_1000_REGISTRATION_PRICE,
            2000 => ProductReference::BACKUP_ACRONIS_2000_REGISTRATION_PRICE,
            default => throw new InvalidArgumentException(sprintf('Unsupported Acronis product price: %d', $size)),
        };
    }

    private function acronisProviders(): void
    {
        $yourhostingProvider = new AcronisProvider();
        $yourhostingProvider->uuid = Str::uuid();
        $yourhostingProvider->name = 'yourhosting';
        $yourhostingProvider->endpoint = 'http://mock:3000/acronis/';
        $yourhostingProvider->tenant_uuid = Uuid::fromString('0f2d2f3a-7c2b-4f6a-9d66-0b8e3c2d1a11');
        $yourhostingProvider->client_id = Uuid::fromString('7c3d2a1e-9f55-4c2e-8a65-0d3a8f2b1c44');
        $yourhostingProvider->client_secret = 'yhmockclientsecret20260113';
        $yourhostingProvider->default = true;
        $yourhostingProvider->sso_target_url = 'https://eu2-cloud.acronis.com';
        $yourhostingProvider->save();

        $this->referenceRepo->set(ProductReference::ACRONIS_PROVIDER_YOURHOSTING, $yourhostingProvider);

        $versioProvider = new AcronisProvider();
        $versioProvider->uuid = Str::uuid();
        $versioProvider->name = 'versio';
        $versioProvider->endpoint = 'http://mock:3000/acronis/';
        $versioProvider->tenant_uuid = Uuid::fromString('b4d13f55-9f41-4d5f-9f93-2c4a3fd9f0e2');
        $versioProvider->client_id = Uuid::fromString('2a6c2c9d-3b73-4e8a-9d0a-1f0e2c3b4a55');
        $versioProvider->client_secret = 'versiomockclientsecret20260113';
        $versioProvider->sso_target_url = 'https://eu2-cloud.acronis.com';
        $versioProvider->save();
    }

    private function upgrades(): void
    {
        $upgradeDestinations = [
            $this->referenceRepo->get(ProductReference::BACKUP_ACRONIS_50, Product::class),
            $this->referenceRepo->get(ProductReference::BACKUP_ACRONIS_100, Product::class),
            $this->referenceRepo->get(ProductReference::BACKUP_ACRONIS_250, Product::class),
            $this->referenceRepo->get(ProductReference::BACKUP_ACRONIS_500, Product::class),
            $this->referenceRepo->get(ProductReference::BACKUP_ACRONIS_1000, Product::class),
            $this->referenceRepo->get(ProductReference::BACKUP_ACRONIS_2000, Product::class),
        ];

        $totalProducts = count($upgradeDestinations);

        // Select the product to upgrade FROM
        for ($i = 0; $i < $totalProducts; $i++) {
            $fromProduct = $upgradeDestinations[$i];

            // Select the products to upgrade TO
            // Starting at $i + 1 guarantees we only process higher-tier products
            for ($j = $i + 1; $j < $totalProducts; $j++) {
                $toProduct = $upgradeDestinations[$j];

                $upgrade = new ProductAllowedChange();
                $upgrade->from_product_id = $fromProduct->id;
                $upgrade->to_product_id = $toProduct->id;
                $upgrade->change_type = ProductChangeType::UPGRADE;
                $upgrade->is_available_for_customer = true;
                $upgrade->display_order = $j;
                $upgrade->save();
            }
        }
    }
}
