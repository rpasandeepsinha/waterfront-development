<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Services;

use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Waterfront\Infra\RtrClient\Enums\RtrValidationError;
use Waterfront\Infra\Translation\TranslatorInterface;

class RtrErrorParseService
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getTranslatedRtrError(string $message): string
    {
        $error = $this->getRtrErrorFromMessage($message);

        return $this->mapRtrErrorToTranslation($error, $message);
    }

    public function getRtrErrorFromMessage(string $message): ?RtrValidationError
    {
        foreach (RtrValidationError::cases() as $rtrError) {
            if (Str::contains($message, $rtrError->value)) {
                return $rtrError;
            }
        }

        $this->logger->warning(sprintf(
            'Unable to parse (unknown) RTR error message [%s]. Returning default.',
            $message
        ));

        return null;
    }

    private function mapRtrErrorToTranslation(?RtrValidationError $error, string $message): string
    {
        return match ($error) {
            RtrValidationError::TRANSFER_TOO_EARLY => $this->translateTransferTooEarlyMessage($message),
            RtrValidationError::ADDRESS_INCORRECT => $this->translator->translate('rtr-error.address-incorrect'),
            RtrValidationError::AUTH_CODE_INCORRECT => $this->translator->translate('rtr-error.auth-code-incorrect'),
            RtrValidationError::AUTH_CODE_INVALID => $this->translator->translate('rtr-error.auth-code-invalid'),
            RtrValidationError::AUTH_CODE_REQUIRED => $this->translator->translate('rtr-error.auth-code-required'),
            RtrValidationError::CONTACT_INFO_MISSING => $this->translator->translate('rtr-error.contact-info-missing'),
            RtrValidationError::PRIVACY_PROTECT_NOT_SUPPORTED => $this->translator->translate('rtr-error.privacy-protect-not-supported'),
            RtrValidationError::VAT_NUMBER_CONTACT_REQUIRED,
            RtrValidationError::VAT_NUMBER_REQUIRED => $this->translator->translate('rtr-error.vat-number-required'),
            RtrValidationError::OBJECT_STATUS,
            RtrValidationError::TRANSFER_BLOCKED => $this->translator->translate('rtr-error.transfer-blocked'),
            default => $this->translator->translate('rtr-error.general')
        };
    }

    private function translateTransferTooEarlyMessage(string $message): string
    {
        $matchFound = preg_match('(\d+-\d+-\d+)', $message, $date);

        if ($matchFound === 1) {
            return $this->translator->translate('rtr-error.transfer-too-early-with-date', ['date' => $date[0]]);
        }
        return $this->translator->translate('rtr-error.transfer-too-early');
    }
}
