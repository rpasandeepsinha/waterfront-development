<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Services\ManualMigration;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Pipeline\Pipeline;
use JsonException;
use Waterfront\Apps\API\Compass\Requests\ManualMigrationValidateRequest;
use Waterfront\Apps\API\Ferry\Enum\ImplementableProducts;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\Ferry\Dto\ManualMigration\MigrationOption;
use Waterfront\Domain\Ferry\Dto\ManualMigration\ValidatedDomain;
use Waterfront\Domain\Ferry\Dto\ManualMigration\ValidatedHosting;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\ManualMigrationOption;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Pipes\DnsConfigurationPipe;
use Waterfront\Domain\Ferry\Pipes\DnsSecEnablePipe;
use Waterfront\Domain\Ferry\Pipes\DomainMigrationPipe;
use Waterfront\Domain\Ferry\Pipes\HostingMigrationPipe;
use Waterfront\Domain\Ferry\Pipes\MailOnlyMigrationPipe;
use Waterfront\Domain\Ferry\Pipes\NameserverMigrationPipe;
use Waterfront\Domain\Ferry\Pipes\SubscriptionPipe;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Exceptions\NotImplementedException;
use Waterfront\Support\Helpers\DnsHelper;
use Webmozart\Assert\Assert;

class ValidationService
{
    public function __construct(
        private readonly Pipeline $pipeline,
        private readonly SubscriptionFormatter $subscriptionFormatter,
        private readonly DnsService $dnsService,
        private readonly TranslatorInterface $translator,
        private readonly DnsHelper $dnsHelper,
    ) {
    }

    public function validate(ManualMigrationValidateRequest $request, Customer $customer): ValidatedDomain | ValidatedHosting
    {
        $subscriptionData = $this->subscriptionFormatter->formatFromRequest($request, $customer);
        Assert::stringNotEmpty($request->domain_name);

        // We're not validating customer data as the customer already exists.
        // Reference is not used for manual migrations
        $payload = new ValidationPayload(
            validationReference: $request->reference_customer_number,
            customer: [],
            subscriptions: $subscriptionData
        );

        $productKey = key($subscriptionData);
        Assert::string($productKey);
        $productGroupSlug = ImplementableProducts::getProductGroupTypeSlug(ImplementableProducts::from($productKey));

        $migrationPipe = $this->getMigrationValidationPipes($productGroupSlug);

        $processedValidationPayload = $this->pipeline
            ->send($payload)
            ->through($migrationPipe)
            ->via('handle')
            ->then(fn ($result): ValidationPayload => $result);
        assert($processedValidationPayload instanceof ValidationPayload);

        $ferryValidationResults = array_merge(...array_values($processedValidationPayload->validationResults));
        $ferryValidationResults = array_column($ferryValidationResults, 'id');
        $ferryValidationResults = array_map(fn (string $x) => MigrationValidation::from($x), $ferryValidationResults);

        return match ($productGroupSlug) {
            ProductGroupType::EXTENSION => $this->processDomain($ferryValidationResults, $request->domain_name),
            ProductGroupType::HOSTING => $this->processHosting($ferryValidationResults),
            default => throw new NotImplementedException()
        };
    }

    /**
     * @param array<MigrationValidation> $ferryValidationResults
     */
    public function processDomain(array $ferryValidationResults, string $domain): ValidatedDomain
    {
        $zoneInPowerDns = ! in_array(MigrationValidation::DNS_CONFIGURATION_ZONE_DOESNT_EXIST, $ferryValidationResults, true);
        $domainInSupportedRegistry = ! in_array(Migrationvalidation::DOMAIN_MIGRATION_FETCH_NOT_FOUND, $ferryValidationResults, true);
        $dnsSecTldSupported = ! in_array(MigrationValidation::DNSSEC_TLD_NOT_SUPPORTED, $ferryValidationResults, true);
        $zoneIsNative = false;

        if ($zoneInPowerDns) {
            // We purposefully ignore the exceptions, because Ferry told us that a zone exists.
            $zoneIsNative = $this->isZoneNative($domain);
        }

        $nameservers = $this->dnsHelper->getNameServers($domain);
        $dnsSecEnabled = $this->dnsHelper->isDnsSecEnabled($domain);
        $errors = $this->filterImportantErrors($ferryValidationResults);
        $options = $this->calculateDomainSelectableOptions($zoneInPowerDns, $domainInSupportedRegistry, $zoneIsNative, $dnsSecTldSupported);

        return new ValidatedDomain($nameservers, $zoneInPowerDns, $zoneIsNative, $dnsSecEnabled, $errors, $options);
    }

    /**
     * @param array<MigrationValidation> $ferryValidationResults
     */
    public function processHosting(array $ferryValidationResults): ValidatedHosting
    {
        $errors = $this->filterImportantErrors($ferryValidationResults);

        return new ValidatedHosting($errors, []);
    }

