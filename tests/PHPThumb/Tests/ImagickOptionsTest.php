<?php
namespace PHPThumb\Tests;

use PHPThumb\Imagick;
use PHPUnit\Framework\TestCase;

class ImagickOptionsTest extends TestCase
{
    protected Imagick $thumb;

    protected function setUp(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('ext-imagick is not available');
        }

        $this->thumb = new Imagick(__DIR__ . '/../../resources/test.jpg');
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
