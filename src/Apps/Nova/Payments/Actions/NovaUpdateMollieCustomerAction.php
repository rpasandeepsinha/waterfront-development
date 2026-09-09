<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Payments\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Waterfront\Domain\Payments\Managers\MollieCustomerManager;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerMetadataDTO;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerRequestDTO;
use Waterfront\Infra\MollieClient\Exceptions\MollieCustomerApiException;
use Waterfront\Infra\MollieClient\Serializers\MollieSerializerFactory;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaUpdateMollieCustomerAction extends Action
{
    /**
     * @var bool
     */
    public $onlyOnDetail = true;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly MollieCustomerManager $customerManager
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.update_mollie_customer');
    }

    /**
     * @param Collection<int, MollieCustomer> $mollieCustomers
     */
    public function handle(ActionFields $fields, Collection $mollieCustomers): ActionResponse|static
    {
        if ($mollieCustomers->count() !== 1) {
            return self::danger($this->translator->translate('nova-action.error.multiple_models'));
        }

        $mollieCustomer = $mollieCustomers->first();
        assert($mollieCustomer instanceof MollieCustomer);

        $serializer = MollieSerializerFactory::getSerializer();

        $customer = $mollieCustomer->customer;

        $payload = new MollieCustomerRequestDTO(
            name: $customer->name,
            email: $customer->email,
            locale: $customer->locale,
            metadata: new MollieCustomerMetadataDTO(
                debtorId: $customer->customer_number
            )
        );

        try {
            $responseDTO = $this->customerManager->updateCustomer($mollieCustomer, $payload);

            return self::modal('modal-response', [
                'title' => $this->translator->translate('nova-action.success.update_mollie_customer'),
                'code' => json_encode($serializer->normalize($responseDTO), JSON_PRETTY_PRINT),
            ]);
        } catch (MollieCustomerApiException $exception) {
            return self::modal('modal-response', [
                'title' => $this->translator->translate('nova-action.failed.update_mollie_customer'),
                'code' => json_encode([
                    'message' => $exception->getMessage(),
                    'status' => $exception->getCode(),
                    'trace' => $exception->getTraceAsString(),
                ], JSON_PRETTY_PRINT),
            ]);
        }
    }
}
