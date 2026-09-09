<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Policies;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use SandwaveIo\LighthouseAuthBase\Authorization\AuthorizationService;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365Repository;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\OneTimeServices\Repositories\OneTimeServiceRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSlug;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Provision\Backup\Repositories\BackupDeploymentRepository;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Exceptions\UnableToSuspendSubscriptionException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionMutationRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\ServicePlanChecker;
use Waterfront\Domain\Subscriptions\Services\SubscriptionAddonService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Domain\Subscriptions\Services\SuspendSubscriptionService;
use Waterfront\Domain\Subscriptions\Services\UnsuspendSubscriptionService;
use Waterfront\Domain\Transfers\Services\TransferService;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;
use Waterfront\Infra\RtrClient\Enums\SubjectStatusType;
use Webmozart\Assert\Assert;

class SubscriptionPolicy
{
    public function __construct(
        private readonly AuthenticationManager $authManager,
        private readonly SubscriptionChangeService $subscriptionChangeService,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionMutationRepository $subscriptionMutationRepository,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly ProductRepository $productPriceRepository,
        private readonly DnsPolicy $dnsPolicy,
        private readonly Microsoft365Repository $m365Repository,
        private readonly CustomerPolicy $customerPolicy,
        private readonly HostingDeploymentPolicy $hostingDeploymentPolicy,
        private readonly AuthorizationService $authorizationService,
        private readonly SubscriptionAddonService $subscriptionAddonService,
        private readonly ServicePlanChecker $servicePlanChecker,
        private readonly TransferService $transferService,
        private readonly ProductRepository $productRepository,
        private readonly OneTimeServiceRepository $oneTimeServiceRepository,
        private readonly SuspendSubscriptionService $suspendSubscriptionService,
        private readonly UnsuspendSubscriptionService $unsuspendSubscriptionService,
        private readonly BackupDeploymentRepository $backupDeploymentRepository,
    ) {
    }

    public function assertCanManageSubscription(Subscription $subscription): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();

        if (! $this->isNotSuspendedAndModifiable($subscription)) {
            throw new AuthorizationException();
        }

        if (! $this->isModifiable($subscription)) {
            throw new AuthorizationException();
        }

        if (! $this->canAccess($subject, $subscription)) {
            throw new AuthorizationException();
        }

