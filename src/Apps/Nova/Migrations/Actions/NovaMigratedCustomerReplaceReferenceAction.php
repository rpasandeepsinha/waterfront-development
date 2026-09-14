<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Migrations\Actions;

use Illuminate\Foundation\Bus\PendingClosureDispatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Mappers\CsvParser;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\QueueName;

class NovaMigratedCustomerReplaceReferenceAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly CsvParser $csvParser,
    ) {
    }

    /**
     * Get the displayable name of the action.
     */
    public function name(): string
    {
        return $this->translator->translate('nova-action.update_mc_reference');
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        /** @var UploadedFile $file */
        $file = $fields->get('csv_upload');

        /** @var array<int, array<string, string>> $references */
        $references = $this->csvParser->parseCsvWithHeaders($file->getContent());

        /** @var string $slug */
        $slug = $fields->get('business_unit');

        foreach ($references as $referenceItem) {
            $task = function () use ($referenceItem, $slug) {
                $mc = MigratedCustomer::query()
                    ->where([
                        'reference_name' => $slug,
                        'reference_customer_number' => $referenceItem['original_reference'],
                    ])
                    ->first();

                if ($mc instanceof MigratedCustomer) {
                    $mc->reference_customer_number = $referenceItem['new_reference'];
                    $mc->save();
                }
            };

            new PendingClosureDispatch(CallQueuedClosure::create($task))
                /** @phpstan-ignore-next-line */
                ->name('update_reference_bulk')
                ->onQueue(QueueName::DEFAULT);
        }

        return ActionResponse::message('NovaMigratedCustomerReplaceReferenceAction executed');
    }

    /**
     * @return array<int, File|Select>
     */
    public function fields(NovaRequest $request): array
    {
        /** @var array{string: string} $units */
        $units = MigratedCustomer::query()
            ->select('reference_name')
            ->groupBy('reference_name')
            ->get()
            ->flatMap(fn (MigratedCustomer $mc) => [$mc->reference_name => $mc->reference_name])
            ->toArray();

        return [
            File::make($this->translator->translate('nova-action.import_reference_file'), 'csv_upload')
                ->disableDownload()
                ->acceptedTypes('.csv')
                ->required()
                ->store(fn (): bool => false),

            Select::make('business_unit')->options($units)->default(Arr::first($units))->required(),
        ];
    }
}
