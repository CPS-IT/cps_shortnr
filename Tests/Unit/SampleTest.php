<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Tests\Unit;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SampleTest extends TestCase
{
    #[DataProvider('sampleDataProvider')]
    #[DataProvider('sampleDataProviderFail')]
    public function testUInt(bool $fail, int $a, int $b, int $c)
    {
        $this->assertSame($fail, ($a < 0));
        $this->assertSame($fail, ($b < 0));
        $this->assertSame($fail, ($c < 0));
    }

    public static function sampleDataProvider(): Generator
    {
        yield 'dataProviderTestDataName' => ['fail' => false, 1, 2, 3];
        yield 'dataProviderTestDataName-advanced' => ['fail' => false, 19, 332, 422];
    }

    public static function sampleDataProviderFail(): Generator
    {
        yield 'dataProviderTestDataName-fail' => ['fail' => true,-1, -2, -3];
        yield 'dataProviderTestDataName-fail-advanced' => ['fail' => true, -19, -332, -422];
    }
}
