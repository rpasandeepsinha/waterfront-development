<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytWebhookPayload;
use Waterfront\Apps\Webhooks\Services\Payt\PaytWebhookSignatureValidator;
use Waterfront\Domain\Payt\Services\PaytWebhookEventHandler;
use Waterfront\Infra\Configuration\ConfigurationException;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class PaytController extends Controller
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly ConfigurationInterface $configuration,
        private readonly PaytWebhookSignatureValidator $signatureValidator,
        private readonly SerializerInterface $serializer,
        private readonly PaytWebhookEventHandler $paytWebhookEventHandler,
    ) {
    }

    public function handleEvent(Request $request, string $businessUnit): JsonResponse
    {
        try {
            $secret = $this->configuration->getAsString('financial.bu-payt.' . $businessUnit . '.secret');
        } catch (ConfigurationException) {
            $this->logger->notice('Payt webhook BU validation failed, app-config not found for {meta.business_unit}', [
                LoggingContextKeys::META => ['business_unit' => $businessUnit],
            ]);

            return new JsonResponse(status: Response::HTTP_BAD_REQUEST);
        }

        $signature = $request->header('X-PAYT-SIGNATURE');

        if (! is_string($signature) || ! $this->signatureValidator->validate($request->getContent(), $signature, $secret)) {
            $this->logger->warning('Payt webhook signature validation failed for business unit: {meta}', [
                LoggingContextKeys::META => ['business_unit' => $businessUnit],
            ]);

            return new JsonResponse(status: Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $this->serializer->deserialize($request->getContent(), PaytWebhookPayload::class, 'json');
        } catch (NotNormalizableValueException | JsonException $exception) {
            $this->logger->notice('Failed to parse Payt webhook payload: {exception.message}', [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => ['business_unit' => $businessUnit],
            ]);

            return new JsonResponse(status: Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->logger->info('Webhook request received from Payt', [
                LoggingContextKeys::META => [
                    'business_unit' => $businessUnit,
                    'payt.webhook.content' => $request->getContent(),
                    'payt.webhook.event_name' => $payload->event->eventName,
                    'payt.webhook.event_time' => $payload->event->eventTime,
                ],
            ]);
            $this->paytWebhookEventHandler->handleEvent($payload, $businessUnit);
        } catch (InvalidArgumentException $exception) {
            $this->logger->notice('Failed to handle event: {exception.message}', [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => ['business_unit' => $businessUnit],
            ]);

            return new JsonResponse(status: Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(
            ['message' => $this->translator->translate('status.success')],
        );
    }
}
