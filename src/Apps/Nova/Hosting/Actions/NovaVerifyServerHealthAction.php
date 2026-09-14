<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use JsonException;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Hosting\Jobs\VerifyServerHealth;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaVerifyServerHealthAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Dispatcher $dispatcher,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.health_check_server');
    }

    /**
     * @param Collection<int, Server> $models
     *
     * @throws JsonException
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $type = $fields->get('server_type');
        assert(is_string($type));
        $serverType = ServerType::from($type);

        $this->dispatcher->dispatch(new VerifyServerHealth($serverType));

        return self::modal('modal-response', [
            'title' => sprintf(
                'Health checking all standard hosting servers with package fetching for Server Type: %s in a queued job. Check the log on debug level for the results',
                $serverType->value,
            ),
        ]);
    }

    /**
     * @return array<int, Select>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Select::make('Server type', 'server_type')
                ->options([
                    ServerType::DIRECTADMIN->value => 'DirectAdmin',
                    ServerType::PLESK->value => 'Plesk',
                ])
                ->default(ServerType::DIRECTADMIN->value),
        ];
    }
}
