<?php

declare(strict_types=1);

namespace Waterfront\Support\Helpers\ValidationRules;

use Illuminate\Container\Container;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class ReservedBankAccounts extends AbstractValidator
{
    protected function passes(string $attribute, mixed $value): bool
    {
        return ! in_array($value, $this->getReservedAccounts(), true);
    }

    protected function message(): string
    {
        /** @var TranslatorInterface $translator */
        $translator = Container::getInstance()->make(TranslatorInterface::class);

        return $translator->translate('validation.iban_number');
    }

    /**
     * @return string[]
     */
    private function getReservedAccounts(): array
    {
        /**
         * These are all accounts of our own BU's and should not be able to be used.
         */
        return [
            'NL02RABO0318528134', // Your Hosting B.V.
            'NL77RABO0129089567', // Your Hosting B.V.
            'NL79RABO0142657891', // SoHosted Webhosting
            'NL75RABO0375102361', // Versio
            'NL27RABO0129284564', // Your Hosting B.V.
            'NL58RABO0370609239', // Argeweb
            'NL80RABO0129284580', // Versio
            'NL24RABO0318528223', // Versio
            'NL37RABO0136562876', // Your Hosting B.V.
            'NL15RABO0157890856', // Provider
            'NL02RABO0129284432', // Your Hosting B.V.
            'NL66RABO0143661299', // Versio
            'NL89RABO0321626591', // Vevida
            'NL50RABO0157979369', // Versio
            'NL89RABO0134917693', // SoHosted Webhosting
            'NL12INGB0681068523', // Argeweb B.V.
            'NL90INGB0007249441', // Integrated Internet Services B.V.
            'NL79INGB0007289700', // Realhosting
            'NL13INGB0005562833', // QDC
            'NL71INGB0004736998', // Versio B.V.
            'NL98ABNA0548279268', // Your Hosting B.V.
            'NL90ABNA0976752662', // Your Hosting B.V.
            'NL34ABNA0543250776', // Your Hosting B.V.
            'NL75ABNA0598569138', // Your Hosting B.V.
            'NL22ABNA0415440203', // Your Hosting B.V.
            'NL08ABNA0493523030', // Your Hosting B.V.
            'NL14ABNA0492712875', // Your Hosting B.V.
        ];
    }
}
