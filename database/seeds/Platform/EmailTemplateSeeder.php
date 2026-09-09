<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Platform;

use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Waterfront\Domain\Domains\Mailers\MailDomainCreationFailed;
use Waterfront\Domain\Domains\Mailers\PremiumDomainPriceRequested;
use Waterfront\Domain\Email\Models\Template;
use Waterfront\Domain\Mailer\CustomerEmailUpdateEmail;
use Waterfront\Domain\Mailer\Templates\MailRecoveryCode;
use Waterfront\Domain\ManualProvisioning\Mailer\Customer\ActivatedManualSubscriptionCustomer;
use Waterfront\Domain\ManualProvisioning\Mailer\Employee\CanceledManualSubscriptionEmployee;
use Waterfront\Domain\ManualProvisioning\Mailer\Employee\CanceledReminderManualSubscription;
use Waterfront\Domain\ManualProvisioning\Mailer\Employee\OrderedManualSubscriptionEmployee;
use Waterfront\Domain\Microsoft365\Mailer\Microsoft365PrimaryDomainUpdated;
use Waterfront\Domain\Microsoft365\Mailer\Microsoft365SeatsChanged;
use Waterfront\Domain\Microsoft365\Mailer\Microsoft365SignMca;
use Waterfront\Domain\Ssl\Mailers\SslRenewalFailedMissingCname;
use Waterfront\Domain\Ssl\Mailers\SslRenewalSucces;
use Waterfront\Domain\Subscriptions\Mailer\MailCustomerSupportSubscriptionDowngrade;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCancelRevertedFailed;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionSuspendedDetails;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionUnSuspendedDetails;
use Waterfront\Domain\Transfers\Mailer\MailTransferAwayCompleted;
use Waterfront\Infra\Translation\TranslatorInterface;

class EmailTemplateSeeder extends Seeder
{
    public function __construct(private readonly TranslatorInterface $translator, private readonly ReferenceRepository $referenceRepo)
    {
    }

