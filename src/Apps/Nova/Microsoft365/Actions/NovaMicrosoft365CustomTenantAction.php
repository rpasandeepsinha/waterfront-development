<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Microsoft365\Actions;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use SandwaveIo\Office365\Exception\Office365Exception;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Microsoft365\Enums\CustomerInfoType;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaMicrosoft365CustomTenantAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.custom-tenant');
    }

    /**
     * @param Collection<int, Customer> $customers
     */
    public function handle(ActionFields $fields, Collection $customers): ActionResponse|static
    {
        /** @var Microsoft365Service $microsoft365Service */
        $microsoft365Service = Container::getInstance()->make(Microsoft365Service::class);

        if ($customers->count() !== 1) {
            return self::danger($this->translator->translate('nova-action.failed.microsoft365-custom-tenant-no-multiple-customers'));
        }

        if (! str_contains($fields->tenant_name, '.onmicrosoft.com')) {
            return self::danger($this->translator->translate('nova-action.failed.microsoft365-custom-tenant-no-onmicrosoft'));
        }

        $customer = $customers[0];
        Assert::isInstanceOf($customer, Customer::class);

        if ($customer->microsoft365CustomerInfo->count() > 0) {
            return self::danger($this->translator->translate('nova-action.failed.microsoft365-custom-already-exists'));
        }

        /** @var Microsoft365CustomerInfo $microsoft365CustomerInfo */
        $microsoft365CustomerInfo = Microsoft365CustomerInfo::create([
            'customer_id' => $customer->id,
            'tenant_name' => $fields->tenant_name,
            'type' => CustomerInfoType::REGISTER,
        ]);

        try {
            $successful = $microsoft365Service->createKpnCustomer($customer, (string) $microsoft365CustomerInfo->id);
        } catch (Office365Exception $e) {
            Log::error(sprintf(
                'Error while creating KPN customer for customer_id: [%s]. With exception message: %s',
                $customer->id,
                $e->getMessage(),
            ));

            return self::danger($this->translator->translate('nova-action.failed.microsoft365-customer-failed'));
        }

        if (! $successful) {
            $microsoft365CustomerInfo->update(['technical_status' => Microsoft365ProcessStatus::FAILED]);
            return self::danger($this->translator->translate('nova-action.failed.microsoft365-customer-failed'));
        }

        return Action::message($this->translator->translate('nova-action.success.microsoft365-custom-tenant-created'));
    }

    /**
     * @return array<int, Text>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make($this->translator->translate('microsoft365-customer.tenant-name'), 'tenant_name')->required(),
        ];
    }
}
