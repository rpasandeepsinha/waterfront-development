<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Actions;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Filesystem\FilesystemManager;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Customers\Services\CustomerWallet\CsvExporter;
use Waterfront\Domain\Customers\Models\CustomerWallet;
use Waterfront\Domain\Customers\Repositories\CustomerWalletRepository;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaCustomerWalletDownloadOverviewCsvAction extends Action
{
    public $onlyOnIndex = true;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly FilesystemManager $filesystemManager,
        private readonly CsvExporter $csvExporter,
        private readonly CustomerWalletRepository $customerWalletRepository,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.customer.wallet.download_overview_csv');
    }

    public function handle(ActionFields $fields): ActionResponse|static
    {
        $refDate = $fields->ref_date ? CarbonImmutable::createFromFormat(DateTimeFormat::DATE, $fields->ref_date) : CarbonImmutable::now();
        assert($refDate instanceof DateTimeInterface);
        $refDate = $refDate->startOfDay();

        $wallets = $this->customerWalletRepository->findAll();

        $csvContent = $this->csvExporter->export(
            [
                'customer_number',
                'bank_account_name',
                'bank_account_number',
                'amount',
                'refund_requested_at',
                'downloaded_at',
            ],
            array_map(static function (CustomerWallet $wallet) use ($refDate): array {
                $isRequestedAtBeforeRefDate = $wallet->refund_requested_at !== null
                    && $wallet->refund_requested_at <= $refDate;
                $isDownloadedAtBeforeRefDate = $wallet->csv_downloaded_at !== null
                    && $wallet->csv_downloaded_at <= $refDate;
                return [
                    $wallet->customer->customer_number,
                    $isRequestedAtBeforeRefDate ? $wallet->bank_account_name : '',
                    $isRequestedAtBeforeRefDate ? $wallet->bank_account_number : '',
                    $wallet->amount,
                    $isRequestedAtBeforeRefDate ? $wallet->refund_requested_at->format(DateTimeFormat::DATE) : '',
                    $isDownloadedAtBeforeRefDate ? $wallet->csv_downloaded_at?->format(DateTimeFormat::DATE) : '',
                ];
            }, $wallets->all()),
        );
        $csvFileName = $this->storeCsv($csvContent, $refDate);

        return ActionResponse::download(
            $csvFileName,
            '/export/' . $csvFileName,
        );
    }

    /**
     * @return array<int, Date>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Date::make($this->translator->translate('nova-action.customer.wallet.download_overview_csv.ref_date'), 'ref_date')
                ->default(fn (): DateTimeImmutable => CarbonImmutable::now())
                ->help($this->translator->translate('nova-action.customer.wallet.download_overview_csv.ref_date.help')),
        ];
    }

    private function storeCsv(string $csvContent, DateTimeImmutable $refDate): string
    {
        Assert::stringNotEmpty($csvContent);

        $csvFileName = sprintf('customer-wallet-overview-ref-date-%s.csv', $refDate->format(DateTimeFormat::FILENAME));

        // Store the csv in the private export directory so that we can hand it over for downloading.
        $this->filesystemManager
            ->disk('private')
            ->put('exports/' . $csvFileName, $csvContent);

        return $csvFileName;
    }
}
