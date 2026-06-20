<?php
namespace PHPThumb\Tests;

use PHPThumb\GD;
use PHPUnit\Framework\TestCase;

class GDOptionsTest extends TestCase
{
    protected GD $thumb;

    protected function setUp(): void
    {
        $this->thumb = new GD(__DIR__ . '/../../resources/test.jpg');
    }

    public function testDefaultOptionsIncludeNewKeys(): void
    {
        $opts = $this->thumb->getOptions();
        self::assertArrayHasKey('sharpenAmount',   $opts);
        self::assertArrayHasKey('textFont',        $opts);
        self::assertArrayHasKey('textDefaultSize', $opts);
        self::assertSame(50,  $opts['sharpenAmount']);
        self::assertSame(12,  $opts['textDefaultSize']);
        self::assertNull($opts['textFont']);
    }
}
