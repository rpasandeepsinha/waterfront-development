<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Microsoft365\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use SandwaveIo\Office365\Exception\Office365Exception;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaMicrosoft365RetryCreateKpnCustomerAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.retry-create-failed-kpn-customers');
    }

    /**
     * @param Collection<int, Microsoft365CustomerInfo> $microsoft365CustomerInfos
     */
    public function handle(ActionFields $fields, Collection $microsoft365CustomerInfos): ActionResponse|static
    {
        /** @var Microsoft365Service $microsoft365Service */
        $microsoft365Service = resolve(Microsoft365Service::class);

        /** @var Microsoft365CustomerInfo $microsoft365CustomerInfo */
        foreach ($microsoft365CustomerInfos as $microsoft365CustomerInfo) {
            if ($microsoft365CustomerInfo->kpn_customer_id === null) {
                try {
                    $successful = $microsoft365Service->createKpnCustomer(
                        customer: $microsoft365CustomerInfo->customer,
                        customerInfoId: (string) $microsoft365CustomerInfo->id,
                    );
                } catch (Office365Exception $e) {
                    Log::error(sprintf(
                        'Error while creating KPN customer for customer_id: [%s]. With exception message: %s',
                        $microsoft365CustomerInfo->customer->id,
                        $e->getMessage(),
                    ));

                    return self::danger($this->translator->translate(
                        'nova-action.failed.microsoft365-customer-failed',
                    ));
                }

                if (! $successful) {
                    $microsoft365CustomerInfo->update(['technical_status' => Microsoft365ProcessStatus::FAILED]);

                    return self::danger($this->translator->translate(
                        'nova-action.failed.microsoft365-customer-failed',
                    ));
                }
            }
        }

        return Action::message($this->translator->translate('nova-action.success.microsoft365-customer-created'));
    }
}
