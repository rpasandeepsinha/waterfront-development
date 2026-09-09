<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Scenarios;

use Carbon\CarbonImmutable;
use Database\Seeders\Products\ProductReference;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Orders\Enums\OrderLineItemStatus;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Payments\Enums\PaymentMethod;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Voucher\Enum\VoucherAmountType;
use Waterfront\Domain\Voucher\Models\Voucher;
use Waterfront\Domain\Voucher\Models\VoucherClaim;

class VoucherSeeder extends Seeder
{
    public function __construct(private readonly ReferenceRepository $referenceRepo)
    {
    }

    public function run(): void
    {
        $customer = $this->customer();

        $this->unlimitedClaimsFixedAmount($customer);
        $this->unlimitedClaimsPercentageAmount($customer);
        $this->noClaimsLeft($customer);
        $this->alreadyClaimedByCustomer($customer);
        $this->expired();
    }

    private function unlimitedClaimsFixedAmount(Customer $customer): void
    {
        $voucher = $this->referenceRepo->get(ProductReference::VPS_CLOUD_20_VOUCHER, Voucher::class);
        $product = $this->referenceRepo->get(ProductReference::VPS_CLOUD_20, Product::class);
        $price = $this->referenceRepo->get(ProductReference::VPS_CLOUD_20_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Str::uuid()->toString();
        $order->status = OrderStatus::IN_PROGRESS;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price - $voucher->amount;
        $order->customer_id = $customer->id;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->save();

        $orderItem = new OrderLineItem();
        $orderItem->order_id = $order->id;
        $orderItem->product_uuid = $product->uuid;
        $orderItem->product_name = $product->name;
        $orderItem->status = OrderLineItemStatus::REGISTRATION;
        $orderItem->gross_price = $price->price;
        $orderItem->net_price = $price->price - $voucher->amount;
        $orderItem->billing_period = $price->billing_period;
        $orderItem->contract_period = $price->contract_period;
        $orderItem->should_invoice = true;
        $orderItem->save();

        $claim = new VoucherClaim();
        $claim->amount_claimed = $voucher->amount;
        $claim->order_line_item_id = $orderItem->id;
        $claim->voucher_id = $voucher->id;
        $claim->save();
    }

    private function unlimitedClaimsPercentageAmount(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::DOMAIN_BE, Product::class);
        $price = $this->referenceRepo->get(ProductReference::DOMAIN_BE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $voucher = new Voucher();
        $voucher->uuid = Str::uuid()->toString();
        $voucher->amount_type = VoucherAmountType::PERCENTAGE;
        $voucher->amount = 10;
        $voucher->product_group_uuid = $product->productGroup->uuid;
        $voucher->product_uuid = $product->uuid;
        $voucher->internal_name = 'Domain BE 10% discount';
        $voucher->display_name = '10% off!';
        $voucher->description = '.be domains are 10% off until further notice';
        $voucher->code = 'greatdiscountforbelgians';
        $voucher->apply_with_discount = false;
        $voucher->allow_multiple_claims_same_customer = true;
        $voucher->save();

        $order = new Order();
        $order->uuid = Str::uuid()->toString();
        $order->status = OrderStatus::IN_PROGRESS;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = intval($price->price - $voucher->amount / 100 * $price->price);
        $order->customer_id = $customer->id;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->save();

        $orderItem = new OrderLineItem();
        $orderItem->order_id = $order->id;
        $orderItem->product_uuid = $product->uuid;
        $orderItem->product_name = $product->name;
        $orderItem->status = OrderLineItemStatus::REGISTRATION;
        $orderItem->gross_price = $price->price;
        $orderItem->net_price = intval($price->price - $voucher->amount / 100 * $price->price);
        $orderItem->billing_period = $price->billing_period;
        $orderItem->contract_period = $price->contract_period;
        $orderItem->should_invoice = true;
        $orderItem->save();

        $claim = new VoucherClaim();
        $claim->amount_claimed = $voucher->amount;
        $claim->order_line_item_id = $orderItem->id;
        $claim->voucher_id = $voucher->id;
        $claim->save();
    }

    private function noClaimsLeft(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::DOMAIN_BE, Product::class);
        $price = $this->referenceRepo->get(ProductReference::DOMAIN_BE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $voucher = new Voucher();
        $voucher->uuid = Str::uuid()->toString();
        $voucher->amount_type = VoucherAmountType::FIXED;
        $voucher->amount = 99;
        $voucher->product_group_uuid = $product->productGroup->uuid;
        $voucher->product_uuid = $product->uuid;
        $voucher->internal_name = 'Domain BE 99 cents off for single customer';
        $voucher->display_name = '.be 99 cents off';
        $voucher->description = 'Customer compensation for long phone waiting lines';
        $voucher->code = 'iamacustomernumber';
        $voucher->apply_with_discount = false;
        $voucher->allow_multiple_claims_same_customer = true;
        $voucher->max_claims = 0;
        $voucher->save();

        $order = new Order();
        $order->uuid = Str::uuid()->toString();
        $order->status = OrderStatus::IN_PROGRESS;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price - $voucher->amount;
        $order->customer_id = $customer->id;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->save();

        $orderItem = new OrderLineItem();
        $orderItem->order_id = $order->id;
        $orderItem->product_uuid = $product->uuid;
        $orderItem->product_name = $product->name;
        $orderItem->status = OrderLineItemStatus::REGISTRATION;
        $orderItem->gross_price = $price->price;
        $orderItem->net_price = $price->price - $voucher->amount;
        $orderItem->billing_period = $price->billing_period;
        $orderItem->contract_period = $price->contract_period;
        $orderItem->should_invoice = true;
        $orderItem->save();

        $claim = new VoucherClaim();
        $claim->amount_claimed = $voucher->amount;
        $claim->order_line_item_id = $orderItem->id;
        $claim->voucher_id = $voucher->id;
        $claim->save();
    }

    private function alreadyClaimedByCustomer(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::DOMAIN_BE, Product::class);
        $price = $this->referenceRepo->get(ProductReference::DOMAIN_BE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $voucher = new Voucher();
        $voucher->uuid = Str::uuid()->toString();
        $voucher->amount_type = VoucherAmountType::FIXED;
        $voucher->amount = 99;
        $voucher->product_group_uuid = $product->productGroup->uuid;
        $voucher->product_uuid = $product->uuid;
        $voucher->internal_name = 'Domain BE 99 cents off for single customer';
        $voucher->display_name = '.be 99c cents off';
        $voucher->description = 'Customer compensation for long phone waiting lines';
        $voucher->code = 'iamacustomernumber';
        $voucher->apply_with_discount = false;
        $voucher->allow_multiple_claims_same_customer = false;
        $voucher->save();

        $order = new Order();
        $order->uuid = Str::uuid()->toString();
        $order->status = OrderStatus::IN_PROGRESS;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price - $voucher->amount;
        $order->customer_id = $customer->id;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->save();

        $orderItem = new OrderLineItem();
        $orderItem->order_id = $order->id;
        $orderItem->product_uuid = $product->uuid;
        $orderItem->product_name = $product->name;
        $orderItem->status = OrderLineItemStatus::REGISTRATION;
        $orderItem->gross_price = $price->price;
        $orderItem->net_price = $price->price - $voucher->amount;
        $orderItem->billing_period = $price->billing_period;
        $orderItem->contract_period = $price->contract_period;
        $orderItem->should_invoice = true;
        $orderItem->save();

        $claim = new VoucherClaim();
        $claim->amount_claimed = $voucher->amount;
        $claim->order_line_item_id = $orderItem->id;
        $claim->voucher_id = $voucher->id;
        $claim->save();
    }

    private function expired(): void
    {
        $product = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);

        $voucher = new Voucher();
        $voucher->uuid = Str::uuid()->toString();
        $voucher->amount_type = VoucherAmountType::FIXED;
        $voucher->amount = 43;
        $voucher->product_group_uuid = $product->productGroup->uuid;
        $voucher->product_uuid = $product->uuid;
        $voucher->internal_name = '.nl customer compensation voucher';
        $voucher->display_name = '.nl 43 cents off';
        $voucher->description = 'Customer compensation';
        $voucher->code = 'iamanexpiredtest';
        $voucher->apply_with_discount = false;
        $voucher->allow_multiple_claims_same_customer = true;
        $voucher->expiration_date = CarbonImmutable::yesterday();
        $voucher->save();
    }

    private function customer(): Customer
    {
        $customer = new Customer();
        $customer->uuid = Str::uuid();
        $customer->first_name = 'Voucher';
        $customer->last_name = 'Scenario';
        $customer->email = 'vouchers@yourhosting.nl';
        $customer->gender = Gender::MALE->value;
        $customer->phone_country_code = '31';
        $customer->phone_area_code = '6';
        $customer->phone_subscriber_number = '12345678';
        $customer->locale = Locale::DUTCH->value;
        $customer->payment_type = PaymentType::CREDIT;
        $customer->has_direct_debit = true;
        $customer->customer_since = CarbonImmutable::now();
        $customer->save();

        $address = new CustomerAddress();
        $address->customer_id = $customer->id;
        $address->street_name = 'Edisonweg';
        $address->street_number = '51';
        $address->street_number_addition = 'D';
        $address->zip_code = '4388 AG';
        $address->city = 'Vlissingen';
        $address->country_code = 'NL';
        $address->save();

        return $customer;
    }
}
