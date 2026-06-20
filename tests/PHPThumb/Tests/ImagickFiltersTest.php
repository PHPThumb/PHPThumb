<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\Imagick;
use PHPUnit\Framework\TestCase;

class ImagickFiltersTest extends TestCase
{
    protected Imagick $thumb;

    protected function setUp(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('ext-imagick is not available');
        }

        $this->thumb = new Imagick(__DIR__ . '/../../resources/test.jpg');
    }

    // ---------------- grayscale() ----------------

    public function testGrayscaleReturnsSelf(): void
    {
        self::assertInstanceOf(Imagick::class, $this->thumb->grayscale());
    }

    public function testGrayscaleChangesPixels(): void
    {
        $before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        self::assertNotSame((int) $before['r'], (int) $before['g']);

        $this->thumb->grayscale();

        $after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();

        self::assertEqualsWithDelta((int) $after['r'], (int) $after['g'], 256);
        self::assertEqualsWithDelta((int) $after['g'], (int) $after['b'], 256);
    }

    public function testGrayscalePreservesDimensions(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->grayscale();

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
    }

    // ---------------- brightness() ----------------

    public function testBrightnessPositiveBrightens(): void
    {
        $before = $this->thumb->getOldImage()->getImagePixelColor(100, 100)->getColor();
        $beforeSum = $before['r'] + $before['g'] + $before['b'];

        $this->thumb->brightness(50);

        $after = $this->thumb->getOldImage()->getImagePixelColor(100, 100)->getColor();
        $afterSum = $after['r'] + $after['g'] + $after['b'];

        self::assertGreaterThan($beforeSum, $afterSum);
    }

    public function testBrightnessNegativeDarkens(): void
    {
        $before = $this->thumb->getOldImage()->getImagePixelColor(100, 100)->getColor();
        $beforeSum = $before['r'] + $before['g'] + $before['b'];

        $this->thumb->brightness(-50);

        $after = $this->thumb->getOldImage()->getImagePixelColor(100, 100)->getColor();
        $afterSum = $after['r'] + $after['g'] + $after['b'];

        self::assertLessThan($beforeSum, $afterSum);
    }

    public function testBrightnessPreservesDimensions(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->brightness(50);

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
    }

    // ---------------- contrast() ----------------

    public function testContrastChangesPixels(): void
    {
        $before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        $this->thumb->contrast(20);
        $after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        self::assertNotSame((int) $before['r'], (int) $after['r']);
    }

    public function testContrastPreservesDimensions(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->contrast(20);

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
    }

    // ---------------- blur() ----------------

    public function testBlurChangesPixels(): void
    {
        $before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        $this->thumb->blur(5);
        $after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        self::assertNotSame((int) $before['r'], (int) $after['r']);
    }

    public function testBlurPreservesDimensions(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->blur(5);

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
    }

    // ---------------- pixelate() ----------------

    public function testPixelateChangesPixels(): void
    {
        $before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        $this->thumb->pixelate(20);
        $after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        self::assertNotSame((int) $before['r'], (int) $after['r']);
    }

    public function testPixelateOneIsNoOp(): void
    {
        $before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        $this->thumb->pixelate(1);
        $after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();

        self::assertSame((int) $before['r'], (int) $after['r']);
        self::assertSame((int) $before['g'], (int) $after['g']);
        self::assertSame((int) $before['b'], (int) $after['b']);
    }

    public function testPixelateZeroThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->thumb->pixelate(0);
    }

    public function testPixelateNegativeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->thumb->pixelate(-5);
    }

    public function testPixelatePreservesDimensions(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->pixelate(20);

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
    }

    // ---------------- edgeDetect() ----------------

    public function testEdgeDetectReturnsSelf(): void
    {
        self::assertInstanceOf(Imagick::class, $this->thumb->edgeDetect());
    }

    public function testEdgeDetectChangesPixels(): void
    {
        $before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        $this->thumb->edgeDetect();
        $after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        self::assertNotSame((int) $before['r'], (int) $after['r']);
    }

    public function testEdgeDetectPreservesDimensions(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->edgeDetect();

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
    }

    // ---------------- emboss() ----------------

    public function testEmbossReturnsSelf(): void
    {
        self::assertInstanceOf(Imagick::class, $this->thumb->emboss());
    }

    public function testEmbossChangesPixels(): void
    {
        $before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        $this->thumb->emboss();
        $after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        self::assertNotSame((int) $before['r'], (int) $after['r']);
    }

    public function testEmbossPreservesDimensions(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->emboss();

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
    }

    // ---------------- smooth() ----------------

    public function testSmoothChangesPixels(): void
    {
        $before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        $this->thumb->smooth(5);
        $after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        self::assertNotSame((int) $before['r'], (int) $after['r']);
    }

    public function testSmoothPreservesDimensions(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->smooth(3);

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
    }

    // ---------------- chaining ----------------

    public function testFiltersChainable(): void
    {
        $result = $this->thumb
            ->resize(200, 0)
            ->grayscale()
            ->blur(2)
            ->sharpen(40)
            ->pixelate(15);

        self::assertInstanceOf(Imagick::class, $result);
        self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
    }
}
