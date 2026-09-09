<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Actions;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use JsonException;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Servers\Exceptions\ServerNotFoundException;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Exceptions\NotImplementedException;

class NovaFetchPackageFromServer extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly HostingService $hostingService
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.fetch_package_from_server');
    }

    /**
     * @param Collection<int, Server> $models
     *
     * @throws JsonException
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $package = $fields->get('package');
        assert(is_string($package));

        $server = $models->firstOrFail();

        $fetchedPackage = [];
        $message        = null;
        $trace          = null;
        $code           = null;

        try {
            $fetchedPackage = $this->hostingService->getPackageOnServer($server, $package);
        } catch (ServerNotFoundException|GuzzleException|NotImplementedException $exception) {
            $message = $exception->getMessage();
            $trace   = $exception->getTraceAsString();
            $code    = $exception->getCode();
        }

        $data = [
            'package'  => $fetchedPackage,
            'exception' => $message,
            'code'      => $code,
            'trace'     => $trace,
        ];

        $title = sprintf(
            'Fetched package {%s} from server with hostname {%s} with response:',
            $package,
            $server->hostname
        );

        return self::modal('modal-response', [
            'title' => $title,
            'code' => json_encode($data, JSON_PRETTY_PRINT),
        ]);
    }

    /**
     * @return array<int, Text>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('package', 'package')
                ->required(),
        ];
    }
}
