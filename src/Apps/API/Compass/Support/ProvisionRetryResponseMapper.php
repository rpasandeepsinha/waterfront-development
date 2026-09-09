<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Support;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionErrorMessage;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Exceptions\RetryOriginNotFoundException;
use Waterfront\Domain\Provision\Factories\ProvisionSerializeFactory;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;
use Waterfront\Infra\Translation\TranslatorInterface;

class ProvisionRetryResponseMapper
{
    public function __construct(
        private readonly ProvisionSerializeFactory $serializerFactory,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function fromResult(ProvisionResultInterface $result): JsonResponse
    {
        if ($result->provisionStatus === ProvisionStatus::VALIDATION_ERROR) {
            $translatedValidationMessages = [];

            foreach ($result->validationResult->messages ?? [] as $field => $messages) {
                $responseField = $field === 'retryOf'
                    ? $field
                    : 'retryData.' . $field;

                $translatedValidationMessages[$responseField] = array_map(function (string $message): string {
                    $provisionError = ProvisionErrorMessage::tryFrom($message);

                    return $provisionError === null
                        ? $message
                        : $this->translator->translate('provision.errors.' . $provisionError->value);
                }, $messages);
            }

            return new JsonResponse([
                'message' => '',
                'errors' => $translatedValidationMessages,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($result->failed) {
            return new JsonResponse([
                'message' => $this->translator->translate('provision.retry.execution_failed'),
                'errors' => [],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $normalizedResult = $this->serializerFactory->get()->normalize($result);
        } catch (SerializerExceptionInterface) {
            return new JsonResponse([
                'message' => $this->translator->translate('provision.retry.execution_failed'),
                'errors' => [],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse($normalizedResult, Response::HTTP_OK);
    }

    public function fromException(Throwable $exception): JsonResponse
    {
        if ($exception instanceof RetryOriginNotFoundException) {
            return new JsonResponse([
                'message' => '',
                'errors' => [
                    'retryOf' => [
                        $this->translator->translate(
                            'provision.errors.' . ProvisionErrorMessage::RETRY_ORIGIN_NOT_FOUND->value
                        ),
                    ],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($exception instanceof SerializerExceptionInterface) {
            return new JsonResponse([
                'message' => $this->translator->translate('provision.retry.invalid_request'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        throw $exception;
    }
}
