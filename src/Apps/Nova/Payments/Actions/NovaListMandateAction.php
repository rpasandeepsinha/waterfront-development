<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Payments\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Waterfront\Domain\Payments\Managers\MollieMandateManager;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Infra\MollieClient\Exceptions\MollieMandateApiException;
use Waterfront\Infra\MollieClient\Serializers\MollieSerializerFactory;
use Waterfront\Infra\PaytClient\Exceptions\PaytMandateApiException;
use Waterfront\Infra\PaytClient\PaytMandateClient;
use Waterfront\Infra\PaytClient\Serializers\PaytSerializerFactory;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaListMandateAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly MollieMandateManager $mollieMandateManager,
        private readonly PaytMandateClient $paytMandateClient,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.list_mandate');
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

        $mollieSerializer = MollieSerializerFactory::getSerializer();
        $paytSerializer = PaytSerializerFactory::getSerializer();

        try {
            $mollieMandates = $this->mollieMandateManager->listMandates($mollieCustomer);
            $paytMandates = $this->paytMandateClient->getPspMandatesByDebtorNumber((string) $mollieCustomer->customer->customer_number);

            return self::modal('modal-response', [
                'title' => $this->translator->translate('nova-action.search.title'),
                'code' => json_encode([
                    'Mollie' => $mollieSerializer->normalize($mollieMandates),
                    'Payt' => $paytSerializer->normalize($paytMandates),
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
