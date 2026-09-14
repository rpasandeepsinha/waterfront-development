<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Payments\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Waterfront\Domain\Payments\Managers\MandateRevokeManager;
use Waterfront\Domain\Payments\Models\Mandate;
use Waterfront\Infra\MollieClient\Exceptions\MollieMandateApiException;
use Waterfront\Infra\PaytClient\Exceptions\PaytMandateApiException;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaRevokeMandateAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly MandateRevokeManager $mandateRevokeManager,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.revoke_mandate');
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

        try {
            $this->mandateRevokeManager->revokeMandate($mandate);

            return self::message(
                $this->translator->translate('nova-action.success.revoke_action'),
            );
        } catch (MollieMandateApiException|PaytMandateApiException $exception) {
            return self::modal('modal-response', [
                'title' => $this->translator->translate('nova-action.failed.revoke_action'),
                'code' => json_encode([
                    'message' => $exception->getMessage(),
                    'status' => $exception->getCode(),
                    'trace' => $exception->getTraceAsString(),
                ], JSON_PRETTY_PRINT),
            ]);
        }
    }
}
