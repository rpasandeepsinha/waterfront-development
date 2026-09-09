<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\RetroFixDeliverdSsl;

use Illuminate\Contracts\Bus\Dispatcher;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptAbstractAction;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class NovaRetroFixDeliverdSslAction extends NovaOneOffScriptAbstractAction
{
    public const string SLUG = 'retro-fix-deliverd-ssl';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Dispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    /** @return array<Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            ...$this->getOneOffScriptInfoFields(),
            Boolean::make('Dry run', 'dry-run')->default(true),
        ];
    }

    public function handle(ActionFields $fields): ActionResponse
    {
        $isDryRun = $fields->boolean('dry-run');

        $this->logger->debug(
            sprintf('Executing one-time script %s', $this->getOneOffScriptSlug()),
            [
                LoggingContextKeys::ONE_OFF_SCRIPT => $this->getOneOffScriptSlug(),
                LoggingContextKeys::META => ['dry-run' => $isDryRun],
            ]
        );

        $subscriptions = Subscription::whereProductGroupType(ProductGroupType::SSL)
            ->whereAdministrativeStatusActive()
            ->where('technical_status', '!=', TechnicalStatus::OK)
            ->with('sslDeployment.provider', 'hostingDeployment', 'domainDeployment')
            ->get();

        $subscriptions->each(function (Subscription $subscription) use ($isDryRun) {
            $this->dispatcher->dispatch(new RetroFixDeliveredSslJob(
                subscription: $subscription,
                isDryRun: $isDryRun,
            ));
        });

        if ($isDryRun) {
            return self::message('Dry run completed.');
        }

        $this->registerExecution();

        return self::message('One-off script executed successfully.');
    }

    protected function getOneOffScriptSlug(): string
    {
        return self::SLUG;
    }

    protected function getOneOffScriptTicketUrl(): string
    {
        return 'https://yh-jira.atlassian.net/browse/SWD-16598';
    }
}