    public function run(): void
    {
        $template = new Template();
        $template->title = 'Activate account';
        $template->slug = 'activate-account';
        $template->subject = 'Activate account';
        $template->header = '<h2>Activeer uw account.</h2><p>Er is een account voor u aangemaakt.</p>';
        $template->body = 'email.activate-account';
        $template->footer = 'Footer placeholder';
        $template->save();
        $this->referenceRepo->set(PlatformReference::MAIL_TEMPLATE_ACTIVATE_ACCOUNT, $template);

        $template = new Template();
        $template->title = 'Account forgot password';
        $template->slug = 'account-forgot-password';
        $template->subject = 'Forgot Password';
        $template->header = '<h2>Wachtwoord vergeten.</h2><p>U heeft een nieuw wachtwoord aangevraagd.</p>';
        $template->body = 'email.account-forgot-password';
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Subscription created';
        $template->slug = 'subscription-created';
        $template->subject = 'Subscription Created';
        $template->header = '<h2>Bestelling geplaatst.</h2><p>U heeft een nieuwe bestelling geplaatst.</p>';
        $template->body = 'email.subscription-created';
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Plesk details';
        $template->slug = 'send-plesk-details';
        $template->subject = 'FTP Logingegevens';
        $template->header = 'Uw Plesk logingegevens';
        $template->body = 'email.send-plesk-details';
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Plesk mail details';
        $template->slug = 'send-plesk-email-only-details';
        $template->subject = 'Plesk Mail Only bestelling';
        $template->header = 'Uw Plesk bestelling';
        $template->body = 'email.send-plesk-email-only-details';
        $template->footer = 'Footer placeholder';
        $template->save();
        $this->referenceRepo->set(PlatformReference::MAIL_TEMPLATE_PLESK_DETAILS, $template);

        $template = new Template();
        $template->title = 'Subscription canceled';
        $template->slug = 'subscription_canceled';
        $template->subject = 'Abonnement opgezegd';
        $template->header = 'Uw opzegging is in behandeling';
        $template->body = 'email.subscription-canceled';
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Subscription cancel reverted';
        $template->slug = 'subscription-cancel-reverted';
        $template->subject = 'Abonnement opzegging geannuleerd';
        $template->header = 'Uw abonnement opzegging is geannuleerd';
        $template->body = 'email.subscription-cancel-reverted';
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Product upgrade';
        $template->slug = 'upgrade-product';
        $template->subject = $this->translator->translate('email.upgrade-product.subject');
        $template->header = '<h2>' . $this->translator->translate('email.upgrade-product.header') . '</h2>';
        $template->body = 'email.upgrade-product';
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Product downgrade';
        $template->slug = 'downgrade-product';
        $template->subject = $this->translator->translate('email.downgrade-product.subject');
        $template->header = '<h2>' . $this->translator->translate('email.downgrade-product.header') . '</h2>';
        $template->body = 'email.downgrade-product';
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Directadmin details';
        $template->slug = 'send-directadmin-details';
        $template->subject = $this->translator->translate('email.directadmin.subject');
        $template->header = '<h2>' . $this->translator->translate('email.directadmin.header') . '</h2>';
        $template->body = 'email.send-directadmin-details';
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Activate sitebuilder';
        $template->slug = 'send-sitebuilder-activation';
        $template->subject = $this->translator->translate('email.sitebuilder.subject');
        $template->header = '<h2>' . $this->translator->translate('email.sitebuilder.header') . '</h2>';
        $template->body = 'email.send-sitebuilder-activation';
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Transfer created';
        $template->slug = 'send-transfer-status-created-sender';
        $template->subject = $this->translator->translate('email.transfer-status.subject-created-sender');
        $template->header = $this->translator->translate('email.transfer-status.header-created-status');
        $template->body = $this->translator->translate('email.transfer-status.body-created-status');
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Incoming transfer';
        $template->slug = 'send-transfer-status-created-receiver';
        $template->subject = $this->translator->translate('email.transfer-status.subject-created-receiver');
        $template->header = $this->translator->translate('email.transfer-status.header-created-receiver');
        $template->body = $this->translator->translate('email.transfer-status.body-created-receiver');
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Transfer accepted sender';
        $template->slug = 'send-transfer-status-accepted-sender';
        $template->subject = $this->translator->translate('email.transfer-status.subject-accepted-sender');
        $template->header = $this->translator->translate('email.transfer-status.header-accepted-sender');
        $template->body = $this->translator->translate('email.transfer-status.body-accepted-sender');
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Transfer accepted receiver';
        $template->slug = 'send-transfer-status-accepted-receiver';
        $template->subject = $this->translator->translate('email.transfer-status.subject-accepted-receiver');
        $template->header = $this->translator->translate('email.transfer-status.header-accepted-receiver');
        $template->body = $this->translator->translate('email.transfer-status.body-accepted-receiver');
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Transfer started sender';
        $template->slug = 'send-transfer-status-started-sender';
        $template->subject = $this->translator->translate('email.transfer-status.subject-started-sender');
        $template->header = $this->translator->translate('email.transfer-status.header-started-sender');
        $template->body = $this->translator->translate('email.transfer-status.body-started-sender');
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Transfer started receiver';
        $template->slug = 'send-transfer-status-started-receiver';
        $template->subject = $this->translator->translate('email.transfer-status.subject-started-receiver');
        $template->header = $this->translator->translate('email.transfer-status.header-started-receiver');
        $template->body = $this->translator->translate('email.transfer-status.body-started-receiver');
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Transfer canceled sender';
        $template->slug = 'send-transfer-status-canceled-sender';
        $template->subject = $this->translator->translate('email.transfer-status.subject-canceled-sender');
        $template->header = $this->translator->translate('email.transfer-status.header-canceled-sender');
        $template->body = $this->translator->translate('email.transfer-status.body-canceled-sender');
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Transfer canceled receiver';
        $template->slug = 'send-transfer-status-canceled-receiver';
        $template->subject = $this->translator->translate('email.transfer-status.subject-canceled-receiver');
        $template->header = $this->translator->translate('email.transfer-status.header-canceled-receiver');
        $template->body = $this->translator->translate('email.transfer-status.body-canceled-receiver');
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Transfer completed sender';
        $template->slug = 'send-transfer-status-completed-sender';
        $template->subject = $this->translator->translate('email.transfer-status.subject-completed-sender');
        $template->header = $this->translator->translate('email.transfer-status.header-completed-sender');
        $template->body = $this->translator->translate('email.transfer-status.body-completed-sender');
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Transfer completed receiver';
        $template->slug = 'send-transfer-status-completed-receiver';
        $template->subject = $this->translator->translate('email.transfer-status.subject-completed-receiver');
        $template->header = $this->translator->translate('email.transfer-status.header-completed-receiver');
        $template->body = $this->translator->translate('email.transfer-status.body-completed-receiver');
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Transfer rejected sender';
        $template->slug = 'send-transfer-status-rejected-sender';
        $template->subject = $this->translator->translate('email.transfer-status.subject-rejected-sender');
        $template->header = $this->translator->translate('email.transfer-status.header-rejected-sender');
        $template->body = $this->translator->translate('email.transfer-status.body-rejected-sender');
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Transfer rejected receiver';
        $template->slug = 'send-transfer-status-rejected-receiver';
        $template->subject = $this->translator->translate('email.transfer-status.subject-rejected-receiver');
        $template->header = $this->translator->translate('email.transfer-status.header-rejected-receiver');
        $template->body = $this->translator->translate('email.transfer-status.body-rejected-receiver');
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Transfer failed sender';
        $template->slug = 'send-transfer-status-failed-sender';
        $template->subject = $this->translator->translate('email.transfer-status.subject-failed-sender');
        $template->header = $this->translator->translate('email.transfer-status.header-failed-sender');
        $template->body = $this->translator->translate('email.transfer-status.body-failed-sender');
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Transfer failed receiver';
        $template->slug = 'send-transfer-status-failed-receiver';
        $template->subject = $this->translator->translate('email.transfer-status.subject-failed-receiver');
        $template->header = $this->translator->translate('email.transfer-status.header-failed-receiver');
        $template->body = $this->translator->translate('email.transfer-status.body-failed-receiver');
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Transfer away completed';
        $template->slug = MailTransferAwayCompleted::getTemplateSlug();
        $template->subject = 'The transfer away has been completed';
        $template->header = 'The domain';
        $template->body = 'email.transfer-away-completed';
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Manual subscription ordered employee';
        $template->slug = OrderedManualSubscriptionEmployee::getTemplateSlug();
        $template->subject = $this->translator->translate('email.manual-subscription.subject-employee-ordered');
        $template->header = '<h2>' . $this->translator->translate('email.manual-subscription.header-employee-ordered') . '</h2>';
        $template->body = 'email.' . OrderedManualSubscriptionEmployee::getTemplateSlug();
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Manual subscription activated customer';
        $template->slug = ActivatedManualSubscriptionCustomer::getTemplateSlug();
        $template->subject = $this->translator->translate('email.manual-subscription.subject-customer-activated');
        $template->header = '<h2>' . $this->translator->translate('email.manual-subscription.header-customer-activated') . '</h2>';
        $template->body = 'email.' . ActivatedManualSubscriptionCustomer::getTemplateSlug();
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Manual subscription canceled employee';
        $template->slug = CanceledManualSubscriptionEmployee::getTemplateSlug();
        $template->subject = $this->translator->translate('email.manual-subscription.subject-employee-canceled');
        $template->header = '<h2>' . $this->translator->translate('email.manual-subscription.header-employee-canceled') . '</h2>';
        $template->body = 'email.' . CanceledManualSubscriptionEmployee::getTemplateSlug();
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Manual subscription canceled reminder employee';
        $template->slug = CanceledReminderManualSubscription::getTemplateSlug();
        $template->subject = $this->translator->translate('email.manual-subscription.subject-employee-canceled-reminder');
        $template->header = '<h2>' . $this->translator->translate('email.manual-subscription.header-employee-canceled') . '</h2>';
        $template->body = 'email.' . CanceledReminderManualSubscription::getTemplateSlug();
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Premium domain price requested';
        $template->slug = PremiumDomainPriceRequested::getTemplateSlug();
        $template->subject = $this->translator->translate('email.premium-domain-price-requested.subject');
        $template->header = '<h2>' . $this->translator->translate('email.premium-domain-price-requested.header') . '</h2>';
        $template->body = 'email.' . PremiumDomainPriceRequested::getTemplateSlug();
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Domein aanvragen mislukt';
        $template->slug = MailDomainCreationFailed::getTemplateSlug();
        $template->subject = 'Domain registration failed';
        $template->header = '<h2>Aanvragen domein mislukt.</h2>';
        $template->body = 'email.' . MailDomainCreationFailed::getTemplateSlug();
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Seats aangepast';
        $template->slug = Microsoft365SeatsChanged::getTemplateSlug();
        $template->subject = 'Seats aangepast';
        $template->header = 'Uw seat aantal is veranderd';
        $template->body = 'email.' . Microsoft365SeatsChanged::getTemplateSlug();
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Microsoft365 domein aangepast';
        $template->slug = Microsoft365PrimaryDomainUpdated::getTemplateSlug();
        $template->subject = 'Microsoft365 domein aangepast';
        $template->header = 'Uw Microsoft365 domein is gewijzigd';
        $template->body = 'email.' . Microsoft365PrimaryDomainUpdated::getTemplateSlug();
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = $this->translator->translate('subscription.suspended.email.title');
        $template->slug = MailSubscriptionSuspendedDetails::getTemplateSlug();
        $template->subject = $this->translator->translate('subscription.suspended.email.subject');
        $template->header = $this->translator->translate('subscription.suspended.email.header');
        $template->body = 'email.' . MailSubscriptionSuspendedDetails::getTemplateSlug();
        $template->footer = $this->translator->translate('subscription.suspended.email.footer');
        $template->save();

        $template = new Template();
        $template->title = $this->translator->translate('subscription.unsuspended.email.title');
        $template->slug = MailSubscriptionUnSuspendedDetails::getTemplateSlug();
        $template->subject = $this->translator->translate('subscription.unsuspended.email.subject');
        $template->header = $this->translator->translate('subscription.unsuspended.email.header');
        $template->body = 'email.' . MailSubscriptionUnSuspendedDetails::getTemplateSlug();
        $template->footer = $this->translator->translate('subscription.unsuspended.email.footer');
        $template->save();

        $template = new Template();
        $template->title = 'Herstelcode';
        $template->slug = MailRecoveryCode::getTemplateSlug();
        $template->subject = 'Herstelcode';
        $template->header = 'Hierbij uw herstelcode.';
        $template->body = 'email.' . MailRecoveryCode::getTemplateSlug();
        $template->footer = 'Footer placeholder';
        $template->save();
        $this->referenceRepo->set(PlatformReference::MAIL_TEMPLATE_RECOVERY_CODE, $template);

        $template = new Template();
        $template->title = $this->translator->translate('subscription.cancel-reverted.email.title');
        $template->slug = MailSubscriptionCancelRevertedFailed::getTemplateSlug();
        $template->subject = $this->translator->translate('subscription.cancel-reverted.email.subject');
        $template->header = $this->translator->translate('subscription.cancel-reverted.email.header');
        $template->body = 'email.' . MailSubscriptionCancelRevertedFailed::getTemplateSlug();
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Downgrade';
        $template->slug = MailCustomerSupportSubscriptionDowngrade::getTemplateSlug();
        $template->subject = 'Klant heeft downgrade aangevraagd';
        $template->header = 'automatisch bericht: Klant heeft downgrade aangevraagd';
        $template->body = 'email.' . MailCustomerSupportSubscriptionDowngrade::getTemplateSlug();
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'VPS details';
        $template->slug = 'cloudstack-manager-vps-details';
        $template->subject = 'VPS Logingegevens';
        $template->header = 'Uw VPS logingegevens';
        $template->body = 'email.send-cloudstack-manager-vps-details';
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Email Updated';
        $template->slug = CustomerEmailUpdateEmail::getTemplateSlug();
        $template->subject = 'Email updated';
        $template->header = 'Uw Customer Email is geupdated';
        $template->body = 'email.customer-email-updated';
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'E-mail adres aangemaakt';
        $template->slug = 'email-account-created';
        $template->subject = 'E-mail adres is aangemaakt';
        $template->header = 'E-mail adres is aangemaakt';
        $template->body = 'email.email-account-created-text';
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = $this->translator->translate('email.ssl-renewal-failed-missing-cname.title');
        $template->slug = SslRenewalFailedMissingCname::getTemplateSlug();
        $template->subject = $this->translator->translate('email.ssl-renewal-failed-missing-cname.subject');
        $template->header = '<h2>' . $this->translator->translate('email.ssl-renewal-failed-missing-cname.header') . '</h2>';
        $template->body = 'email.' . SslRenewalFailedMissingCname::getTemplateSlug();
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = $this->translator->translate('email.ssl-certificate-successfully-delivered.title');
        $template->slug = SslRenewalSucces::getTemplateSlug();
        $template->subject = $this->translator->translate('email.ssl-certificate-successfully-delivered.subject');
        $template->header = '<h2>' . $this->translator->translate('email.ssl-certificate-successfully-delivered.header') . '</h2>';
        $template->body = 'email.' . SslRenewalSucces::getTemplateSlug();
        $template->footer = 'Footer placeholder';
        $template->save();

        $template = new Template();
        $template->title = 'Accepteer MCA';
        $template->slug = 'microsoft-mca';
        $template->subject = 'Accepteer de MCA om de Microsoft order door te laten gaan';
        $template->header = 'Accepteer de MCA om de Microsoft order door te laten gaan';
        $template->body = 'email.' . Microsoft365SignMca::getTemplateSlug();
        $template->footer = 'Footer placeholder';
        $template->save();
    }
}
