<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Sanity;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Ssl\Models\Sanity as SslSanity;
use Waterfront\Domain\Ssl\Services\CertificateManager;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Webmozart\Assert\Assert;

#[AsCommand(name: 'sanity:ssl')]
#[Description('Check SSL deployments for missing settings, storage files')]
#[Signature('sanity:ssl {domain?}')]
class CheckSsl extends Command
{
    /**
     * @throws FileNotFoundException
     */
    public function handle(
        CertificateManager $certificateManager,
        CsrManager $csrManager
    ): int {
        $domainOption = $this->argument('domain');

        $subscriptions = Subscription::whereProductGroupType(ProductGroupType::SSL)
            ->whereIn('administrative_status', [AdministrativeStatus::ACTIVE->value, AdministrativeStatus::CANCELED->value])
            ->whereNotIn('technical_status', [TechnicalStatus::DELETED->value])
            ->when($domainOption, function ($query) use ($domainOption): void {
                $query->where('domain', $domainOption);
            })
            ->get();

        $subscriptionCount = $subscriptions->count();

        foreach ($subscriptions as $key => $subscription) {
            $subscriptionDomain = $subscription->domain;
            Assert::notNull($subscriptionDomain, 'Provided subscription has no domain');

            $domain = ($subscription->product->slug === 'ssl_wildcard')
                ? "*.{$subscriptionDomain}"
                : $subscriptionDomain;

            $current = $key + 1;

            $this->info("$current / $subscriptionCount: $domain");

            $sanityModel = SslSanity::where('subscription_uuid', $subscription->uuid)->first();

            if ($sanityModel === null) {
                $sanityModel = new SslSanity();
                $sanityModel->subscription_uuid = $subscription->uuid;
            }

            $sanityModel->product_name = $subscription->product->name;
            $sanityModel->domain = $subscriptionDomain;

            // Check SSL deployment existence
            $sanityModel->has_ssl_subscription = (bool) $subscription->sslDeployment;

            // Check certificate file existence
            $sanityModel->has_ssl_certificate_files = (bool) $certificateManager->getMainCertificate($domain);

            // Check private key existence
            try {
                $sanityModel->has_ssl_private_files = (bool) $csrManager->getPrivateKey($domain);
            } catch (FileNotFoundException) {
                $sanityModel->has_ssl_private_files = false;
            }

            $sanityModel->save();
        }

        return self::SUCCESS;
    }
}
