<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\Imagick;
use PHPUnit\Framework\TestCase;

class ImagickFlipTest extends TestCase
{
    protected Imagick $thumb;

    protected function setUp(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('ext-imagick is not available');
        }

        $this->thumb = new Imagick(__DIR__ . '/../../resources/test.jpg');
    }

    public function testFlipHorizontalReturnsSelf(): void
    {
        self::assertInstanceOf(Imagick::class, $this->thumb->flip('horizontal'));
    }

    public function testFlipHorizontalChangesPixels(): void
    {
    	$h = $this->thumb->getCurrentDimensions()['height'];
    	$mid_y = (int) ($h / 2);

    	$before = $this->thumb->getOldImage()
    	->getImagePixelColor(5, $mid_y)
    	->getColor();

    	$this->thumb->flip('horizontal');

    	$w = $this->thumb->getCurrentDimensions()['width'];
    	$after = $this->thumb->getOldImage()
    	->getImagePixelColor($w - 6, $mid_y)
    	->getColor();


        self::assertEqualsWithDelta((int) $before['r'], (int) $after['r'], 5);
        self::assertEqualsWithDelta((int) $before['g'], (int) $after['g'], 5);
        self::assertEqualsWithDelta((int) $before['b'], (int) $after['b'], 5);
    }

    public function testFlipVerticalSwapsRows(): void
    {
        $before = $this->thumb->getOldImage()
            ->getImagePixelColor(50, 5)
            ->getColor();

        $this->thumb->flip('vertical');

        $h = $this->thumb->getCurrentDimensions()['height'];
        $after = $this->thumb->getOldImage()
            ->getImagePixelColor(50, $h - 5)
            ->getColor();

        self::assertEqualsWithDelta((int) $before['r'], (int) $after['r'], 5);
        self::assertEqualsWithDelta((int) $before['g'], (int) $after['g'], 5);
        self::assertEqualsWithDelta((int) $before['b'], (int) $after['b'], 5);
    }

    public function testFlipBothEquivalentToRotate180(): void
    {
        $a = new Imagick(__DIR__ . '/../../resources/test.jpg');
        $b = new Imagick(__DIR__ . '/../../resources/test.jpg');

        $a->flip('both');
        $b->rotateImageNDegrees(180);

        self::assertSame($a->getCurrentDimensions()['width'],  $b->getCurrentDimensions()['width']);
        self::assertSame($a->getCurrentDimensions()['height'], $b->getCurrentDimensions()['height']);

        $aImg = $a->getOldImage();
        $bImg = $b->getOldImage();
        $aPx = $aImg->getImagePixelColor(100, 100)->getColor();
        $bPx = $bImg->getImagePixelColor(100, 100)->getColor();

        self::assertEqualsWithDelta((int) $aPx['r'], (int) $bPx['r'], 5);
        self::assertEqualsWithDelta((int) $aPx['g'], (int) $bPx['g'], 5);
        self::assertEqualsWithDelta((int) $aPx['b'], (int) $bPx['b'], 5);
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
        self::assertInstanceOf(Imagick::class, $this->thumb->flip('h'));
        self::assertInstanceOf(Imagick::class, $this->thumb->flip('v'));
        self::assertInstanceOf(Imagick::class, $this->thumb->flip('hv'));
        self::assertInstanceOf(Imagick::class, $this->thumb->flip('lr'));
        self::assertInstanceOf(Imagick::class, $this->thumb->flip('tb'));
    }

    public function testFlipInvalidDirectionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->thumb->flip('diagonal');
    }

    public function testFlipDefaultIsHorizontal(): void
    {
        $a = new Imagick(__DIR__ . '/../../resources/test.jpg');
        $b = new Imagick(__DIR__ . '/../../resources/test.jpg');

        $a->flip();
        $b->flip('horizontal');

        $aPx = $a->getOldImage()->getImagePixelColor(10, 10)->getColor();
        $bPx = $b->getOldImage()->getImagePixelColor(10, 10)->getColor();

        self::assertEqualsWithDelta((int) $aPx['r'], (int) $bPx['r'], 5);
        self::assertEqualsWithDelta((int) $aPx['g'], (int) $bPx['g'], 5);
        self::assertEqualsWithDelta((int) $aPx['b'], (int) $bPx['b'], 5);
    }
}
