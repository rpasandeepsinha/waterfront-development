<?php

declare(strict_types=1);

namespace Waterfront\Domain\ResellerHosting\Services;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use ReflectionException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\DirectAdmin\DirectAdminPassword;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\ResellerHosting\Exceptions\ResellerHostingException;
use Waterfront\Domain\ResellerHosting\Exceptions\ResellerHostingNameserverCoupleException;
use Waterfront\Domain\ResellerHosting\Exceptions\ResellerHostingSslCoupleException;
use Waterfront\Domain\ResellerHosting\Factories\ResellerHostingServiceFactory;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\ResellerHosting\Parameters\AppResellerHostingDomainCoupleParameters;
use Waterfront\Domain\ResellerHosting\Parameters\ResellerHostingParameters as DirectAdminParameters;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Support\Enums\LoggingContextKeys;

class ResellerHostingService
{
    public function __construct(
        private readonly ResellerHostingServiceFactory $resellerHostingServiceFactory,
        private readonly DirectAdminPassword $passwordGenerator
    ) {
    }

    /**
     *
     * @throws ResellerHostingException
     *
     * @return string[]
     */
    public function getSubAccounts(ResellerHostingDeployment $resellerHostingDeployment): array
    {
        return $this->resellerHostingServiceFactory
            ->driver($resellerHostingDeployment->provider->slug)
            ->getSubAccounts($resellerHostingDeployment);
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getCustomerPackages(Customer $customer): Collection
    {
        return $customer->subscriptions()
            ->whereIn('administrative_status', [
                AdministrativeStatus::ACTIVE->value,
                AdministrativeStatus::CANCELED->value,
            ])
            ->whereHas('product.productGroup', function (Builder $q): void {
                $q->where('slug', ProductGroupType::RESELLER_HOSTING);
            })
            ->get();
    }

    /**
     * @throws ResellerHostingException
     */
    public function getCustomerPackage(Customer $customer, string $subscriptionUuid): Subscription
    {
        $subscription = $customer->subscriptions()
            ->where(['uuid' => $subscriptionUuid])
            ->whereIn('administrative_status', [
                AdministrativeStatus::ACTIVE->value,
                AdministrativeStatus::CANCELED->value,
            ])
            ->whereHas('product.productGroup', function (Builder $q): void {
                $q->where('slug', ProductGroupType::RESELLER_HOSTING);
            })
            ->first();

        if (! $subscription instanceof Subscription) {
            throw ResellerHostingException::noResellerHostingPackagesFound($customer->id);
        }

        return $subscription;
    }

    /**
     * @throws ResellerHostingException
     */
    public function create(
        string $subscriptionUuid,
        string $contactPersonName,
        string $contactEmail,
        int|null $serverId,
        Product $product,
        Customer $customer
    ): void {
        $server = $serverId !== null
            ? Server::find($serverId)
            : $this->resellerHostingServiceFactory->defaultDriver()->findServer();

        assert($server instanceof Server);

        Log::info(
            self::class . '::create - Create reseller hosting',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscriptionUuid,
                LoggingContextKeys::SERVER_ID => $server->id,
                LoggingContextKeys::META => [
                    'contact email'  => $contactEmail,
                    'contact person' => $contactPersonName,
                    'email'          => $contactEmail,
                ],
            ]
        );

        try {
            $provider = $this->defaultDriverModel();

            $status = $this->resellerHostingServiceFactory
                ->defaultDriver()
                ->create(
                    contactPersonName: $contactPersonName,
                    contactEmail: $contactEmail,
                    customerEmail: $customer->email,
                    customerUuid: $customer->uuid,
                    subscriptionUuid: $subscriptionUuid,
                    specs: $product->productSpecs->toArray(),
                    providerId: $provider->id,
                    server: $server
                );
        } catch (ResellerHostingException | ModelNotFoundException $exception) {
            Log::error(
                self::class . '::create - status code: ' . $exception->getCode()
                . ', message: ' . $exception->getMessage()
                . ', trace: ' . $exception->getTraceAsString()
            );

            $status = Result::STATUS_ERROR;

            HostingDeployment::where('subscription_uuid', $subscriptionUuid)->forceDelete();
        }

        /**
         * There isn't always a deployment so you can't do $hostingDeployment->subscription->domain
         * so unfortunately we still have to query the main subscription here. And also the technical_status
         * fields are not yet moved to the deployment.
         */
        Subscription::where('uuid', $subscriptionUuid)->update(
            [
                'technical_status' => $status,
            ]
        );
    }

