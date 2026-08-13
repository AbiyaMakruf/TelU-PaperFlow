<?php

namespace Tests\Unit;

use App\Services\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_it_parses_various_phone_number_formats_to_international_format(): void
    {
        $testCases = [
            '082123944448' => '+6282123944448',
            '081283887102' => '+6281283887102',
            '6282268215563' => '+6282268215563',
            '+6282252000699' => '+6282252000699',
            '+62 8951 4294 050' => '+6289514294050',
            '0817-7413-2507' => '+6281774132507',
            '+62 821-1707-7750' => '+6282117077750',
            '81283997495' => '+6281283997495',
            '' => null,
            '-' => null,
        ];

        foreach ($testCases as $input => $expected) {
            $this->assertEquals($expected, PhoneNumber::parse($input), "Failed parsing for input: {$input}");
        }
    }
}
