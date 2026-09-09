<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Legacy;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\UploadedFile;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptAbstractAction;

class NovaBulkUpdateDomainHandlePrivacyProtectAction extends NovaOneOffScriptAbstractAction
{
    public const string SLUG = 'bulk-update-contact-data-privacy-protect';

    private const string CSV_COLUMN_DOMAIN_NAME = 'Domain Name';

    public function __construct(
        private readonly Dispatcher $jobDispatcher,
    ) {
        parent::__construct();
    }

    public function handle(ActionFields $fields): ActionResponse
    {
        $dryRun = $fields->boolean('dry-run');
        $limit = $fields->integer('limit');

        /** @var UploadedFile $file */
        $file = $fields->get('csv_upload');

        $rows = $this->parseCsvWithHeaders($file->getContent());

        if ($limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        foreach ($rows as $row) {
            $domainName = trim($row[self::CSV_COLUMN_DOMAIN_NAME] ?? '');

            if ($domainName === '') {
                continue;
            }

            $this->jobDispatcher->dispatch(new NovaUpdateDomainHandlePrivacyProtectJob(
                domainName: $domainName,
                dryRun: $dryRun,
            ));
        }

        return ActionResponse::message(
            sprintf(
                '[%s] Dispatched %d domain handle update jobs.',
                $dryRun ? 'DRY RUN' : 'EXECUTED',
                count($rows),
            )
        );
    }

    /**
     * @return array<Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ...$this->getOneOffScriptInfoFields(),
            Boolean::make('Dry run', 'dry-run')
                ->withMeta(['value' => true]),
            Number::make('Limit (0 = all)', 'limit')->default(0)->min(0),
            File::make('CSV upload', 'csv_upload')
                ->disableDownload()
                ->acceptedTypes('.csv')
                ->required()
                ->rules('required')
                ->store(fn (): bool => false),
        ];
    }

    protected function getOneOffScriptSlug(): string
    {
        return self::SLUG;
    }

    protected function getOneOffScriptTicketUrl(): string
    {
        return 'https://yh-jira.atlassian.net/browse/SWD-16449';
    }

    /**
     * Parses a comma-separated CSV file with a header row.
     *
     * @return array<array<string, string|null>>
     */
    private function parseCsvWithHeaders(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        $lines = explode("\n", trim($content));

        /** @var string[] $headers */
        $headers = str_getcsv(array_shift($lines), escape: '\\');

        $rows = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $values = array_map(
                fn (string|null $value): ?string => ($value === null || $value === '') ? null : $value,
                str_getcsv($line, escape: '\\')
            );

            $rows[] = array_combine($headers, $values);
        }

        return $rows;
    }
}
