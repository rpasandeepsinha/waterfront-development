<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Actions;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Customers\Services\CustomerWallet\CsvExporter;
use Waterfront\Domain\Customers\Models\CustomerWallet;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaCustomerWalletDownloadRefundCsvAction extends Action
{
    public $onlyOnIndex = true;

    public static $chunkCount = 500;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ConfigurationInterface $configuration,
        private readonly FilesystemManager $filesystemManager,
        private readonly CsvExporter $csvExporter,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.customer.wallet.download_refund_csv');
    }

    /**
     * @param Collection<int, CustomerWallet> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        if ($models->count() < 1) {
            return self::danger($this->translator->translate('nova-action.error.nothing_selected'));
        }

        if (! $fields->confirm_check) {
            return self::danger($this->translator->translate('nova-action.error.did-not-confirm'));
        }

        if (! $this->allSelectedWalletsAreRefundable($models)) {
            return self::danger($this->translator->translate(
                'nova-action.customer.wallet.selection-contains-wallets-without-refund-request',
            ));
        }

        if (! $this->allSelectedWalletsAreNotAlreadyExported($models)) {
            return self::danger($this->translator->translate(
                'nova-action.customer.wallet.selection-contains-wallets-that-already-have-been-downloaded',
            ));
        }

        $now = CarbonImmutable::now();
        $csvFileName = $this->storeCsv(
            $this->csvExporter->export(
                [
                    'customer_number',
                    'bank_account_name',
                    'bank_account_number',
                    'amount',
                ],
                array_map(fn (CustomerWallet $wallet): array => [
                    $wallet->customer->customer_number,
                    $wallet->bank_account_name,
                    $wallet->bank_account_number,
                    $wallet->amount,
                ], $models->all()),
            ),
            $now,
        );
        $this->updateAllDownloadedAtForWallets($models, $now);

        return ActionResponse::download(
            $csvFileName,
            '/export/' . $csvFileName,
        );
    }

    /**
     * @return array<int, NovaBoolField|Heading>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            NovaBoolField::make('Confirm', 'confirm_check')->help($this->translator->translate(
                'nova-action.customer.wallet.download_refund_csv.confirmtext',
            )),
            Heading::make(
                '<p class="text-red-600">LET OP! De export is gelimiteerd tot '
                . self::$chunkCount
                . ' items!</p>
<p>De export zal geen fout geven maar incompleet zijn indien er meer wallets geselecteerd worden.</p>',
            )->asHtml(),
        ];
    }

    /**
     * @param Collection<int, CustomerWallet> $models
     */
    private function allSelectedWalletsAreNotAlreadyExported(Collection $models): bool
    {
        return (
            $models->contains(
                static fn (CustomerWallet $wallet) => $wallet->csv_downloaded_at !== null,
            ) === false
        );
    }

    /**
     * @param Collection<int, CustomerWallet> $models
     */
    private function allSelectedWalletsAreRefundable(Collection $models): bool
    {
        return (
            $models->contains(
                static fn (CustomerWallet $wallet) => (
                    $wallet->refund_requested_at === null
                    || $wallet->bank_account_number === null
                    || $wallet->bank_account_name === null
                ),
            ) === false
        );
    }

    private function storeCsv(string $csvContent, DateTimeImmutable $now): string
    {
        Assert::stringNotEmpty($csvContent);

        $csvFileName = sprintf('customer-wallet-refunds-%s.csv', $now->format(DateTimeFormat::FILENAME));

        // First store it in the private exports dir so that we can hand it over for downloading.
        $this->filesystemManager->disk('private')->put('exports/' . $csvFileName, $csvContent);

        // Second store it on the cloud disk for historical reasons.
        $this->filesystemManager->disk($this->configuration->getAsString('filesystems.cloud'))->put(
            $csvFileName,
            $csvContent,
        );

        return $csvFileName;
    }

    /**
     * @param Collection<int, CustomerWallet> $models
     */
    private function updateAllDownloadedAtForWallets(Collection $models, DateTimeImmutable $now): void
    {
        CustomerWallet::query()
            ->whereIn('id', $models->pluck('id')->all())
            ->update([
                'csv_downloaded_at' => $now,
            ]);
    }
}
