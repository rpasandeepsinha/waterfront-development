<?php

declare(strict_types=1);

namespace Database\Seeders\Scenarios\TestKees;

use Database\Seeders\Support\LocalSslBundle;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;

class SslCertificateSeeder extends Seeder
{
    public function run(): void
    {
        /** @var Collection<int, string> $domains */
        $domains = SslDeployment::query()
            ->join('subscriptions as s', 's.uuid', '=', 'ssl_deployments.subscription_uuid')
            ->whereNotIn('s.administrative_status', AdministrativeStatus::administrativelyEnded())
            ->whereNotNull('s.domain')
            ->distinct()
            ->pluck('s.domain');

        $disk = $this->container->make(Factory::class)->disk('s3');

        foreach ($domains as $domain) {
            $dir = $domain;

            $files = [
                sprintf('%s/%s.csr', $dir, $domain) => LocalSslBundle::csr(),
                sprintf('%s/%s.key', $dir, $domain) => LocalSslBundle::privateKey(),
                sprintf('%s/intermediate.crt', $dir) => LocalSslBundle::intermediate(),
                sprintf('%s/root.crt', $dir) => LocalSslBundle::root(),
                sprintf('%s/main.crt', $dir) => LocalSslBundle::certificate(),
            ];

            foreach ($files as $path => $contents) {
                if ($disk->exists($path)) {
                    continue;
                }

                $disk->put($path, $contents);
            }
        }
    }
}
