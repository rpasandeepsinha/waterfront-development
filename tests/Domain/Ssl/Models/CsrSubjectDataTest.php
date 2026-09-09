<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl\Models;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\Ssl\Models\CsrSubjectData;

#[CoversClass(CsrSubjectData::class)]
class CsrSubjectDataTest extends TestCase
{
    #[Test]
    public function constructor(): void
    {
        $customerData = [
            'name'       => 'Luigi',
            'organization' => 'Mario World ORG',
            'address'    => [
                'city'         => 'Toad Town',
                'province'     => 'Mushroom Kingdom',
                'country_code' => 'MUSH',
            ],
            'department' => '?/! Blocks',
        ];
        $testItem = CsrSubjectData::createFromCustomerData($customerData, 'my-domain.nl');
        self::assertSame('Toad Town', $testItem->getCity());
        self::assertSame('Mushroom Kingdom', $testItem->getProvince());
        self::assertSame(
            '/C=MUSH/ST=Mushroom Kingdom/L=Toad Town/O=Luigi/OU=?! Blocks/CN=my-domain.nl',
            $testItem->__toString()
        );
    }

    #[Test]
    public function noProvinceEntered(): void
    {
        $customerData = [
            'name'       => 'Luigi',
            'organization' => 'Mario World ORG',
            'address'    => [
                'city'         => 'Toad Town',
                'country_code' => 'MUSH',
            ],
            'department' => '?-Blocks',
        ];
        $testItem = CsrSubjectData::createFromCustomerData($customerData, 'my-domain.nl');
        self::assertSame('Toad Town', $testItem->getCity());
        self::assertSame('Toad Town', $testItem->getProvince());
    }
}
