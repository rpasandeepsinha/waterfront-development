<?php

declare(strict_types=1);

namespace Tests\Infra\PleskClientLive;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateCustomer\Result as CustomerCreateResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer\Parameters as CustomerDeleteParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteWebsite\Parameters as WebsiteDeleteParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailForwardingCreate\Parameters as EmailForwardingCreateParameters
;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailGetAccountSettings\Parameters as EmailGetAccountSettingsParameters
;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailSetCatchAll\Parameters as EmailSetCatchAllParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters as HostingParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Infra\PleskClient\Exceptions\PleskLackOffResourceException;
use Waterfront\Infra\PleskClient\Services\CustomerClient;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

/**
 * Tests using the local Plesk Docker container.
 *
 * Be aware that this test is mainly written for a local test environment of plesk.
 * So make sure that service plans that are declared in the const SERVICEPLAN, UPGRADEPLAN & PLANTOCHANGE are created
 * It is also possible that some tests return an error, this has mainly to do with DNS, be aware of this too. This
 * is why some tests check for a 500 status
 */
#[CoversClass(CustomerClient::class)]
#[CoversClass(HostingPackageClient::class)]
class PleskClientTest extends IntegrationTestCase
{
    use RefreshDatabase;

    public const string DOMAIN = 'lekkertesten.nl';

    //This serviceplan lays somwhere in the middle of the sizes and must be of the type hosting
    public const string SERVICEPLAN = 'Start';
    //This serviceplan must not have the hostingtype set
    public const PLANTOCHANGE = ProductType::EMAIL_START->value;
    //This must be a higher paln with hosting set
    public const string UPGRADEPLAN = 'premium';

    public const array PARAMETERS = [
        'contactPersonName' => 'Test Kees22',
        'emailAddress' => 'test22.kees@sandwave.io',
        'domain' => self::DOMAIN,
        'ipv4Address' => '172.17.0.2',
        'username' => 'testkees.n_22',
        'password' => 'Test_password1!',
        'phpVersion' => 'plesk-php73-fastcgi',
        'forwardingUrl' => null,
        'enableDns' => 'OFF',
        'enableSsh' => 'OFF',
        'enableSsl' => 'OFF',
        'notify' => 'yes',
        'package' => self::SERVICEPLAN,
    ];

    // These settings are not available on the local Plesk container
    public const array FORGET_HOSTING_SETTINGS = [
        'properties.webstat',
        'properties.webstat_protected',
        'permissions.ext_permission_acronis_backup_acronis_backup',
        'permissions.manage_mail_settings',
        'permissions.manage_maillists',
        'permissions.manage_spamfilter',
        'permissions.manage_virusfilter',
    ];

    private CustomerClient $customerClient;

    private HostingPackageClient $hostingPackageClient;

    private HostingParameters $hostingParameters;

    protected function setUp(): void
    {
        parent::setUp();

        $this->updateConfig();
        $this->setupClients();

        $this->hostingParameters = HostingParameters::create(self::PARAMETERS);
    }

    #[Test]
    public function customerCreateSuccess(): void
    {
        $customerResult = $this->createCustomer();
        self::assertSame(Result::STATUS_OK, $customerResult->getStatus());
    }

    #[Test]
    public function customerCreateAlreadyExists(): void
    {
        //Create customer , this sets the customer_id in the parameters
        $this->createCustomer();
        $secondCustomerResult = $this->customerClient->createCustomer($this->hostingParameters);

        self::assertSame(Result::STATUS_ERROR, $secondCustomerResult->getStatus());

        $errorMessage = 'User account ' . $this->hostingParameters->getUsername() . ' already exists.';
        self::assertSame($errorMessage, $secondCustomerResult->getErrorMessage());
    }

    /**
     * Be aware off te following of the fact that the response can fail localy with a 500 http Status
     *   <?xml version="1.0" encoding="UTF-8"?>
     *   <packet version="1.6.9.1"><webspace><add><result><status>error</status><errcode>1007</errcode>
     *  <errtext>Unable to create the domain testkees.nl because a DNS record pointing to the host testkees.nl already exists.</errtext>
     *  </result></add></webspace></packet>.
     */
    #[Test]
    public function hostingCreateSuccess(): void
    {
        $this->createCustomer();

        $hostingResult = $this->hostingPackageClient->createHosting($this->hostingParameters);

        self::assertSame(Result::STATUS_ERROR, $hostingResult->getStatus());
        self::assertSame(500, $hostingResult->getErrorCode());
    }

