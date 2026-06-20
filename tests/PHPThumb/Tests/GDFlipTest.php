<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\GD;
use PHPUnit\Framework\TestCase;

class GDFlipTest extends TestCase
{
    protected GD $thumb;

    protected function setUp(): void
    {
        $this->thumb = new GD(__DIR__ . '/../../resources/test.jpg');
    }

    public function testFlipHorizontalReturnsSelf(): void
    {
        $result = $this->thumb->flip('horizontal');
        self::assertInstanceOf(GD::class, $result);
    }

    public function testFlipHorizontalChangesPixels(): void
    {
        $before = $this->thumb->getOldImage();
        $w = imagesx($before);

        $rgbLeft  = imagecolorat($before, 5, imagesy($before) / 2);
        $rgbRight = imagecolorat($before, $w - 5, imagesy($before) / 2);

        $this->thumb->flip('horizontal');

        $after = $this->thumb->getOldImage();
        $rgbLeftAfter  = imagecolorat($after, 5, imagesy($after) / 2);
        $rgbRightAfter = imagecolorat($after, $w - 5, imagesy($after) / 2);

        self::assertSame($rgbLeft,  $rgbRightAfter);
        self::assertSame($rgbRight, $rgbLeftAfter);
    }

    public function testFlipVerticalSwapsRows(): void
    {
        $before = $this->thumb->getOldImage();
        $h = imagesy($before);

        $rgbTop    = imagecolorat($before, 50, 5);
        $rgbBottom = imagecolorat($before, 50, $h - 5);

        $this->thumb->flip('vertical');

        $after = $this->thumb->getOldImage();
        $rgbTopAfter    = imagecolorat($after, 50, 5);
        $rgbBottomAfter = imagecolorat($after, 50, $h - 5);

        self::assertSame($rgbTop,    $rgbBottomAfter);
        self::assertSame($rgbBottom, $rgbTopAfter);
    }

    public function testFlipBothEquivalentToRotate180(): void
    {
        $a = new GD(__DIR__ . '/../../resources/test.jpg');
        $b = new GD(__DIR__ . '/../../resources/test.jpg');

        $a->flip('both');
        $b->rotateImageNDegrees(180);

        self::assertSame($a->getCurrentDimensions()['width'],  $b->getCurrentDimensions()['width']);
        self::assertSame($a->getCurrentDimensions()['height'], $b->getCurrentDimensions()['height']);

        $aImg = $a->getOldImage();
        $bImg = $b->getOldImage();
        self::assertSame(
            imagecolorat($aImg, 100, 100),
            imagecolorat($bImg, 100, 100)
            );
    }

    public function testFlipPreservesDimensions(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->flip('horizontal');

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
    }

    public function testFlipAcceptsAliases(): void
    {
        self::assertInstanceOf(GD::class, $this->thumb->flip('h'));
        self::assertInstanceOf(GD::class, $this->thumb->flip('v'));
        self::assertInstanceOf(GD::class, $this->thumb->flip('hv'));
        self::assertInstanceOf(GD::class, $this->thumb->flip('lr'));
        self::assertInstanceOf(GD::class, $this->thumb->flip('tb'));
    }

    public function testFlipInvalidDirectionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->thumb->flip('diagonal');
    }

    public function testFlipDefaultIsHorizontal(): void
    {
        $a = new GD(__DIR__ . '/../../resources/test.jpg');
        $b = new GD(__DIR__ . '/../../resources/test.jpg');

        $a->flip();
        $b->flip('horizontal');

        self::assertSame(
            imagecolorat($a->getOldImage(), 10, 10),
            imagecolorat($b->getOldImage(), 10, 10)
            );
    }
}
