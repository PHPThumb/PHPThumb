<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\Imagick;
use PHPUnit\Framework\TestCase;

class ImagickBorderTest extends TestCase
{
    protected Imagick $thumb;

    protected function setUp(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('ext-imagick is not available');
        }

        $this->thumb = new Imagick(__DIR__ . '/../../resources/test.jpg');
    }

    public function testBorderReturnsSelf(): void
    {
        self::assertInstanceOf(Imagick::class, $this->thumb->border(10));
    }

    public function testBorderIncreasesDimensions(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->border(10);

        self::assertSame($w + 20, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h + 20, $this->thumb->getCurrentDimensions()['height']);
    }

    public function testBorderColorAtCorner(): void
    {
        $this->thumb->border(10, '#FF0000');

        $rgb = $this->thumb->getOldImage()
            ->getImagePixelColor(0, 0)
            ->getColor();

        // Imagick can return 16-bit-quantum values; >255 is fine. We just
        // verify the ratio: R is the dominant channel and G/B are zero.
        self::assertGreaterThan($rgb['g'], $rgb['r']);
        self::assertSame(0, $rgb['g']);
        self::assertSame(0, $rgb['b']);
    }

    public function testBorderColorAtAllCorners(): void
    {
        $this->thumb->border(20, [0, 255, 0]);
        $img = $this->thumb->getOldImage();
        $w = $img->getImageWidth();
        $h = $img->getImageHeight();

        foreach ([
            [0,     0],
            [$w - 1, 0],
            [0,     $h - 1],
            [$w - 1, $h - 1],
        ] as [$x, $y]) {
            $rgb = $img->getImagePixelColor($x, $y)->getColor();
            self::assertSame(0,   $rgb['r'], "R at ($x,$y)");
            self::assertGreaterThan(0, $rgb['g'], "G at ($x,$y) must be > 0");
            self::assertSame(0,   $rgb['b'], "B at ($x,$y)");
        }
    }

    public function testBorderPreservesCenter(): void
    {
        $beforeRgb = $this->thumb->getOldImage()
            ->getImagePixelColor(250, 187)
            ->getColor();

        $this->thumb->border(20, '#000000');

        // Original pixel moves to (270, 207).
        $afterRgb = $this->thumb->getOldImage()
            ->getImagePixelColor(270, 207)
            ->getColor();

        self::assertEqualsWithDelta($beforeRgb['r'], $afterRgb['r'], 5);
        self::assertEqualsWithDelta($beforeRgb['g'], $afterRgb['g'], 5);
        self::assertEqualsWithDelta($beforeRgb['b'], $afterRgb['b'], 5);
    }

    public function testBorderAcceptsHexShort(): void
    {
        $this->thumb->border(5, '#fff');
        $rgb = $this->thumb->getOldImage()
            ->getImagePixelColor(0, 0)
            ->getColor();
        self::assertGreaterThanOrEqual(0xFF00, $rgb['r']);
        self::assertGreaterThanOrEqual(0xFF00, $rgb['g']);
        self::assertGreaterThanOrEqual(0xFF00, $rgb['b']);
    }

    public function testBorderZeroIsNoOp(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->border(0);

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
    }

    public function testBorderNegativeThicknessThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->thumb->border(-5);
    }

    public function testBorderInvalidColorThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->thumb->border(5, [255, 0]);
    }

    public function testBorderInvalidHexThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->thumb->border(5, '#zzzzzz');
    }

    public function testBorderIsChainable(): void
    {
        $result = $this->thumb
            ->resize(200, 0)
            ->border(10, '#000000')
            ->sharpen(50);

        self::assertInstanceOf(Imagick::class, $result);
        self::assertSame(220, $this->thumb->getCurrentDimensions()['width']);
    }

    public function testBorderDefaultColorIsBlack(): void
    {
        $this->thumb->border(5);
        $rgb = $this->thumb->getOldImage()
            ->getImagePixelColor(0, 0)
            ->getColor();
        self::assertSame(0, $rgb['r']);
        self::assertSame(0, $rgb['g']);
        self::assertSame(0, $rgb['b']);
    }
}