    #[Test]
    public function mailOnlyHostingCreateSuccess(): void
    {
        $this->hostingParameters->setMailOnlyHosting(true);

        $this->createCustomer();
        $hostingResult = $this->hostingPackageClient->createHosting($this->hostingParameters);

        $result = $this->hostingPackageClient->getHostingSite($this->hostingParameters);
        $resultData = $result->getResponseBody();
        $hostingType = Arr::get($resultData, 'site.get.result.data.gen_info.htype');

        self::assertSame('none', $hostingType);

        self::assertSame(Result::STATUS_OK, $hostingResult->getStatus());
    }

    #[Test]
    public function hostingCreateOwnerMismatch(): void
    {
        $customerResult = $this->createCustomer();
        $customerId = $customerResult->getCustomerId();

        $this->hostingParameters->setCustomerId('1908');

        $hostingResult = $this->hostingPackageClient->createHosting($this->hostingParameters);

        self::assertSame(Result::STATUS_ERROR, $hostingResult->getStatus());
        self::assertSame(1015, $hostingResult->getErrorCode());
        self::assertSame('Owner does not exist', $hostingResult->getErrorMessage());

        $this->hostingParameters->setCustomerId($customerId);
    }

    #[Test]
    public function servicePlanExists(): void
    {
        self::assertTrue($this->hostingPackageClient->servicePlanExists($this->hostingParameters));
    }

    #[Test]
    public function servicePlanExitsNotFound(): void
    {
        $this->hostingParameters->setPackage('ik_besta_niet');
        self::assertFalse($this->hostingPackageClient->servicePlanExists($this->hostingParameters));
    }

    /**
     * todo : refactor this test in ticket https://yh-jira.atlassian.net/browse/WATER-2488.
     */
    #[Test]
    public function hostingUpgradeSuccess(): void
    {
        $this->createCustomer();

        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $upgradeResult = $this->hostingPackageClient->changeServicePlan(self::DOMAIN, self::UPGRADEPLAN);

        self::assertSame(Result::STATUS_ERROR, $upgradeResult->getStatus());
        self::assertSame(500, $upgradeResult->getErrorCode());
    }

    #[Test]
    public function changeServicePlanPlanNotExists(): void
    {
        $this->createCustomer();

        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $this->expectException(PleskClientException::class);
        $this->expectExceptionMessageIs('Service plan nonexisting_plan was not found on the given plesk server');
        $this->hostingPackageClient->changeServicePlan(self::DOMAIN, 'nonexisting_plan');
    }

    #[Test]
    public function changeServicePlanUnknownDomain(): void
    {
        $this->createCustomer();

        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $this->expectException(PleskClientException::class);
        $this->expectExceptionMessageIs('[Error code : 1013]:: Api message : domain does not exist');
        $this->hostingPackageClient->changeServicePlan('unknowndomain.nl', self::PLANTOCHANGE);
    }

    /**
     * This test works only when you have to much resources in use
     * So do a upload first.
     */
    #[Test]
    public function changeServicePlanNoResources(): void
    {
        $this->createCustomer();
        $this->hostingParameters->setPackage(self::UPGRADEPLAN);
        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $this->expectException(PleskClientException::class);
        $this->expectExceptionMessageIs(
            '[Waterfront\Infra\PleskClient\Exceptions\PleskClientException]:: Not enough resources available for domain',
        );

        $this->hostingPackageClient->changeServicePlan(self::DOMAIN, self::SERVICEPLAN);
    }

    #[Test]
    public function changeServicePlanHostingTypeMismatch(): void
    {
        $this->createCustomer();
        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $errorMessage = sprintf(
            '[Waterfront\Infra\PleskClient\Exceptions\PleskLackOffResourceException]:: Serviceplan : %s | domain : %s | PleskError Unable to accept the template: the following limitations are exceeded.',
            self::PLANTOCHANGE,
            $this->hostingParameters->getDomain(),
        );

        $this->expectException(PleskLackOffResourceException::class);
        $this->expectExceptionMessageIs($errorMessage);

        $this->hostingPackageClient->changeServicePlan(self::DOMAIN, self::PLANTOCHANGE);
    }

