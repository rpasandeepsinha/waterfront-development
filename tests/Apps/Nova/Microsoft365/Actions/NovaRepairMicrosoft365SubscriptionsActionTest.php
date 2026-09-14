<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Microsoft365\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Microsoft365\Actions\NovaMicrosoft365BulkStatusUpdateAction;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaMicrosoft365BulkStatusUpdateAction::class)]
class NovaRepairMicrosoft365SubscriptionsActionTest extends IntegrationTestCase
{
    private Subscription $parentSubscription;

    private Microsoft365CustomerInfo $microsoft365CustomerInfo;

    /** @var Collection<int, Microsoft365Deployment> */
    private Collection $actionCollection;

    private NovaMicrosoft365BulkStatusUpdateAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $microsoft365ProductGroup = new ProductGroupFactory()->microsoft365()->createOne();

        $parentProductOne = new ProductFactory()->for($microsoft365ProductGroup)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);
        $childProductOne = new ProductFactory()->for($microsoft365ProductGroup)->createOne([
            'slug' => 'microsoft-business-standard',
        ]);

        $this->parentSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->technicalStatusOk()
            ->createOne([
                'product_uuid' => $parentProductOne->uuid,
            ]);

        new SubscriptionFactory()
            ->withCustomer()
            ->count(5)
            ->parentSubscription($this->parentSubscription)
            ->technicalStatusOk()
            ->create([
                'product_uuid' => $childProductOne->uuid,
            ]);

        $this->microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for(
            new CustomerFactory()->createOne(),
        )->createOne();
        $microsoft365Deployment = new Microsoft365DeploymentFactory()
            ->for($this->parentSubscription)
            ->for($this->microsoft365CustomerInfo)
            ->createOne();

        $this->actionCollection = new Collection([$microsoft365Deployment]);

        $this->action = new NovaMicrosoft365BulkStatusUpdateAction(
            self::resolve(TranslatorInterface::class),
        );
    }

    #[Test]
    public function repairAllSubscriptions(): void
    {
        $actionFields = $this->getFields([
            'from_administrative_status' => AdministrativeStatus::ACTIVE->value,
            'to_administrative_status' => AdministrativeStatus::ARCHIVED->value,
            'from_technical_status' => TechnicalStatus::OK->value,
            'to_technical_status' => TechnicalStatus::DELETED->value,
        ]);

        $actionResponse = $this->action->handle($actionFields, $this->actionCollection);
        self::assertInstanceOf(ActionResponse::class, $actionResponse);

        $this->parentSubscription->refresh();
        self::assertCount(0, $this->parentSubscription->children->where(
            'administrative_status',
            AdministrativeStatus::ACTIVE->value,
        ));
        self::assertCount(5, $this->parentSubscription->children->where(
            'administrative_status',
            AdministrativeStatus::ARCHIVED->value,
        ));
        self::assertCount(0, $this->parentSubscription->children->where(
            'technical_status',
            TechnicalStatus::OK->value,
        ));
        self::assertCount(5, $this->parentSubscription->children->where(
            'technical_status',
            TechnicalStatus::DELETED->value,
        ));
    }

    #[Test]
    public function repairPartialSubscriptions(): void
    {
        $firstChild = $this->parentSubscription->children->firstOrFail();
        $firstChild->administrative_status = AdministrativeStatus::CANCELED->value;
        $firstChild->save();

        $actionFields = $this->getFields([
            'from_administrative_status' => AdministrativeStatus::ACTIVE->value,
            'to_administrative_status' => AdministrativeStatus::ARCHIVED->value,
            'from_technical_status' => TechnicalStatus::OK->value,
            'to_technical_status' => TechnicalStatus::DELETED->value,
        ]);

        $actionResponse = $this->action->handle($actionFields, $this->actionCollection);
        self::assertInstanceOf(ActionResponse::class, $actionResponse);

        $this->parentSubscription->refresh();
        self::assertCount(0, $this->parentSubscription->children->where(
            'administrative_status',
            AdministrativeStatus::ACTIVE->value,
        ));
        self::assertCount(1, $this->parentSubscription->children->where(
            'administrative_status',
            AdministrativeStatus::CANCELED->value,
        ));
        self::assertCount(4, $this->parentSubscription->children->where(
            'administrative_status',
            AdministrativeStatus::ARCHIVED->value,
        ));
        self::assertCount(1, $this->parentSubscription->children->where(
            'technical_status',
            TechnicalStatus::OK->value,
        ));
        self::assertCount(4, $this->parentSubscription->children->where(
            'technical_status',
            TechnicalStatus::DELETED->value,
        ));
    }

    #[Test]
    public function mcaNotSigned(): void
    {
        $this->microsoft365CustomerInfo->mca_signed_at = null;
        $this->microsoft365CustomerInfo->save();

        $actionFields = $this->getFields([
            'from_administrative_status' => AdministrativeStatus::ACTIVE->value,
            'to_administrative_status' => AdministrativeStatus::ARCHIVED->value,
            'from_technical_status' => TechnicalStatus::OK->value,
            'to_technical_status' => TechnicalStatus::DELETED->value,
        ]);

        $actionResponse = $this->action->handle($actionFields, $this->actionCollection);

        self::assertInstanceOf(ActionResponse::class, $actionResponse);
        $message = $actionResponse['danger'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.failed.microsoft365-mca-not-signed', $message->text);
    }

    /**
     * @param array<string, bool|null|string> $payload
     */
    private function getFields(array $payload): ActionFields
    {
        return new ActionFields(new Collection($payload), new Collection([]));
    }
}
