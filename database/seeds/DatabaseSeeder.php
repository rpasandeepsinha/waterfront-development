<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\OneTimeScripts\MoveNameserverSeeder;
use Database\Seeders\Platform\EmailTemplateSeeder;
use Database\Seeders\Platform\ExperimentSeeder;
use Database\Seeders\Platform\OneOffScriptSeeder;
use Database\Seeders\Platform\ProductExperimentOfferingSeeder;
use Database\Seeders\Platform\ShopConfigSeeder;
use Database\Seeders\Platform\TranslationKeySeeder;
use Database\Seeders\Platform\TranslationLanguageSeeder;
use Database\Seeders\Platform\TranslationStringSeeder;
use Database\Seeders\Products\AcronisSeeder;
use Database\Seeders\Products\AddOnSeeder;
use Database\Seeders\Products\DnsSeeder;
use Database\Seeders\Products\DomainSeeder;
use Database\Seeders\Products\HostingSeeder;
use Database\Seeders\Products\Microsoft365Seeder;
use Database\Seeders\Products\OneTimeServiceSeeder;
use Database\Seeders\Products\OtherSeeder;
use Database\Seeders\Products\RedirectSeeder;
use Database\Seeders\Products\ResellerHostingSeeder;
use Database\Seeders\Products\SitebuilderSeeder;
use Database\Seeders\Products\SslSeeder;
use Database\Seeders\Products\VolumeDiscountSeeder;
use Database\Seeders\Products\VpsSeeder;
use Database\Seeders\Scenarios\DiscountSeeder;
use Database\Seeders\Scenarios\ManualMigrationSeeder;
use Database\Seeders\Scenarios\MigrationsSeeder;
use Database\Seeders\Scenarios\SIT\SitHostingSeeder;
use Database\Seeders\Scenarios\TestKees\SslCertificateSeeder;
use Database\Seeders\Scenarios\TestKees\TestKeesSeeder;
use Database\Seeders\Scenarios\TransferSeeder;
use Database\Seeders\Scenarios\VoucherSeeder;
use Database\Seeders\Support\ProductPriceReferenceSeeder;
use Database\Seeders\Support\ProductPriceSeeder;
use Database\Seeders\Support\PuzzelSeeder;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Waterfront\Apps\Console\Commands\Translations\UpdateTranslationsS3;
use Waterfront\Domain\Products\Jobs\UpdateProductListS3;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Enums\Environment;
use Webmozart\Assert\Assert;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function __construct(
        private readonly Dispatcher $jobDispatcher,
        private readonly ConfigurationInterface $config,
    ) {
    }

    public function run(): void
    {
        // Register the repository as singleton to keep object references between seeds
        $referenceRepository = new ReferenceRepository();
        $this->container->singleton(ReferenceRepository::class, fn () => $referenceRepository);

        // Platform
        $this->call([
            EmailTemplateSeeder::class,
            TranslationLanguageSeeder::class,
            TranslationKeySeeder::class,
            TranslationStringSeeder::class,
            OneOffScriptSeeder::class,
        ]);

        if ($this->config->getAsBoolean('app.seed_with_logic')) {
            Artisan::call(UpdateTranslationsS3::class);
        }

        if ($this->config->getAsString('app.tenant_env') === Environment::SIT->value) {
            $this->call([
                SitHostingSeeder::class,
            ]);
        } else {
            $this->call([
                HostingSeeder::class,
            ]);
        }

        // Products
        $this->call([
            DomainSeeder::class,
            ResellerHostingSeeder::class,
            SitebuilderSeeder::class,
            AddOnSeeder::class,
            Microsoft365Seeder::class,
            SslSeeder::class,
            DnsSeeder::class,
            VpsSeeder::class,
            OtherSeeder::class,
            VolumeDiscountSeeder::class,
            OneTimeServiceSeeder::class,
            RedirectSeeder::class,
            AcronisSeeder::class,
        ]);

        // Links an experiment to a product, so it runs after the product seeders
        $this->call([
            ExperimentSeeder::class,
        ]);

        // Load prices from CSV
        $scenario = $this->command->option('scenario');
        Assert::string($scenario);
        new ProductPriceSeeder()->load($scenario);

        // Set references
        new ProductPriceReferenceSeeder($referenceRepository)->run();

        $this->call(PuzzelSeeder::class);

        // Set initial customer number to 1000001
        DB::unprepared("SELECT setval('customer_number_increment', 1000001, false);");

        // Scenarios
        $this->call([
            TestKeesSeeder::class,
            TransferSeeder::class,
            DiscountSeeder::class,
            MigrationsSeeder::class,
            VoucherSeeder::class,
            ManualMigrationSeeder::class,
        ]);

        // Depends on both product and customer references, so it runs after the scenarios
        $this->call([
            ProductExperimentOfferingSeeder::class,
        ]);

        /**
         * One time script seeders.
         */
        $this->call(MoveNameserverSeeder::class);

        // Seeding with business logic
        if ($this->config->getAsBoolean('app.seed_with_logic')) {
            $this->jobDispatcher->dispatch(new UpdateProductListS3());

            $this->call([
                SslCertificateSeeder::class,
                ShopConfigSeeder::class,
            ]);
        }

        // Remove prices from PHP seeders
    }
}
