<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use UnhandledMatchError;
use ValueError;
use Waterfront\Apps\Webhooks\Services\EmailService;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Mailer\Exceptions\MailValidationException;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class EmailController
{
    public function __construct(private readonly TranslatorInterface $translator, private readonly LoggerInterface $logger)
    {
    }

    public function sendEmail(Request $request, EmailService $emailService): JsonResponse
    {
        try {
            $kratosData = $emailService->dissectKratosJsonNet($request->getContent());
        } catch (JsonException|ValueError|UnhandledMatchError $exception) {
            $this->logger->notice('Unable to parse kratos data: {exception.message}', [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);
            $this->logger->notice(sprintf('Unable to parse kratos data: %s', json_encode($request->getContent())));
            return new JsonResponse(status: Response::HTTP_NO_CONTENT);
        }

        $customer = Customer::where('email', $kratosData->templateData->identity)->first();

        try {
            $emailService->matchTemplateTypeAndSendEmail(
                $kratosData,
                $customer->first_name ?? '',
                $kratosData->recipient,
                $kratosData->identity['uuid'],
            );
        } catch (UnhandledMatchError|MailValidationException $exception) {
            $this->logger->notice('Sending mail failed: {exception.message}', [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);
            return new JsonResponse(status: Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(
            [
                'message' => $this->translator->translate('status.success'),
            ],
        );
    }
}
