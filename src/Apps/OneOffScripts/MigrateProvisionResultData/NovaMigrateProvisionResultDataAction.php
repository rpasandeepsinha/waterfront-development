<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\MigrateProvisionResultData;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use stdClass;
use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptAbstractAction;
use Waterfront\Support\Enums\LoggingContextKeys;

class NovaMigrateProvisionResultDataAction extends NovaOneOffScriptAbstractAction
{
    public const string SLUG = 'migrate-provision-result-data';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Dispatcher $busDispatcher,
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

        DB::table('provisioning_results')
            ->select(['id', 'response', 'status'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($isDryRun): void {
                foreach ($rows as $row) {
                    /** @var stdClass&object{id: int, response: string, status: string} $row */
                    $this->busDispatcher->dispatch(new ProvisionResultUpdateJob($row, $isDryRun));
                }
            });

        return self::message('One-off script started successfully.');
    }

    protected function getOneOffScriptSlug(): string
    {
        return self::SLUG;
    }

    protected function getOneOffScriptTicketUrl(): string
    {
        return 'https://yh-jira.atlassian.net/browse/SWD-16603';
    }
}
