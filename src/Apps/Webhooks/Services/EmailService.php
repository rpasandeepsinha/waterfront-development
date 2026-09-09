<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\Services;

use JsonException;
use Ramsey\Uuid\Uuid;
use Waterfront\Apps\Webhooks\DTO\ActivationTemplateData;
use Waterfront\Apps\Webhooks\DTO\KratosEmail;
use Waterfront\Apps\Webhooks\DTO\NewIdentityTemplateData;
use Waterfront\Apps\Webhooks\DTO\RecoveryTemplateData;
use Waterfront\Apps\Webhooks\Enums\KratosTemplates;
use Waterfront\Domain\Email\Actions\SendActivationMail;
use Waterfront\Domain\Email\Actions\SendRecoveryCodeMail;
use Waterfront\Domain\Email\Dto\Recipient;
use Waterfront\Domain\Mailer\MailActivateAccount;
use Waterfront\Domain\Mailer\Templates\MailActivateNewIdentity;
use Waterfront\Support\Exceptions\NotImplementedException;

class EmailService
{
    public function __construct(
        private readonly SendActivationMail $sendActivationMail,
        private readonly SendRecoveryCodeMail $sendRecoveryCodeMail,
    ) {
    }

    /**
     * @throws JsonException
     */
    public function dissectKratosJsonNet(string $jsonNet): KratosEmail
    {
        /** @var array{template_data: array<string, string>, template_type: string, recipient: string, identity: array<string, string>} $unpackedJson */
        $unpackedJson = json_decode($jsonNet, true, 512, JSON_THROW_ON_ERROR);
        $identity = $unpackedJson['identity'];

        $templateData = $unpackedJson['template_data'];
        $templateType = KratosTemplates::from(strval($unpackedJson['template_type']));

        $templateDataDto = match($templateType) {
            KratosTemplates::VERIFICATION_NEW_IDENTITY => new NewIdentityTemplateData(
                activationCode: $templateData['recovery_code'],
                activationUrl: $templateData['recovery_link'] . '&code=' . $templateData['recovery_code'],
                identity: $unpackedJson['recipient'],
            ),
            KratosTemplates::VERIFICATION_CODE_VALID => new ActivationTemplateData(
                activationCode: $templateData['verification_code'],
                activationUrl: $templateData['verification_url'] . '&code=' . $templateData['verification_code'],
                identity: $unpackedJson['recipient'],
            ),
            KratosTemplates::RECOVERY_CODE_VALID => new RecoveryTemplateData(
                recoveryCode: $templateData['recovery_code'],
                recoveryLink: $templateData['recovery_link'] . '&code=' . $templateData['recovery_code'],
                identity: $unpackedJson['recipient'],
            ),
        };

        return new KratosEmail(
            recipient: $unpackedJson['recipient'],
            templateType: $templateType,
            templateData: $templateDataDto,
            identity: $identity
        );
    }

    public function matchTemplateTypeAndSendEmail(KratosEmail $kratosEmail, string $name, string $toEmail, string $identityUuid): void
    {
        $recipient = new Recipient($name, $toEmail, Uuid::fromString($identityUuid));

        match($kratosEmail->templateData::class) {
            NewIdentityTemplateData::class => $this->sendActivationMail->execute(
                $recipient,
                new MailActivateNewIdentity($kratosEmail->templateData->activationCode, $kratosEmail->templateData->activationUrl)
            ),
            ActivationTemplateData::class => $this->sendActivationMail->execute(
                $recipient,
                new MailActivateAccount($kratosEmail->templateData->activationCode, $kratosEmail->templateData->activationUrl)
            ),
            RecoveryTemplateData::class => $this->sendRecoveryCodeMail->execute($recipient, $kratosEmail->templateData->recoveryCode, $kratosEmail->templateData->recoveryLink),
            default => throw new NotImplementedException(sprintf('Template type %s not implemented', $kratosEmail->templateType::class)),
        };
    }
}