    #[Test]
    public function changeServicePlanSwitchBetweenHostingType(): void
    {
        //Create a customer + site
        $this->createCustomer();
        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $fowardedEmailInfo = [
            'sourceEmailAddressUsername' => 'info',
            'destinationEmailAddresses' => ['forwarded@info.nl'],
        ];
        $this->createForwardMail($fowardedEmailInfo);

        $fowardedEmailTesty = [
            'sourceEmailAddressUsername' => 'testy',
            'destinationEmailAddresses' => ['forwarded@testy.nl'],
        ];
        $this->createForwardMail($fowardedEmailTesty);

        $catchAllForward = [
            'destinationEmailAddress' => 'forwarded@catchall.nl',
        ];

        $this->createCatchAllMail($catchAllForward);

        $logMessage = sprintf(
            '[Waterfront\Infra\PleskClient\Services\HostingPackageClient]::changeServicePlanSwitchBetweenHostingType - Switching between serviceplans : %s and %s',
            $this->hostingParameters->getPackage(),
            self::PLANTOCHANGE,
        );

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage));

        $logMessage2 = 'Waterfront\Infra\PleskClient\Services\HostingPackageClient::create - Create new hosting';

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage2));

        $logMessage3 = 'Waterfront\Infra\PleskClient\Services\HostingPackageClient::create - New hosting created successfully';

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage3));

        $logMessage4 = sprintf(
            '[Waterfront\Infra\PleskClient\Services\HostingPackageClient]::changeServicePlanSwitchBetweenHostingType - Imported catchAll account with forward to : %s',
            $catchAllForward['destinationEmailAddress'],
        );

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage4));

        $logMessage5 = sprintf(
            '[Waterfront\Infra\PleskClient\Services\HostingPackageClient]::changeServicePlanSwitchBetweenHostingType - Importing %d forwarding email accounts',
            2,
        );

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage5));

        $logMessage6 = sprintf(
            '[Waterfront\Infra\PleskClient\Services\HostingPackageClient]::changeServicePlanSwitchBetweenHostingType - Emailaccount %s with forwading to %s is imported',
            $fowardedEmailInfo['sourceEmailAddressUsername'],
            json_encode($fowardedEmailInfo['destinationEmailAddresses']),
        );

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage6));

        $logMessage7 = sprintf(
            '[Waterfront\Infra\PleskClient\Services\HostingPackageClient]::changeServicePlanSwitchBetweenHostingType - Emailaccount %s with forwading to %s is imported',
            $fowardedEmailTesty['sourceEmailAddressUsername'],
            json_encode($fowardedEmailTesty['destinationEmailAddresses']),
        );

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage7));

        $this->hostingPackageClient->changeServicePlanSwitchBetweenHostingType(
            hostingParameters: $this->hostingParameters,
            domain: self::DOMAIN,
            servicePlanGuuid: self::PLANTOCHANGE,
        );

        //Get the new created hosting
        $result = $this->hostingPackageClient->getHostingSite($this->hostingParameters);
        $resultData = $result->getResponseBody();
        $hostingType = Arr::get($resultData, 'site.get.result.data.gen_info.htype');

        $siteId = Arr::get($resultData, 'site.get.result.id');
        assert(is_int($siteId) || is_string($siteId));
        $EmailGetAccountSettingsParameters = EmailGetAccountSettingsParameters::create([
            'siteId' => intval($siteId),
        ]);

        $responseResult = $this->hostingPackageClient->getExistingEmailAccounts($EmailGetAccountSettingsParameters);
        $emailAccountResultArray = $responseResult->getEmailAccounts();
        $catchAllResult = $responseResult->getCatchAllForward();

        self::assertSame('none', $hostingType);

        self::assertCount(2, $emailAccountResultArray);
        self::assertSame($fowardedEmailInfo['sourceEmailAddressUsername'], $emailAccountResultArray[0]->getMailName());

        /** @var array<int, string> $forwardDestinationAddresses */
        $forwardDestinationAddresses = $emailAccountResultArray[0]->getForwardDestinationAddresses();
        self::assertSame(
            $fowardedEmailInfo['destinationEmailAddresses'][0],
            $forwardDestinationAddresses[0],
        );

        self::assertSame($fowardedEmailTesty['sourceEmailAddressUsername'], $emailAccountResultArray[1]->getMailName());

        /** @var array<int, string> $forwardDestinationAddresses */
        $forwardDestinationAddresses = $emailAccountResultArray[1]->getForwardDestinationAddresses();
        self::assertSame(
            $fowardedEmailTesty['destinationEmailAddresses'][0],
            $forwardDestinationAddresses[0],
        );

        self::assertSame($catchAllResult, $catchAllForward['destinationEmailAddress']);
    }

    #[Test]
    public function changeServicePlanSwitchBetweenHostingTypeWithMultipleForwards(): void
    {
        //Create a customer + site
        $this->createCustomer();
        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $fowardedEmailInfo = [
            'sourceEmailAddressUsername' => 'info',
            'destinationEmailAddresses' => ['forwarded@info.nl', 'forwarded2@info.nl'],
        ];
        $this->createForwardMail($fowardedEmailInfo);

        $fowardedEmailTesty = [
            'sourceEmailAddressUsername' => 'testy',
            'destinationEmailAddresses' => ['forwarded@testy.nl'],
        ];
        $this->createForwardMail($fowardedEmailTesty);

        $catchAllForward = [
            'destinationEmailAddress' => 'forwarded@catchall.nl',
        ];

        $this->createCatchAllMail($catchAllForward);

        $logMessage = sprintf(
            '[Waterfront\Infra\PleskClient\Services\HostingPackageClient]::changeServicePlanSwitchBetweenHostingType - Switching between serviceplans : %s and %s',
            $this->hostingParameters->getPackage(),
            self::PLANTOCHANGE,
        );

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage));

        $logMessage2 = 'Waterfront\Infra\PleskClient\Services\HostingPackageClient::create - Create new hosting';

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage2));

        $logMessage3 = 'Waterfront\Infra\PleskClient\Services\HostingPackageClient::create - New hosting created successfully';

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage3));

        $logMessage4 = sprintf(
            '[Waterfront\Infra\PleskClient\Services\HostingPackageClient]::changeServicePlanSwitchBetweenHostingType - Imported catchAll account with forward to : %s',
            $catchAllForward['destinationEmailAddress'],
        );

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage4));

        $logMessage5 = sprintf(
            '[Waterfront\Infra\PleskClient\Services\HostingPackageClient]::changeServicePlanSwitchBetweenHostingType - Importing %d forwarding email accounts',
            2,
        );

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage5));

        $logMessage6 = sprintf(
            '[Waterfront\Infra\PleskClient\Services\HostingPackageClient]::changeServicePlanSwitchBetweenHostingType - Emailaccount %s with forwading to %s is imported',
            $fowardedEmailInfo['sourceEmailAddressUsername'],
            json_encode($fowardedEmailInfo['destinationEmailAddresses']),
        );

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage6));

        $logMessage7 = sprintf(
            '[Waterfront\Infra\PleskClient\Services\HostingPackageClient]::changeServicePlanSwitchBetweenHostingType - Emailaccount %s with forwading to %s is imported',
            $fowardedEmailTesty['sourceEmailAddressUsername'],
            json_encode($fowardedEmailTesty['destinationEmailAddresses']),
        );

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage7));

        $this->hostingPackageClient->changeServicePlanSwitchBetweenHostingType(
            hostingParameters: $this->hostingParameters,
            domain: self::DOMAIN,
            servicePlanGuuid: self::PLANTOCHANGE,
        );

        //Get the new created hosting
        $result = $this->hostingPackageClient->getHostingSite($this->hostingParameters);
        $resultData = $result->getResponseBody();
        $hostingType = Arr::get($resultData, 'site.get.result.data.gen_info.htype');

        $siteId = Arr::get($resultData, 'site.get.result.id');
        assert(is_int($siteId) || is_string($siteId));
        $EmailGetAccountSettingsParameters = EmailGetAccountSettingsParameters::create([
            'siteId' => intval($siteId),
        ]);

        $responseResult = $this->hostingPackageClient->getExistingEmailAccounts($EmailGetAccountSettingsParameters);
        $emailAccountResultArray = $responseResult->getEmailAccounts();
        $catchAllResult = $responseResult->getCatchAllForward();

        self::assertSame('none', $hostingType);

        self::assertCount(2, $emailAccountResultArray);
        self::assertSame($fowardedEmailInfo['sourceEmailAddressUsername'], $emailAccountResultArray[0]->getMailName());

        /** @var array<int, string> $forwardDestinationAddresses */
        $forwardDestinationAddresses = $emailAccountResultArray[0]->getForwardDestinationAddresses();
        self::assertSame(
            $fowardedEmailInfo['destinationEmailAddresses'][0],
            $forwardDestinationAddresses[0],
        );

        self::assertSame(
            $fowardedEmailInfo['destinationEmailAddresses'][1],
            $forwardDestinationAddresses[1],
        );

        self::assertSame($fowardedEmailTesty['sourceEmailAddressUsername'], $emailAccountResultArray[1]->getMailName());

        /** @var array<int, string> $forwardDestinationAddresses */
        $forwardDestinationAddresses = $emailAccountResultArray[1]->getForwardDestinationAddresses();
        self::assertSame(
            $fowardedEmailTesty['destinationEmailAddresses'][0],
            $forwardDestinationAddresses[0],
        );

        self::assertSame($catchAllResult, $catchAllForward['destinationEmailAddress']);
    }

    #[Test]
    public function changeServicePlanSwitchBetweenHostingTypeWithoutMails(): void
    {
        //Create a customer + site
        $this->createCustomer();
        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $logMessage = sprintf(
            '[Waterfront\Infra\PleskClient\Services\HostingPackageClient]::changeServicePlanSwitchBetweenHostingType - Switching between serviceplans : %s and %s',
            $this->hostingParameters->getPackage(),
            self::PLANTOCHANGE,
        );

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage));

        $logMessage2 = 'Waterfront\Infra\PleskClient\Services\HostingPackageClient::create - Create new hosting';

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage2));

        $logMessage3 = 'Waterfront\Infra\PleskClient\Services\HostingPackageClient::create - New hosting created successfully';

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage3));

        $logMessage4 = sprintf(
            '[Waterfront\Infra\PleskClient\Services\HostingPackageClient]::changeServicePlanSwitchBetweenHostingType - There was no catchAll set for domain %s',
            $this->hostingParameters->getDomain(),
        );

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage4));

        $this->hostingPackageClient->changeServicePlanSwitchBetweenHostingType(
            hostingParameters: $this->hostingParameters,
            domain: self::DOMAIN,
            servicePlanGuuid: self::PLANTOCHANGE,
        );

        //Get the new created hosting
        $result = $this->hostingPackageClient->getHostingSite($this->hostingParameters);
        $resultData = $result->getResponseBody();
        $hostingType = Arr::get($resultData, 'site.get.result.data.gen_info.htype');

        $siteId = Arr::get($resultData, 'site.get.result.id');
        assert(is_int($siteId) || is_string($siteId));
        $EmailGetAccountSettingsParameters = EmailGetAccountSettingsParameters::create([
            'siteId' => intval($siteId),
        ]);

        $responseResult = $this->hostingPackageClient->getExistingEmailAccounts($EmailGetAccountSettingsParameters);

        self::assertSame('none', $hostingType);

        self::assertEmpty($responseResult->getEmailAccounts());
        self::assertNull($responseResult->getCatchAllForward());
    }

    #[Test]
    public function customerDelete(): void
    {
        $this->createCustomer();

        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $deleteParameters = CustomerDeleteParameters::create([
            'customerId' => $this->hostingParameters->getCustomerId(),
        ]);

        $deleteResult = $this->customerClient->deleteCustomer($deleteParameters);

        self::assertSame(Result::STATUS_OK, $deleteResult->getStatus());
    }

    #[Test]
    public function deleteSite(): void
    {
        $this->createCustomer();

        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $websiteDeleteParameters = WebsiteDeleteParameters::create([
            'domain' => self::PARAMETERS['domain'],
        ]);

        $deleteResult = $this->hostingPackageClient->deleteWebsite($websiteDeleteParameters);
        self::assertSame(Result::STATUS_OK, $deleteResult->getStatus());
    }

    #[Test]
    public function deleteSiteUnknownDomain(): void
    {
        $this->createCustomer();

        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $websiteDeleteParameters = WebsiteDeleteParameters::create([
            'domain' => 'unknowndomain.nl',
        ]);

        $deleteResult = $this->hostingPackageClient->deleteWebsite($websiteDeleteParameters);
        self::assertSame(Result::STATUS_ERROR, $deleteResult->getStatus());
    }

    #[Test]
    public function getHostingSiteSuccess(): void
    {
        $this->createCustomer();

        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $result = $this->hostingPackageClient->getHostingSite($this->hostingParameters);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
        $resultData = $result->getResponseBody();
        self::assertSame(
            $this->hostingParameters->getDomain(),
            Arr::get($resultData, 'site.get.result.data.gen_info.name'),
        );
    }

    #[Test]
    public function getHostingSiteUnknownDomain(): void
    {
        $this->createCustomer();

        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $requestParameters = $this->hostingParameters;
        $requestParameters->setDomain('unknowndomain.nl');
        $result = $this->hostingPackageClient->getHostingSite($requestParameters);

        self::assertSame(Result::STATUS_ERROR, $result->getStatus());
        self::assertSame('Site does not exist', $result->getErrorMessage());
    }

    #[Test]
    public function fetchCustomerSuccess(): void
    {
        $this->createCustomer();

        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $result = $this->customerClient->fetchCustomer($this->hostingParameters);
        self::assertSame(Result::STATUS_OK, $result->getStatus());
        $resultData = $result->getResponseBody();
        self::assertEquals($this->hostingParameters->getCustomerId(), Arr::get($resultData, 'site.get.result.id'));
    }

    #[Test]
    public function fetchCustomerUnknownId(): void
    {
        $createCustomerResult = $this->createCustomer();

        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $requestParameters = $this->hostingParameters;
        $requestParameters->setCustomerId('1908');
        $result = $this->customerClient->fetchCustomer($requestParameters);
        self::assertSame(Result::STATUS_ERROR, $result->getStatus());
        self::assertSame('Client does not exist', $result->getErrorMessage());

        $this->hostingParameters->setCustomerId($createCustomerResult->getCustomerId());
    }

    #[Test]
    public function getEmailAccountSettings(): void
    {
        $this->createCustomer();

        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $fowardedEmailInfo = [
            'sourceEmailAddressUsername' => 'info',
            'destinationEmailAddresses' => ['forwarded@info.nl'],
        ];
        $this->createForwardMail($fowardedEmailInfo);

        $fowardedEmailTesty = [
            'sourceEmailAddressUsername' => 'testy',
            'destinationEmailAddresses' => ['forwarded@testy.nl'],
        ];
        $this->createForwardMail($fowardedEmailTesty);

        $catchAllForward = [
            'destinationEmailAddress' => 'forwarded@catchall.nl',
        ];
        $this->createCatchAllMail($catchAllForward);

        $result = $this->hostingPackageClient->getHostingSite($this->hostingParameters);
        $resultData = $result->getResponseBody();

        $siteId = Arr::get($resultData, 'site.get.result.id');
        assert(is_int($siteId) || is_string($siteId));
        $EmailGetAccountSettingsParameters = EmailGetAccountSettingsParameters::create([
            'siteId' => intval($siteId),
        ]);

        $responseResult = $this->hostingPackageClient->getExistingEmailAccounts($EmailGetAccountSettingsParameters);

        $emailAccountResultArray = $responseResult->getEmailAccounts();
        $catchAllResult = $responseResult->getCatchAllForward();

        self::assertSame($fowardedEmailInfo['sourceEmailAddressUsername'], $emailAccountResultArray[0]->getMailName());

        /** @var array<int, string> $forwardDestinationAddresses */
        $forwardDestinationAddresses = $emailAccountResultArray[0]->getForwardDestinationAddresses();
        self::assertSame(
            $fowardedEmailInfo['destinationEmailAddresses'][0],
            $forwardDestinationAddresses[0],
        );

        self::assertSame($fowardedEmailTesty['sourceEmailAddressUsername'], $emailAccountResultArray[1]->getMailName());

        /** @var array<int, string> $forwardDestinationAddresses */
        $forwardDestinationAddresses = $emailAccountResultArray[1]->getForwardDestinationAddresses();
        self::assertSame(
            $fowardedEmailTesty['destinationEmailAddresses'][0],
            $forwardDestinationAddresses[0],
        );

        self::assertSame($catchAllResult, $catchAllForward['destinationEmailAddress']);
    }

    #[Test]
    public function getEmailAccountSettingsMultipleForwards(): void
    {
        $this->createCustomer();

        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $fowardedEmailInfo = [
            'sourceEmailAddressUsername' => 'info',
            'destinationEmailAddresses' => ['forwarded@sandwave.io', 'forward2@sandwave.io'],
        ];

        $this->createForwardMail($fowardedEmailInfo);

        $fowardedEmailTesty = [
            'sourceEmailAddressUsername' => 'testy',
            'destinationEmailAddresses' => ['forwarded@testy.nl'],
        ];
        $this->createForwardMail($fowardedEmailTesty);

        $catchAllForward = [
            'destinationEmailAddress' => 'forwarded@catchall.nl',
        ];
        $this->createCatchAllMail($catchAllForward);

        $result = $this->hostingPackageClient->getHostingSite($this->hostingParameters);
        $resultData = $result->getResponseBody();

        $siteId = Arr::get($resultData, 'site.get.result.id');
        assert(is_int($siteId) || is_string($siteId));
        $EmailGetAccountSettingsParameters = EmailGetAccountSettingsParameters::create([
            'siteId' => intval($siteId),
        ]);

        $responseResult = $this->hostingPackageClient->getExistingEmailAccounts($EmailGetAccountSettingsParameters);

        $emailAccountResultArray = $responseResult->getEmailAccounts();
        $catchAllResult = $responseResult->getCatchAllForward();

        self::assertSame($fowardedEmailInfo['sourceEmailAddressUsername'], $emailAccountResultArray[0]->getMailName());

        /** @var array<int, string> $forwardDestinationAddresses */
        $forwardDestinationAddresses = $emailAccountResultArray[0]->getForwardDestinationAddresses();
        self::assertSame(
            $fowardedEmailInfo['destinationEmailAddresses'][0],
            $forwardDestinationAddresses[0],
        );

        self::assertSame(
            $fowardedEmailInfo['destinationEmailAddresses'][1],
            $forwardDestinationAddresses[1],
        );

        self::assertSame($fowardedEmailTesty['sourceEmailAddressUsername'], $emailAccountResultArray[1]->getMailName());

        /** @var array<int, string> $forwardDestinationAddresses */
        $forwardDestinationAddresses = $emailAccountResultArray[1]->getForwardDestinationAddresses();
        self::assertSame(
            $fowardedEmailTesty['destinationEmailAddresses'][0],
            $forwardDestinationAddresses[0],
        );

        self::assertSame($catchAllResult, $catchAllForward['destinationEmailAddress']);
    }

    #[Test]
    public function getEmailAccountSettingsWithoutEmails(): void
    {
        $this->createCustomer();

        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $result = $this->hostingPackageClient->getHostingSite($this->hostingParameters);
        $resultData = $result->getResponseBody();

        $siteId = Arr::get($resultData, 'site.get.result.id');
        assert(is_int($siteId) || is_string($siteId));
        $EmailGetAccountSettingsParameters = EmailGetAccountSettingsParameters::create([
            'siteId' => intval($siteId),
        ]);

        $responseResult = $this->hostingPackageClient->getExistingEmailAccounts($EmailGetAccountSettingsParameters);

        self::assertEmpty($responseResult->getEmailAccounts());
        self::assertNull($responseResult->getCatchAllForward());
    }

    #[Test]
    public function getEmailAccountSettingsFailedUnknownSitId(): void
    {
        $EmailGetAccountSettingsParameters = EmailGetAccountSettingsParameters::create([
            'siteId' => 1908,
        ]);

        $this->expectException(PleskClientException::class);
        $this->expectExceptionMessageIs('[Error code : 1015]:: Api message : Domain does not exist.');

        $this->hostingPackageClient->getExistingEmailAccounts($EmailGetAccountSettingsParameters);
    }

    /**
     * This test if a servecplan is changeble :
     * - In this scenario it doesn't because we try from a hosting to a non hosting serviceplan.
     */
    #[Test]
    public function isServicePlanChangeableFalse(): void
    {
        $this->createCustomer();

        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $result = $this->hostingPackageClient->isServicePlanChangeable(self::DOMAIN, self::PLANTOCHANGE);
        self::assertFalse($result);
    }

    /**
     * This test if a servecplan is changeble :
     * - In this scenario it does because we try from a hosting to a hosting servicplan.
     */
    #[Test]
    public function isServicePlanChangeableTrue(): void
    {
        $customerResult = $this->customerClient->createCustomer($this->hostingParameters);

        $this->hostingParameters->setCustomerId($customerResult->getCustomerId());
        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $result = $this->hostingPackageClient->isServicePlanChangeable(self::DOMAIN, self::PLANTOCHANGE);
        self::assertTrue($result);
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function xXCreateAccount(): void
    {
        $this->hostingParameters->setPackage(self::UPGRADEPLAN);
        //Create a customer + site
        $this->createCustomer();
        $this->hostingPackageClient->createHosting($this->hostingParameters);

        $fowardedEmailInfo = [
            'sourceEmailAddressUsername' => 'info',
            'destinationEmailAddress' => 'forwarded@info.nl',
        ];
        $this->createForwardMail($fowardedEmailInfo);

        $fowardedEmailTesty = [
            'sourceEmailAddressUsername' => 'testy',
            'destinationEmailAddress' => 'forwarded@testy.nl',
        ];
        $this->createForwardMail($fowardedEmailTesty);

        $catchAllForward = [
            'destinationEmailAddress' => 'forwarded@catchall.nl',
        ];

        $this->createCatchAllMail($catchAllForward);
    }

    private function updateConfig(): void
    {
        // TODO figure out a way to update the config before the provider is loaded
        // Or reload the service provider once we set it to false
        // So we don't need to update the .env.testing before/after running this test
        // Config::set('hosting-service-client.connection.use_faker', false);
        Config::set('hosting-service-client.connection.verify_ssl', false);

        // Remove settings not available on the local Plesk container
        $productSpecsConfig = Config::get('product-specs.hosting');
        foreach (self::FORGET_HOSTING_SETTINGS as $key) {
            Arr::forget($productSpecsConfig, $key);
        }

        Config::set('product-specs.hosting', $productSpecsConfig);
    }

    private function setupClients(): void
    {
        $this->customerClient = self::resolve(CustomerClient::class);
        $this->hostingPackageClient = self::resolve(HostingPackageClient::class);

        $server = Server::where('hostname', 'localhost')->firstOrFail();

        $credentials = [
            'username' => $server->getUsername(),
            'password' => $server->getPassword(),
        ];

        $this->customerClient->setServer($server, $credentials);
        $this->hostingPackageClient->setServer($server, $credentials);
    }

    private function createCustomer(): CustomerCreateResult
    {
        $customerResult = $this->customerClient->createCustomer($this->hostingParameters);
        $this->hostingParameters->setCustomerId($customerResult->getCustomerId());

        return $customerResult;
    }

    /**
     * @param array<string,string> $data
     */
    private function createCatchAllMail(array $data): void
    {
        $emailCatchAllParameters = EmailSetCatchAllParameters::create([
            'domain' => $this->hostingParameters->getDomain(),
            'destinationEmailAddress' => $data['destinationEmailAddress'],
        ]);

        $this->hostingPackageClient->setEmailCatchAll($emailCatchAllParameters);
    }

    /**
     * @param array<string,string|array<int,string>> $data
     */
    private function createForwardMail(array $data): void
    {
        $emailForwardParameters = EmailForwardingCreateParameters::create([
            'domain' => $this->hostingParameters->getDomain(),
            'sourceEmailAddressUsername' => $data['sourceEmailAddressUsername'],
            'destinationEmailAddresses' => $data['destinationEmailAddresses'],
        ]);

        $this->hostingPackageClient->createEmailForward($emailForwardParameters);
    }
}
