<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Traits;

use Illuminate\Container\Container;
use libphonenumber\NumberParseException;
use Waterfront\Domain\Customers\DTO\PhoneDTO;
use Waterfront\Infra\Translation\TranslatorInterface;

trait HasPhoneNumber
{
    public function getPhoneNumberAttribute(): string
    {
        if (
            $this->phone_country_code !== ''
            && $this->phone_area_code !== ''
            && $this->phone_subscriber_number !== ''
        ) {
            return (
                '(+' . $this->phone_country_code . ') ' . $this->phone_area_code . '-' . $this->phone_subscriber_number
            );
        }

        return '';
    }

    /**
     * @throws NumberParseException
     */
    public function setPhoneNumberAttribute(string $value): void
    {
        try {
            $phone = new PhoneDTO($value);
            $this->phone_country_code = $phone->getCountryCode();
            $this->phone_area_code = $phone->getAreaCode();
            $this->phone_subscriber_number = $phone->getNumber();
        } catch (NumberParseException $exception) {
            /** @var TranslatorInterface $translator */
            $translator = Container::getInstance()->make(TranslatorInterface::class);

            throw new NumberParseException(
                NumberParseException::NOT_A_NUMBER,
                $translator->translate('customer.phone-country-error'),
                $exception,
            );
        }
    }
}
