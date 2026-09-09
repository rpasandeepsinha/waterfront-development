<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Payments\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Infra\MollieClient\Exceptions\MollieMandateApiException;
use Waterfront\Infra\MollieClient\MollieMandateClient;
use Waterfront\Infra\MollieClient\Serializers\MollieSerializerFactory;
use Waterfront\Infra\PaytClient\Exceptions\PaytMandateApiException;
use Waterfront\Infra\PaytClient\PaytMandateClient;
use Waterfront\Infra\PaytClient\Serializers\PaytSerializerFactory;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaFindMandateAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly MollieMandateClient $mollieMandateClient,
        private readonly PaytMandateClient $paytMandateClient,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.find_mandate');
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

        /** @var string $mollieMandateReferenceId */
        $mollieMandateReferenceId = $fields->get('mollie_mandate_reference_id');

        /** @var string $paytMandateReferenceId */
        $paytMandateReferenceId = $fields->get('payt_mandate_reference_id');

        $mollieSerializer = MollieSerializerFactory::getSerializer();
        $paytSerializer = PaytSerializerFactory::getSerializer();

        try {
            $mollieResponseDTO = $this->mollieMandateClient->getMandate(
                $mollieCustomer->mollie_customer_reference_id,
                $mollieMandateReferenceId
            );

            $paytResponseDTO = $this->paytMandateClient->getPspMandatesByPaytId(
                $paytMandateReferenceId
            );

            return self::modal('modal-response', [
                'title' => $this->translator->translate('nova-action.search.title'),
                'code' => json_encode([
                    'Mollie' => $mollieSerializer->normalize($mollieResponseDTO),
                    'Payt'   => $paytSerializer->normalize($paytResponseDTO),
                ], JSON_PRETTY_PRINT),
            ]);
        } catch (MollieMandateApiException|PaytMandateApiException $exception) {
            return self::modal('modal-response', [
                'title' => $this->translator->translate('nova-action.search.title'),
                'code' => json_encode([
                    'message' => $exception->getMessage(),
                    'status' => $exception->getCode(),
                    'trace' => $exception->getTraceAsString(),
                ], JSON_PRETTY_PRINT),
            ]);
        }
    }

    /**
     * @return array<int, Text>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Mollie mandate reference id', 'mollie_mandate_reference_id')
                ->required(),

            Text::make('Payt mandate reference id', 'payt_mandate_reference_id')
                ->required(),
        ];
    }
}
