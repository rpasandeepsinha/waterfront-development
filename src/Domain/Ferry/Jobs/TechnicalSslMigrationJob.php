<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use Throwable;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Ssl\Jobs\UpdateSslExpireDate;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class TechnicalSslMigrationJob extends MigrationJob
{
    private Dispatcher $dispatcher;

    public function __construct(
        public Subscription $subscription,
        protected ?string $failedTechnicalStatus,
    ) {
        parent::__construct($this->subscription, $this->failedTechnicalStatus);
    }

    public function getMigrationStep(): MigrationStep
    {
        return MigrationStep::SSL_MIGRATION;
    }

    protected function getSuccessfulTechnicalStatus(): string
    {
        return TechnicalStatus::OK->value;
    }

    protected function registerServices(): void
    {
        $this->dispatcher = $this->resolve(Dispatcher::class);
    }

    protected function runMigration(): void
    {
        $rtrProvider = Provider::where('type', ProviderType::SSL)
            ->where('slug', ProviderSlug::REALTIME_REGISTER)
            ->firstOrFail();

        /** @var SslDeployment $deployment */
        $deployment = $this->subscription->sslDeployment()->firstOrFail();

        $deployment->provider_id = $rtrProvider->id;
        $deployment->save();

        $this->dispatcher->dispatch(new UpdateSslExpireDate($deployment, true));
    }

    protected function rollback(Throwable $throwable): void
    {
        $placeholderProvider = Provider::where('type', ProviderType::SSL)
            ->where('slug', ProviderSlug::PLACEHOLDER)
            ->firstOrFail();

        /** @var SslDeployment $deployment */
        $deployment = $this->subscription->sslDeployment()->firstOrFail();

        $deployment->provider_id = $placeholderProvider->id;
        $deployment->expire_date = null;
        $deployment->save();
    }
}
