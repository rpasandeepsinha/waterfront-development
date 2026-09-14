<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Ssl\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Throwable;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Pipes\SslMigrationPipe;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaSslRenewalHealthCheckAction extends Action
{
    public function __construct(
        private readonly SslMigrationPipe $sslMigrationPipe,
        private readonly TranslatorInterface $translator,
        private readonly HostingDeploymentRepository $hostingDeploymentRepository,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.ssl_renewal_health_check');
    }

    /**
     * @param Collection<int, SslDeployment> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        /** @var SslDeployment $sslDeployment */
        $sslDeployment = $models->firstOrFail();

        try {
            $subscription = $sslDeployment->subscription;

            $hostingPayload = $this->getHostingPayload($subscription);

            $validationPayload = new ValidationPayload(
                validationReference: 'ssl_renewal_health_check_' . CarbonImmutable::now()->format('Y_m_d_H_i_s'),
                customer: [],
                subscriptions: [
                    'ssl' => [
                        [
                            'domain' => $subscription->domain ?? '',
                        ],
                    ],
                    'hosting' => $hostingPayload !== null ? [$hostingPayload] : [],
                ],
            );

            $result = $this->sslMigrationPipe->handle(
                $validationPayload,
                fn (): ValidationPayload => $validationPayload,
            );

            return self::modal('modal-response', [
                'title' => $this->translator->translate('nova-action.ssl_renewal_health_check'),
                'code' => json_encode($result->toArray(), JSON_PRETTY_PRINT),
            ]);

            /** @phpstan-ignore-next-line  */
        } catch (Throwable $exception) {
            return self::modal('modal-response', [
                'title' => $this->translator->translate('nova-action.ssl_renewal_health_check'),
                'code' => json_encode([
                    'message' => $exception->getMessage(),
                    'status' => $exception->getCode(),
                    'trace' => $exception->getTraceAsString(),
                ], JSON_PRETTY_PRINT),
            ]);
        }
    }

    /**
     * @return array<mixed>|null
     */
    private function getHostingPayload(Subscription $subscription): ?array
    {
        if ($subscription->domain === null || $subscription->domain === '') {
            return null;
        }

        $domain = $subscription->domain;

        /** @var Subscription|null $hostingSubscription */
        $hostingSubscription = Subscription::query()
            ->where([
                'customer_id' => $subscription->customer->id,
                'domain' => $domain,
            ])
            ->whereProductGroupType(ProductGroupType::HOSTING)
            ->first();

        if (! $hostingSubscription instanceof Subscription) {
            return null;
        }

        $hostingDeployment = $hostingSubscription->hostingDeployment;

        if ($hostingDeployment === null) {
            return null;
        }

        $server = $this->hostingDeploymentRepository->getServer($hostingDeployment);
        $provider = $hostingDeployment->provider;

        if ($server === null || $provider === null) {
            return null;
        }

        $serverData = $this->getServerData($hostingDeployment, $server);

        if ($serverData === []) {
            return null;
        }

        return [
            'start_date' => $hostingSubscription->start_date->toDateString(),
            'next_contract_date' => $hostingSubscription->next_billing_date->toDateString(),
            'next_billing_date' => $hostingSubscription->next_billing_date->toDateString(),
            'contract_period' => $hostingSubscription->contract_period,
            'billing_period' => $hostingSubscription->billing_period,
            'slug' => $hostingSubscription->product->slug,
            'reference_product_id' => 'fake',
            'reference_subscription_id' => 'fake',
            'hostname' => $server->hostname,
            'driver' => $provider->slug->value,
            'server_data' => $serverData,
            'domain' => $hostingSubscription->domain,
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getServerData(HostingDeployment $hostingDeployment, Server $server): array
    {
        return match ($server->type) {
            ServerType::DIRECTADMIN => [
                'directadmin_customer_name' => $hostingDeployment->directadmin_customer_username,
            ],
            ServerType::PLESK => [
                'plesk_customer_id' => $hostingDeployment->plesk_customer_id,
                'plesk_customer_username' => $hostingDeployment->plesk_customer_username,
            ],
            default => [],
        };
    }
}
