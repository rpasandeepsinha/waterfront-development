<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Actions;

use Illuminate\Support\Collection;
use JsonException;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Throwable;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\Services\BasekitFactoryInterface;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaFetchUserFromSitebuilderServer extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly BasekitFactoryInterface $basekitFactory,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.fetch_user_from_sitebuilder_server');
    }

    /**
     * @param Collection<int, Server> $models
     *
     * @throws JsonException
     */
    public function handle(ActionFields $fields, Collection $models): Action|ActionResponse|static
    {
        /** @var Server $server */
        $server = $models->firstOrFail();
        /** @var string|null $sitebuilderUserRef */
        $sitebuilderUserRef = $fields->get('sitebuilder_user_ref');
        /** @var string|null $sitebuilderSiteRef */
        $sitebuilderSiteRef = $fields->get('sitebuilder_site_ref');

        if ($sitebuilderUserRef === null || $sitebuilderSiteRef === null) {
            return self::danger($this->translator->translate('nova-action.error.no_identifier_provided'));
        }

        $fetchedSitebuilder = [];
        $exceptions  = [];

        try {
            $basekitClient = $this->basekitFactory->make($server);
            $fetchedSitebuilder['user'] = $basekitClient->userApi->get(intval($sitebuilderUserRef))->toArray();
            $fetchedSitebuilder['site'] = $basekitClient->sitesApi->get(intval($sitebuilderSiteRef))->toArray();
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $exceptions[] = [
                'message' => $exception->getMessage(),
                'previous' => $exception->getPrevious()?->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'code' => $exception->getCode(),
            ];
        }

        $data = [
            'sitebuilder' => $fetchedSitebuilder,
            'exceptions' => $exceptions,
        ];

        $title = sprintf(
            'Fetched sitebuilder user {%s} from server with hostname {%s} with response:',
            $sitebuilderUserRef,
            $server->hostname
        );

        return self::modal('modal-response', [
            'title' => $title,
            'code' => json_encode($data, JSON_PRETTY_PRINT),
            'size' => '7xl',
        ]);
    }

    /**
     * @return array<int, Text|Number>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Number::make('User reference', 'sitebuilder_user_ref'),
            Number::make('Site reference', 'sitebuilder_site_ref'),
        ];
    }
}