        if (! $this->assertSubscriptionActive($subscription)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanAccess(Subscription $subscription): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();

        if (! $this->canAccess($subject, $subscription)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanView(Subscription $subscription): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();

        if (! $this->canAccess($subject, $subscription)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanCancel(Subscription $subscription): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();

        $this->customerPolicy->assertCanManageSubscription();

        if (
            $subscription->administrative_status === AdministrativeStatus::CANCELED->value
            || ! $this->isModifiable($subscription)
            || $this->assertInvoicedEarly($subscription)
            || $this->isSuspended($subscription)
            || ! $this->canAccess($subject, $subscription)
            || $this->hasOpenMutation($subscription)
        ) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanCoupleHosting(Subscription $subscription): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();

        $this->customerPolicy->assertCanManageSubscription();

        $dnsSubscription = $this->getDnsSubscription($subscription);
        $this->canAccessDomainAndDnsSubscription($subscription, $dnsSubscription, $subject);

        if (
            ! $this->productSpecRepository->booleanSpecificationIsTrue(
                $dnsSubscription->product,
                ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT
            )
        ) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanManageDomain(Subscription $subscription): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();

        if (is_null($subscription->domain)) {
            throw new AuthorizationException();
        }

        if ($subscription->product->productGroup->slug !== ProductGroupType::EXTENSION) {
            throw new AuthorizationException();
        }

        if (! $this->isNotSuspendedAndModifiable($subscription)) {
            throw new AuthorizationException();
        }

        if (! $this->isModifiable($subscription)) {
            throw new AuthorizationException();
        }

        if (! $this->canAccess($subject, $subscription)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanRetryProvisioning(Subscription $subscription): void
    {
        $this->assertCanManageDomain($subscription);

        if (! in_array($subscription->technical_status, [DomainStatus::FAILED->value, TechnicalStatus::FAILED->value, TechnicalStatus::TRANSFER_FAILED->value], true)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanManageHosting(Subscription $subscription): void
    {
        $subject = $this->authManager->getAuthenticatedSubject();

        if ($subject->identitySchema->schemaId === SchemaId::EMPLOYEE || $subject->identitySchema->schemaId === SchemaId::SYSTEM) {
            return;
        }

        Assert::isInstanceOf($subject, AuthenticatedCustomer::class);

        if (is_null($subscription->domain)) {
            throw new AuthorizationException();
        }

        if ($subscription->product->productGroup->slug !== ProductGroupType::HOSTING && $subscription->product->productGroup->slug !== ProductGroupType::REDIRECT) {
            throw new AuthorizationException();
        }

        if (! $this->isNotSuspendedAndModifiable($subscription)) {
            throw new AuthorizationException();
        }

        if (! $this->isModifiable($subscription)) {
            throw new AuthorizationException();
        }

        if (! $this->canAccess($subject, $subscription)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanManageResellerHosting(string $uuid): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();

        $subscription = Subscription::query()
            ->whereProductGroupType(ProductGroupType::RESELLER_HOSTING)
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->where('uuid', $uuid)
            ->first();

        if ($subscription === null) {
            throw new AuthorizationException();
        }

        if ($subscription->customer_id !== $subject->customer->id) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanManageSsl(Subscription $subscription): void
    {
        $subject = $this->authManager->getAuthenticatedSubject();

        if ($subject->identitySchema->schemaId === SchemaId::EMPLOYEE || $subject->identitySchema->schemaId === SchemaId::SYSTEM) {
            return;
        }

        Assert::isInstanceOf($subject, AuthenticatedCustomer::class);

        if (is_null($subscription->domain)) {
            throw new AuthorizationException();
        }

        if ($subscription->product->productGroup->slug !== ProductGroupType::SSL) {
            throw new AuthorizationException();
        }

        if (! $this->isNotSuspendedAndModifiable($subscription)) {
            throw new AuthorizationException();
        }

        if (! $this->isModifiable($subscription)) {
            throw new AuthorizationException();
        }

        if (! $this->canAccess($subject, $subscription)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanDowngrade(Subscription $subscription): void
    {
        $this->authManager->getAuthenticatedCustomer();

        if ($this->subscriptionChangeService->getPotentialChanges(ProductChangeType::DOWNGRADE, $subscription)->isEmpty()) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanRequestDowngrade(Subscription $subscription): void
    {
        $this->authManager->getAuthenticatedCustomer();

        try {
            $this->assertCanDowngrade($subscription);
        } catch (AuthorizationException | AuthenticationException) {
            throw new AuthorizationException();
        }

        if ($this->subscriptionRepository->isDowngradeAlreadyRequested($subscription)) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanRetryDowngrades(Subscription $subscription): void
    {
        $this->assertCanManageHosting($subscription);

        $failedDowngradeExists = SubscriptionChange::query()
            ->where('subscription_uuid', $subscription->uuid)
            ->where('type', ProductChangeType::DOWNGRADE->value)
            ->where('status', SubscriptionChangeStatus::EXECUTION_FAILED->value)
            ->exists();

        if (! $failedDowngradeExists) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanChangeContract(Subscription $subscription): void
    {
        $this->authManager->getAuthenticatedCustomer();

        $this->customerPolicy->assertCanManageSubscription();

        $this->assertCanManageSubscription($subscription);

        if (
            $subscription->administrative_status === AdministrativeStatus::CANCELED->value
            || ! $this->subscriptionProductHasMultiYearPricing($subscription)
            || $this->hasOpenMutation($subscription)
            || $this->assertInvoicedEarly($subscription)
        ) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanUpgrade(Subscription $subscription): void
    {
        $this->customerPolicy->assertCanManageSubscription();
        $this->assertCanAccess($subscription);

        if ($this->subscriptionChangeService->getPotentialChanges(ProductChangeType::UPGRADE, $subscription)->count() > 0) {
            return;
        }

        try {
            if ($this->subscriptionAddonService->getPotentialAddons($subscription)->count() > 0) {
                return;
            }
        } catch (InvalidArgumentException) {
            // @ignoreException
        }

        throw new AuthorizationException();
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertCanUseWpSso(Subscription $subscription): void
    {
        $hostingDeployment = $subscription->hostingDeployment;

        if (! $hostingDeployment instanceof HostingDeployment) {
            throw new AuthorizationException();
        }

        if ($hostingDeployment->wp_installation_id === null) {
            throw new AuthorizationException();
        }
    }

    public function canCreateMutationWithDiscount(): bool
    {
        return $this->authorizationService->can($this->authManager->getAuthenticatedSubject()->identitySchema, Permissions::CREATE_SUBSCRIPTION_MUTATION_WITH_DISCOUNT, null);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertCanManageVirtualMachine(Subscription $subscription): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();
        $this->customerPolicy->assertCanManageVPS();

        if ($subscription->product->productGroup->slug !== ProductGroupType::VPS) {
            throw new AuthorizationException();
        }

        if (! $this->isNotSuspendedAndModifiable($subscription)) {
            throw new AuthorizationException();
        }

        if (! $this->isModifiable($subscription)) {
            throw new AuthorizationException();
        }

        if (! $this->canAccess($subject, $subscription)) {
            throw new AuthorizationException();
        }
    }

    /** @return string[] */
    public function getAvailableActions(Subscription $subscription): array
    {
        $actions = [];

        try {
            self::assertCanView($subscription);
            $actions[] = 'view';
        } catch (AuthenticationException | AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanCancel($subscription);
            $actions[] = 'cancel';
        } catch (AuthenticationException | AuthorizationException) {
            // @ignoreException
        }

        try {
            $this->dnsPolicy->assertCanManageDns($subscription);
            $actions[] = 'manageDns';
        } catch (AuthenticationException | AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanCoupleHosting($subscription);
            $actions[] = 'coupleHosting';
        } catch (AuthenticationException | AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanManageDomain($subscription);
            $actions[] = 'manageDomain';
        } catch (AuthenticationException | AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanManageHosting($subscription);
            $actions[] = 'manageHosting';
        } catch (AuthenticationException | AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanUpgrade($subscription);
            $actions[] = 'upgrade';
        } catch (AuthorizationException | AuthenticationException) {
            // @ignoreException
        }

        try {
            self::assertCanRetryProvisioning($subscription);
            $actions[] = 'retryProvisioning';
        } catch (AuthenticationException | AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanDowngrade($subscription);
            $actions[] = 'canDowngrade';
        } catch (AuthenticationException | AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanRequestDowngrade($subscription);
            $actions[] = 'canRequestDowngrade';
        } catch (AuthenticationException | AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanRetryDowngrades($subscription);
            $actions[] = 'retryDowngrades';
        } catch (AuthenticationException | AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanUseWpSso($subscription);
            $actions[] = 'canUseWpSso';
        } catch (AuthenticationException | AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanChangeContract($subscription);
            $actions[] = 'canChangeContract';
        } catch (AuthenticationException | AuthorizationException) {
            // @ignoreException
        }

        try {
            self::showCoupledM365CancellationAlert($subscription);
            $actions[] = 'showCoupledM365CancellationAlert';
        } catch (AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanSeeMailOnlyDetails($subscription);
            $actions[] = 'seeMailOnlyDetails';
        } catch (AuthorizationException) {
            // @ignoreException
        }

        if ($this->canCreateMutationWithDiscount()) {
            $actions[] = 'createSubscriptionMutationWithDiscount';
        }

        if ($this->hasOpenTransferServiceRequest($subscription)) {
            $actions[] = 'openTransferServiceRequest';
        }

        if ($this->canSuspendSubscription($subscription)) {
            $actions[] = 'suspendSubscription';
        }

        if ($this->canUnsuspendSubscription($subscription)) {
            $actions[] = 'unSuspendSubscription';
        }

        if ($this->canRetrySslDeployment($subscription)) {
            $actions[] = 'retryProvisionSsl';
        }

        if (! $this->hasOpenMutation($subscription) && ! $this->assertInvoicedEarly($subscription) && $subscription->administrative_status === AdministrativeStatus::ACTIVE->value) {
            $actions[] = 'extendContract';
        }

        if ($this->canUpdateSslRequestStatus($subscription)) {
            $actions[] = 'updateSslRequestStatus';
        }

        if ($this->canSetSslDnsVerifyRecord($subscription)) {
            $actions[] = 'setSslDnsVerifyRecord';
        }

        if ($this->canSyncCertificateFromRtr($subscription)) {
            $actions[] = 'syncCertificateFromRtr';
        }

        if ($this->canManageBackup($subscription)) {
            $actions[] = 'manageBackup';
        }

        if ($this->canRetryHosting($subscription)) {
            $actions[] = 'retryHosting';
        }

        if ($this->canAddDomainToSpamExperts($subscription)) {
            $actions[] = 'addDomainToSpamExperts';
        }

        return $actions;
    }

    public function canRetryHosting(Subscription $subscription): bool
    {
        return in_array(
            $subscription->product->productGroup->slug,
            [ProductGroupType::HOSTING, ProductGroupType::RESELLER_HOSTING],
            true
        );
    }

    /**
     * @throws AuthorizationException|AuthenticationException
     */
    public function assertCanManageNameservers(Subscription $subscription): void
    {
        $subject = $this->authManager->getAuthenticatedCustomer();

        if ($subscription->product->productGroup->slug !== ProductGroupType::EXTENSION) {
            throw new AuthorizationException();
        }

        if (is_null($subscription->domain)) {
            throw new AuthorizationException();
        }

        if (! $this->isNotSuspendedAndModifiable($subscription) || ! $this->isModifiable($subscription)) {
            throw new AuthorizationException();
        }

        if (! $this->canAccess($subject, $subscription)) {
            throw new AuthorizationException();
        }
    }

    public function hasServicePlan(Subscription $subscription): bool
    {
        return $this->servicePlanChecker->isServicePlanActive($subscription);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function canManageBackup(Subscription $subscription): bool
    {
        $subject = $this->authManager->getAuthenticatedSubject();

        if ($subscription->product->productGroup->slug !== ProductGroupType::BACKUP) {
            return false;
        }

        if (! $this->isNotSuspendedAndModifiable($subscription)) {
            return false;
        }

        if (! $this->isModifiable($subscription)) {
            return false;
        }

        if (
            ! $subject instanceof AuthenticatedCustomer
            || ! $this->canAccess($subject, $subscription)
        ) {
            return false;
        }

        // Check if backup deployment was successfully created and API calls can be made for retrieving SSO or usage of the backup deployment
        $backupDeployment = $this->backupDeploymentRepository->getByTag(Uuid::fromString($subscription->uuid))->first();
        if ($backupDeployment === null) {
            return false;
        }

        return true;
    }

    private function assertSubscriptionActive(Subscription $subscription): bool
    {
        if (in_array(
            $subscription->administrative_status,
            [
                ...AdministrativeStatus::administrativelyEnded(),
                ...AdministrativeStatus::getIneligibleForSuspension(),
            ]
        )) {
            return false;
        }
        return true;
    }

    private function hasOpenMutation(Subscription $subscription): bool
    {
        return $this->subscriptionMutationRepository->findOpenMutation($subscription) !== null;
    }

    private function subscriptionProductHasMultiYearPricing(Subscription $subscription): bool
    {
        $subscription->loadMissing('product');
        return $this->productPriceRepository->hasPricesWithMultipleContractPeriods($subscription->product);
    }

    private function canAccess(AuthenticatedCustomer $subject, Subscription $subscription): bool
    {
        return $subject->customer->is($subscription->customer);
    }

    private function isSuspended(Subscription $subscription): bool
    {
        return $subscription->technical_status === TechnicalStatus::SUSPENDING->value
            || $subscription->technical_status === TechnicalStatus::UNSUSPENDING->value
            || $subscription->administrative_status === AdministrativeStatus::SUSPENDED->value
            // In fail cases we will treat the subscription as suspended
            || $subscription->technical_status === TechnicalStatus::SUSPENSION_FAILED->value
            || $subscription->technical_status === TechnicalStatus::UNSUSPENSION_FAILED->value;
    }

    private function isModifiable(Subscription $subscription): bool
    {
        return ! $this->transferService->hasOpenTransfer($subscription);
    }

    private function isNotSuspendedAndModifiable(Subscription $subscription): bool
    {
        return ! $this->isSuspended($subscription);
    }

    private function assertInvoicedEarly(Subscription $subscription): bool
    {
        $carbon = CarbonImmutable::make($subscription->next_billing_date);
        assert($carbon instanceof CarbonImmutable);

        return $carbon->gt($subscription->end_date);
    }

    /**
     * @throws AuthorizationException
     */
    private function getDnsSubscription(Subscription $subscription): Subscription
    {
        if (is_null($subscription->domain)) {
            throw new AuthorizationException();
        }

        try {
            return $this->subscriptionRepository->getActiveDnsSubscription($subscription->domain);
        } catch (ModelNotFoundException $exception) {
            throw new AuthorizationException(previous: $exception);
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function canAccessDomainAndDnsSubscription(
        Subscription $subscription,
        Subscription $dnsSubscription,
        AuthenticatedCustomer $subject
    ): void {
        if (! $this->isNotSuspendedAndModifiable($subscription)) {
            throw new AuthorizationException();
        }

        if (
            ! $this->isModifiable($subscription)
            || ! $this->isModifiable($dnsSubscription)
        ) {
            throw new AuthorizationException();
        }

        if (
            ! $this->canAccess($subject, $subscription)
            || ! $this->canAccess($subject, $dnsSubscription)
        ) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function showCoupledM365CancellationAlert(Subscription $subscription): void
    {
        if ($this->m365Repository->getDeploymentsByCustomerAndDomainName($subscription->customer, $subscription->domain)->count() === 0) {
            throw new AuthorizationException();
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function assertCanSeeMailOnlyDetails(Subscription $subscription): void
    {
        $hostingDeployment = $subscription->hostingDeployment;

        if ($hostingDeployment === null) {
            throw new AuthorizationException();
        }

        $this->hostingDeploymentPolicy->assertCanManageMailAccounts($hostingDeployment);
    }

    private function hasOpenTransferServiceRequest(Subscription $subscription): bool
    {
        try {
            $transferProduct = $this->productRepository->findProductBySlug(ProductSlug::TRANSFER_SERVICE->value);
        } catch (ModelNotFoundException) {
            return false;
        }

        $ots = $this->oneTimeServiceRepository->getBySubscriptionIdAndProductId($subscription->id, $transferProduct->id);

        if ($ots === null || $ots->status === OneTimeServiceStatus::DONE) {
            return false;
        }
        return true;
    }

    private function canRetrySslDeployment(Subscription $subscription): bool
    {
        if ($subscription->product->productGroup->slug !== ProductGroupType::SSL) {
            return false;
        }

        if ($subscription->sslDeployment?->provider->slug !== ProviderSlug::REALTIME_REGISTER) {
            return false;
        }

        $retryableStatuses = [
            SubjectStatusType::FAILED->value,
            TechnicalStatus::FAILED->value,
            TechnicalStatus::ERROR->value,
            TechnicalStatus::FAI->value,
        ];

        return in_array($subscription->technical_status, $retryableStatuses, true);
    }

    private function canSuspendSubscription(Subscription $subscription): bool
    {
        try {
            $this->suspendSubscriptionService->checkIfSubscriptionIsEligible($subscription);

            $this->suspendSubscriptionService->technicalSuspendSupported($subscription);
        } catch (UnableToSuspendSubscriptionException) {
            return false;
        }

        return true;
    }

    private function canUnsuspendSubscription(Subscription $subscription): bool
    {
        try {
            $this->unsuspendSubscriptionService->checkIfSubscriptionIsEligible($subscription);

            $this->unsuspendSubscriptionService->technicalUnsuspendSupported($subscription);
        } catch (UnableToSuspendSubscriptionException) {
            return false;
        }

        return true;
    }

    private function canUpdateSslRequestStatus(Subscription $subscription): bool
    {
        if ($subscription->product->productGroup->slug !== ProductGroupType::SSL) {
            return false;
        }

        $sslDeployment = $subscription->sslDeployment;

        if (! $sslDeployment instanceof SslDeployment) {
            return false;
        }

        if ($sslDeployment->provider->slug !== ProviderSlug::REALTIME_REGISTER) {
            return false;
        }

        return true;
    }

    private function canAddDomainToSpamExperts(Subscription $subscription): bool
    {
        if ($this->authManager->getAuthenticatedSubject()->identitySchema->schemaId !== SchemaId::EMPLOYEE) {
            return false;
        }

        if ($subscription->domain === null) {
            return false;
        }

        return in_array(
            $subscription->product->productGroup->slug,
            [ProductGroupType::HOSTING, ProductGroupType::EXTENSION],
            true
        );
    }

    private function canSetSslDnsVerifyRecord(Subscription $subscription): bool
    {
        if ($subscription->product->productGroup->slug !== ProductGroupType::SSL) {
            return false;
        }

        $sslDeployment = $subscription->sslDeployment;

        if (! $sslDeployment instanceof SslDeployment) {
            return false;
        }

        if ($sslDeployment->provider->slug !== ProviderSlug::REALTIME_REGISTER) {
            return false;
        }

        return true;
    }

    private function canSyncCertificateFromRtr(Subscription $subscription): bool
    {
        if ($subscription->product->productGroup->slug !== ProductGroupType::SSL) {
            return false;
        }

        $sslDeployment = $subscription->sslDeployment;

        if ($sslDeployment === null) {
            return false;
        }

        return $sslDeployment->provider->slug === ProviderSlug::REALTIME_REGISTER;
    }
}
