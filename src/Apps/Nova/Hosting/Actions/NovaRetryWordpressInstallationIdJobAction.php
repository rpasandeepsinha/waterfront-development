<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Actions;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaSubscriptionAction;
use Waterfront\Domain\Hosting\Jobs\ReceiveWpInstallationIdJob;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class NovaRetryWordpressInstallationIdJobAction extends NovaSubscriptionAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly Dispatcher $dispatcher,
        private readonly LoggerInterface $logger,
        private readonly HostingDeploymentRepository $hostingDeploymentRepository,
    ) {
        $this->canSee(
            fn (NovaRequest $request): bool =>
                $this->onlyForSingleSubscription($request)
                && $this->hasWpToolkit($request)
        );

        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.retry-retrieve-wp-installation-id.title');
    }

    /**
     * @param Collection<int, HostingDeployment> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $hostingDeployment = $models->first();

        assert($hostingDeployment instanceof HostingDeployment);

        $subscription = $hostingDeployment->subscription;
        $server = $this->hostingDeploymentRepository->getServer($hostingDeployment);
        assert($server instanceof Server);

        $this->dispatcher->dispatch(
            new ReceiveWpInstallationIdJob(
                $subscription->uuid,
                $server
            )
        );

        $this->logger->debug(
            'Starting ReceiveWpInstallationId job from nova action for domain {domain.name} on server {server.id}.',
            [
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                LoggingContextKeys::SERVER_ID => $server->id,
            ]
        );

        return self::message($this->translator->translate('nova-action.retry-wordpress-installation-id-job-dispatched'));
    }

    private function hasWpToolkit(NovaRequest $request): bool
    {
        $hostingDeployment = $request->selectedResources();

        if ($hostingDeployment === null || count($hostingDeployment) === 0) {
            return false;
        }

        $hostingDeployment = $hostingDeployment->firstOrFail();

        assert($hostingDeployment instanceof HostingDeployment);

        $subscription = $hostingDeployment->subscription;

        return $this->productSpecRepository->booleanSpecificationIsTrue($subscription->product, ProductSpecName::WAIT_FOR_WP_TOOLKIT);
    }
}