    public function terminate(ResellerHostingDeployment $deployment): void
    {
        Log::info(
            self::class . '::terminate - Terminating reseller hosting',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $deployment->subscription_uuid,
            ]
        );

        $success = $this->resellerHostingServiceFactory
            ->driver($deployment->provider->slug)
            ->terminate($deployment);

        if ($success) {
            $deployment->subscription->technical_status = TechnicalStatus::DELETED->value;
            $deployment->subscription->save();
            $deployment->delete();
        }
    }

    /**
     *
     * @throws ResellerHostingException
     *
     * @return array<string,string>
     */
    public function resetPassword(Customer $customer, ResellerHostingDeployment $resellerHostingDeployment): array
    {
        $driver = $resellerHostingDeployment->provider->slug;

        if ($resellerHostingDeployment->relevant_username === null) {
            throw ResellerHostingException::noRelevantUsernameFound($resellerHostingDeployment->subscription_uuid);
        }

        $parameters = $this->getResetPasswordParameters(
            $customer,
            $resellerHostingDeployment->subscription->product,
            $resellerHostingDeployment
        );

        Log::info(
            self::class . '::resetPassword - Resetting password for reseller hosting',
            [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::CUSTOMER_NUMBER => $customer->customer_number,
                LoggingContextKeys::DOMAIN_NAME => $resellerHostingDeployment->subscription->domain,
                LoggingContextKeys::SUBSCRIPTION_UUID => $resellerHostingDeployment->subscription->uuid,
            ]
        );

        return $this->resellerHostingServiceFactory
            ->driver($driver)
            ->resetPassword($parameters, $customer->uuid);
    }

    public function modifyCustomerForResellerMigrations(
        ProviderSlug $driver,
        string $username,
    ): bool {
        return $this->resellerHostingServiceFactory
            ->driver($driver)
            ->modifyCustomerForResellerMigrations($username);
    }

    /**
     * @throws DirectAdminCommandException
     * @throws GuzzleException
     * @throws ReflectionException
     * @throws ResellerHostingException
     * @throws ResellerHostingNameserverCoupleException
     * @throws ResellerHostingSslCoupleException
     */
    public function coupleExistingDomain(
        ResellerHostingDeployment $resellerHostingDeployment,
        Subscription $domainSubscription,
        AppResellerHostingDomainCoupleParameters $parameters,
    ): void {
        Log::info(
            self::class . '::coupleExistingDomain - Coupling existing domain for reseller hosting',
            [
                LoggingContextKeys::META => [
                    'reseller_hosting_subscription_uuid' => $resellerHostingDeployment->uuid,
                    'domain_subscription_uuid' => $domainSubscription->uuid,
                    'parameters_domain' => $parameters->getDomain(),
                    'parameters_username' => $parameters->getUserName(),
                    'parameters_uuid' => $parameters->getUuid(),
                ],
            ]
        );

        $this->resellerHostingServiceFactory
            ->driver($resellerHostingDeployment->provider->slug)
            ->coupleExistingDomain(
                $resellerHostingDeployment,
                $domainSubscription,
                $parameters,
            );
    }

    /**
     * @throws ModelNotFoundException
     */
    private function defaultDriverModel(): Provider
    {
        return Provider::where('type', ProviderType::HOSTING)->where('default', true)->firstOrFail();
    }

    /**
     * Todo : Make return conditional after PleskImplementation.
     *        This wil be done when we handle the story WATER-1772.
     */
    private function getResetPasswordParameters(Customer $customer, Product $product, ResellerHostingDeployment $deployment): DirectAdminParameters
    {
        assert($deployment->directadmin_customer_username !== null);

        return new DirectAdminParameters(
            contactPerson: $customer->getContactNameAttribute(),
            username: $deployment->directadmin_customer_username,
            password: $this->passwordGenerator->generatePassword(12),
            email: $customer->email,
            domain: $deployment->subscription->domain,
            ipv4Address: $deployment->server->ipv4,
            ipv6Address: null,
            packageName: $product->name,
            resellerHostingId: null,
            providerId: $deployment->provider->id,
        );
    }
}
