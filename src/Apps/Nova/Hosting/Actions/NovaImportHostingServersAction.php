<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Actions;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Support\MessageBag;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Http\Requests\NovaRequest;
use ValueError;
use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Domain\Ferry\Mappers\CsvParser;
use Waterfront\Infra\Translation\TranslatorInterface;

abstract class NovaImportHostingServersAction extends Action
{
    /**
     * @var array<string, array<int, DomainNameRule|string>>
     */
    protected array $validationRules;

    public function __construct(
        protected readonly CsvParser $csvParser,
        protected readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<int,File>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            File::make($this->translator->translate('nova-action.import_hosting_servers_file'), 'csv_upload')
                ->disableDownload()
                ->acceptedTypes('.csv')
                ->required()
                ->help($this->translator->translate(
                    'nova-action.import_hosting_server_help',
                    [
                        'url' => 'https://yh-jira.atlassian.net/wiki/spaces/DEV/pages/1411940384/Hosting+server+bulk+import',
                    ]
                ))
                ->store(fn (): bool => false),
        ];
    }

    public function handle(ActionFields $fields): ActionResponse|static
    {
        /** @var UploadedFile $file */
        $file = $fields->get('csv_upload');

        /** @var array<int, array<string, string>> $servers */
        $servers = $this->csvParser->parseCsvWithHeaders($file->getContent());

        foreach ($servers as $server) {
            $this->createServer($server);
        }

        return Action::message(
            $this->translator->translate(
                'nova-action.success.import_hosting_servers_successfully',
                [
                    'imported_count' => (string) count($servers),
                ]
            )
        );
    }

    /**
     * Validate the CSV file.
     *
     * Gets called before handle().
     * Skips handle() if there are any errors.
     */
    protected function afterValidation(NovaRequest $request, Validator $validator): void
    {
        /** @var UploadedFile|null $file */
        $file = $request->file('csv_upload');

        if (! $file instanceof UploadedFile) {
            // Will still show a validation error due to the rules() method.
            return;
        }

        $fileExtension = $file->getClientOriginalExtension();

        if ($fileExtension !== 'csv') {
            // "acceptedTypes" is only frontend validation, so we need to validate this here too
            $validator->errors()->add(
                'csv_upload',
                $this->translator->translate('nova-action.error.import_hosting_server_not_csv')
            );
        }

        try {
            $serverData = $this->csvParser->parseCsvWithHeaders(
                $file->getContent()
            );
        } catch (ValueError) {
            $validator->errors()
                ->add('csv_upload', $this->translator->translate('nova-action.error.number_of_columns_does_not_match'));
            return;
        }

        $csvValidator = ValidatorFacade::make(
            $serverData,
            $this->validationRules
        );

        if ($csvValidator->fails()) {
            $validator->errors()
                ->add('csv_upload', $this->buildValidationErrorString($csvValidator->messages()));
        }
    }

    /**
     * @param array<string, string|null> $serverData
     */
    abstract protected function createServer(array $serverData): void;

    /**
     * Build error string, so we can display it on the csv_upload field.
     */
    private function buildValidationErrorString(MessageBag $messageBag): string
    {
        $messageString = '';
        $maxToDisplay = 10;
        $validationErrors = $messageBag->toArray();

        // Sort by row number, instead of by field name.
        uksort($validationErrors, function ($key1, $key2) {
            [$rowNumber1] = explode('.', $key1);
            [$rowNumber2] = explode('.', $key2);

            return (int) $rowNumber1 < (int) $rowNumber2 ? -1 : 1;
        });

        $fieldCount = 0;

        foreach ($validationErrors as $fieldName => $messages) {
            foreach ($messages as $message) {
                if ($fieldCount === $maxToDisplay) {
                    // We don't want to display hundreds of validation messages when there are hundreds of rows.
                    break 2;
                }

                $messageString .= "$fieldName: $message<br/>";

                $fieldCount++;
            }
        }

        if (count($validationErrors) > $maxToDisplay) {
            // Display message that there are more validation errors.
            $messageString .= '<br/>' . $this->translator->translate(
                'nova-action.error.import_hosting_servers_more_errors',
                [
                    'error_count' => (string) count($validationErrors),
                    'max_to_display' => (string) $maxToDisplay,
                ]
            );
        }

        return $messageString;
    }
}
