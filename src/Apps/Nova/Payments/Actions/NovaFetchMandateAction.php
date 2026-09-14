<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Payments\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Waterfront\Domain\Payments\Managers\MollieMandateManager;
use Waterfront\Domain\Payments\Managers\PaytMandateManager;
use Waterfront\Domain\Payments\Models\Mandate;
use Waterfront\Infra\MollieClient\Exceptions\MollieMandateApiException;
use Waterfront\Infra\MollieClient\Serializers\MollieSerializerFactory;
use Waterfront\Infra\PaytClient\Exceptions\PaytMandateApiException;
use Waterfront\Infra\PaytClient\Serializers\PaytSerializerFactory;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaFetchMandateAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly MollieMandateManager $mollieMandateManager,
        private readonly PaytMandateManager $paytMandateManager,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.fetch_mandate');
    }

    /**
     * @param Collection<int, Mandate> $mandates
     */
    public function handle(ActionFields $fields, Collection $mandates): ActionResponse|static
    {
        if ($mandates->count() !== 1) {
            return self::danger($this->translator->translate('nova-action.error.multiple_models'));
        }

        $mandate = $mandates->first();
        assert($mandate instanceof Mandate);

        $mollieCustomer = $mandate->mollieCustomer;

        $mollieSerializer = MollieSerializerFactory::getSerializer();
        $paytSerializer = PaytSerializerFactory::getSerializer();

        try {
            $mollieResponseDTO = $this->mollieMandateManager->getMandate($mollieCustomer, $mandate);

            $paytResponseDTO = $mandate->payt_mandate_reference_id !== null
                ? $this->paytMandateManager->getMandate($mandate)
                : null;

            return self::modal('modal-response', [
                'title' => $this->translator->translate('nova-action.search.title'),
                'code' => json_encode([
                    'Mollie' => $mollieSerializer->normalize($mollieResponseDTO),
                    'Payt' => $paytResponseDTO !== null ? $paytSerializer->normalize($paytResponseDTO) : null,
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
}
