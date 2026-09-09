<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bus\PendingClosureDispatch;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\DestructiveAction;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

class NovaResetDnsSecBulkAction extends DestructiveAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Get the displayable name of the action.
     */
    public function name(): string
    {
        return $this->translator->translate('nova-action.reset_dnssec_bulk.name');
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        /** @var string $domains */
        $domains = $fields->get('domains');
        /** @var string $slug */
        $slug = $fields->get('driver');
        $slug = ProviderSlug::from($slug);

        $domainList = explode(',', $domains);

        foreach ($domainList as $domain) {
            $task = function () use ($domain, $slug) {
                $domainService = Application::getInstance()->get(DomainService::class);

                $domainService->disableDnssec($domain, $slug);
                $domainService->enableDnssec($domain, $slug);
            };

            new PendingClosureDispatch(CallQueuedClosure::create($task))
                ->name('reset_dnssec_bulk')
                ->onQueue(QueueName::DEFAULT);
        }

        $this->logger->debug('NovaResetDnsSecBulkAction reset dnssec completed', [LoggingContextKeys::META => [
            'domains' => $domainList,
        ]]);

        return ActionResponse::message('reset dnssec executed');
    }

    /**
     * @return array<int, Text|Select>
     */
    public function fields(NovaRequest $request): array
    {
        /** @var array{slug: string} $slugs */
        $slugs = Provider::where('type', ProviderType::DOMAIN)
            ->whereNot('slug', ProviderSlug::PLACEHOLDER)
            ->get()
            ->flatMap(fn (Provider $provider) => [$provider->slug->value => $provider->slug->value])
            ->toArray();

        return [
            Text::make('domains_csv', 'domains')->rules('string')->required(),
            Select::make('driver')->options($slugs)->default(Arr::first($slugs))->required(),
        ];
    }
}
