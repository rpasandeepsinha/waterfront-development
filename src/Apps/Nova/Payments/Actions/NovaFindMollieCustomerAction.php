<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Payments\Actions;

use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Infra\MollieClient\Exceptions\MollieCustomerApiException;
use Waterfront\Infra\MollieClient\MollieCustomerClient;
use Waterfront\Infra\MollieClient\Serializers\MollieSerializerFactory;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaFindMollieCustomerAction extends Action
{
    /**
     * @var bool
     */
    public $onlyOnDetail = true;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly MollieCustomerClient $mollieCustomerClient,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.find_mollie_customer');
    }

    public function handle(ActionFields $fields): ActionResponse|static
    {
        $mollieCustomerReferenceId = $fields->get('mollie_customer_reference_id');
        assert(is_string($mollieCustomerReferenceId));

        $serializer = MollieSerializerFactory::getSerializer();

        try {
            $responseDTO = $this->mollieCustomerClient->getCustomerById($mollieCustomerReferenceId);

            return self::modal('modal-response', [
                'title' => $this->translator->translate('nova-action.search.title'),
                'code' => json_encode($serializer->normalize($responseDTO), JSON_PRETTY_PRINT),
            ]);
        } catch (MollieCustomerApiException $exception) {
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
            Text::make('mollie customer reference id', 'mollie_customer_reference_id')->required(),
        ];
    }
}
