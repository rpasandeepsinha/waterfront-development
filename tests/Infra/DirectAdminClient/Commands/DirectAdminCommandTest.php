<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Tests\Infra\DirectAdminClient\Mock\MockCommand;

#[CoversClass(MockCommand::class)]
class DirectAdminCommandTest extends DirectAdminTestCase
{
    #[Test]
    public function a_url_encoded_string_can_be_decoded_to_array(): void
    {
        $cmd = new MockCommand();

        $str = 'list[]=value1&list[]=value2&list[]=value3&list[]=value4';
        $str .= '&listother[]=value1&listother[]=value2&listother[]=value3&listother[]=value4';

        $expect = [
            'list' => [
                'value1',
                'value2',
                'value3',
                'value4',
            ],
            'listother' => [
                'value1',
                'value2',
                'value3',
                'value4',
            ],
        ];

        self::assertSame($expect, $cmd->decodeUrlEncodedString($str));
    }
}