    /**
     * @return array<string, array<MigrationOption>>
     */
    public function calculateDomainSelectableOptions(bool $zoneInPowerDns, bool $domainInSupportedRegistry, bool $zoneIsNative, bool $dnsSecTldSupported): array
    {
        return [
            'nameservers' => [
                new MigrationOption(
                    title: ManualMigrationOption::NAMESERVERS_DO_NOTHING,
                    value: $this->translator->translate('manual-migration.validation.option.nameservers.do-nothing'),
                    selected: false,
                    description: $this->translator->translate('manual-migration.validation.option.do-nothing.description'),
                    optionAvailable: true
                ),
                new MigrationOption(
                    title: ManualMigrationOption::NAMESERVERS_UPDATE_NEW,
                    value: $this->translator->translate('manual-migration.validation.option.nameservers.update-new'),
                    selected: false,
                    description: $this->translator->translate('manual-migration.validation.option.nameservers.update-new.description'),
                    optionAvailable: $domainInSupportedRegistry
                ),
            ],
            'dnssec' => [
                new MigrationOption(
                    title: ManualMigrationOption::DNSSEC_DO_NOTHING,
                    value: $this->translator->translate('manual-migration.validation.option.dnssec.do-nothing'),
                    selected: false,
                    description: $this->translator->translate('manual-migration.validation.option.do-nothing.description'),
                    optionAvailable: true
                ),
                new MigrationOption(
                    title: ManualMigrationOption::DNSSEC_ENABLE,
                    value: $this->translator->translate('manual-migration.validation.option.dnssec.enable'),
                    selected: false,
                    description: $this->translator->translate('manual-migration.validation.option.dnssec.enable.description'),
                    optionAvailable: ($zoneInPowerDns && $domainInSupportedRegistry && $dnsSecTldSupported)
                ),
            ],
            'dns' => [
                new MigrationOption(
                    title: ManualMigrationOption::DNS_DO_NOTHING,
                    value: $this->translator->translate('manual-migration.validation.option.dns.do-nothing'),
                    selected: false,
                    description: $this->translator->translate('manual-migration.validation.option.do-nothing.description'),
                    optionAvailable: true
                ),
                new MigrationOption(
                    title: ManualMigrationOption::DNS_UPDATE_NATIVE,
                    value: $this->translator->translate('manual-migration.validation.option.dns.update-native'),
                    selected: false,
                    description: $this->translator->translate('manual-migration.validation.option.dns.update-native.description'),
                    optionAvailable: ($zoneInPowerDns && ! $zoneIsNative),
                ),
                new MigrationOption(
                    title: ManualMigrationOption::DNS_DEFAULT_TEMPLATE,
                    value: $this->translator->translate('manual-migration.validation.option.dns.default-template'),
                    selected: false,
                    description: $this->translator->translate('manual-migration.validation.option.dns.default-template.description'),
                    optionAvailable: ($zoneInPowerDns || ! $zoneIsNative)
                ),
                new MigrationOption(
                    title: ManualMigrationOption::DNS_NEW_EMPTY,
                    value: $this->translator->translate('manual-migration.validation.option.dns.new-empty'),
                    selected: false,
                    description: $this->translator->translate('manual-migration.validation.option.dns.new-empty.description'),
                    optionAvailable: ($zoneInPowerDns || ! $zoneIsNative),
                ),
            ],
        ];
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function isZoneNative(string $domain): bool
    {
        $zoneKind = $this->dnsService->getDnsZone($domain)->kind;

        return $zoneKind === PowerDnsZoneKind::MASTER->value || $zoneKind === PowerDnsZoneKind::NATIVE->value;
    }

    /**
     * @param array<MigrationValidation> $ferryValidationResults
     *
     * @return array<string, string>
     */
    public function filterImportantErrors(array $ferryValidationResults): array
    {
        // For manual migrations we see a bunch of things that Ferry sees as errors as valid, so we whitelist the errors that are important to us.
        $errorList = [
            MigrationValidation::PIPE_NOT_IMPLEMENTED->value => $this->translator->translate('manual-migration.validation.error.pipe-not-implemented'),
            MigrationValidation::DEFAULT_VALIDATION->value => $this->translator->translate('manual-migration.validation.error.default-validation'),
            MigrationValidation::SUBSCRIPTION_VOLUME_DISCOUNT_PRODUCT_INCORRECT->value => $this->translator->translate('manual-migration.validation.error.subscription-volume-discount-product-incorrect'),
            MigrationValidation::SUBSCRIPTION_ALREADY_MIGRATED->value => $this->translator->translate('manual-migration.validation.error.subscription-already-migrated'),
            MigrationValidation::DOMAIN_MIGRATION_DRIVER_CREDENTIALS_FAILED->value => $this->translator->translate('manual-migration.validation.error.domain-migration-driver-credentials-failed'),
            MigrationValidation::DOMAIN_MIGRATION_BUSINESS_UNIT_FAILED->value => $this->translator->translate('manual-migration.validation.error.domain-migration-business-unit-failed'),
            MigrationValidation::DOMAIN_MIGRATION_INVALID_PHONE->value => $this->translator->translate('manual-migration.validation.error.domain-migration-invalid-phone'),
            MigrationValidation::DNS_CONFIGURATION_ZONE_UNEXPECTED_EXCEPTION->value => $this->translator->translate('manual-migration.validation.error.dns-configuration-zone-unexpected-exception'),
            MigrationValidation::DNSSEC_ZONE_UNEXPECTED_EXCEPTION->value => $this->translator->translate('manual-migration.validation.error.dnssec-zone-unexpected-exception'),
        ];

        return array_filter($errorList, fn (string $errorKey) => in_array(MigrationValidation::from($errorKey), $ferryValidationResults, true), ARRAY_FILTER_USE_KEY);
    }

    /**
     * @throws NotImplementedException
     *
     * @return string[]
     */
    private function getMigrationValidationPipes(ProductGroupType $productGroupSlug): array
    {
        return match ($productGroupSlug) {
            ProductGroupType::EXTENSION => [
                SubscriptionPipe::class,
                DomainMigrationPipe::class,
                DnsConfigurationPipe::class,
                NameserverMigrationPipe::class,
                DnsSecEnablePipe::class,
            ],
            ProductGroupType::HOSTING => [
                SubscriptionPipe::class,
                HostingMigrationPipe::class,
                MailOnlyMigrationPipe::class,
            ],
            default => throw new NotImplementedException(),
        };
    }
}
