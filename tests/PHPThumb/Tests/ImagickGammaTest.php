<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\Imagick;
use PHPUnit\Framework\TestCase;

class ImagickGammaTest extends TestCase
{
    protected Imagick $thumb;

    protected function setUp(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('ext-imagick is not available');
        }

        $this->thumb = new Imagick(__DIR__ . '/../../resources/test.jpg');
    }

    // ---------------- gamma() ----------------

    public function testGammaReturnsSelf(): void
    {
        self::assertInstanceOf(Imagick::class, $this->thumb->gamma(1.0));
    }

    public function testGammaOneIsNoOp(): void
    {
        $before = $this->thumb->getOldImage()->getImagePixelColor(50, 50)->getColor();
        $this->thumb->gamma(1.0);
        $after = $this->thumb->getOldImage()->getImagePixelColor(50, 50)->getColor();

        // Imagick uses a higher quantum depth than 8-bit; allow a small delta.
        self::assertEqualsWithDelta((int) $before['r'], (int) $after['r'], 256);
        self::assertEqualsWithDelta((int) $before['g'], (int) $after['g'], 256);
        self::assertEqualsWithDelta((int) $before['b'], (int) $after['b'], 256);
    }

    public function testGammaAboveOneBrightens(): void
    {
        $before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        $beforeSum = $before['r'] + $before['g'] + $before['b'];

        $this->thumb->gamma(2.0);

        $after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        $afterSum = $after['r'] + $after['g'] + $after['b'];

        self::assertGreaterThan($beforeSum, $afterSum);
    }

    public function testGammaBelowOneDarkens(): void
    {
        $before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        $beforeSum = $before['r'] + $before['g'] + $before['b'];

        $this->thumb->gamma(0.5);

        $after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        $afterSum = $after['r'] + $after['g'] + $after['b'];

        self::assertLessThan($beforeSum, $afterSum);
    }

    public function testGammaPreservesDimensions(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->gamma(1.3);

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
    }

    // ---------------- sharpen() ----------------

    public function testSharpenReturnsSelf(): void
    {
        self::assertInstanceOf(Imagick::class, $this->thumb->sharpen());
    }

    public function testSharpenChangesPixels(): void
    {
    	// Imagick::sharpenImage() is a Gaussian filter, not a high-pass
    	// convolution — so the change at any single pixel on a natural
    	// photo can fall below 8-bit quantum. Assert that *some* pixel
    	// in the image changed by ≥1 (8-bit precision), which is what
    	// the operation guarantees portably.
    	$img = $this->thumb->getOldImage();
    	$w = $img->getImageWidth();
    	$h = $img->getImageHeight();

    	// Capture a hash of a sparse sample grid.
    	$before = [];
    	for ($y = 50; $y < $h; $y += 50) {
    		for ($x = 50; $x < $w; $x += 50) {
    			$before["$x,$y"] = $img->getImagePixelColor($x, $y)->getColor();
    		}
    	}

    	$this->thumb->sharpen(50);

    	$img = $this->thumb->getOldImage();
    	$changed = false;
    	foreach ($before as $key => $px) {
    		[$x, $y] = explode(',', $key);
    		$after = $img->getImagePixelColor((int) $x, (int) $y)->getColor();
    		if ((int) $px['r'] !== (int) $after['r']) {
    			$changed = true;
    			break;
    		}
    	}

    	self::assertTrue($changed, 'sharpen() must modify at least one sampled pixel');
    }

    public function testSharpenZeroIsNoOp(): void
    {
        $before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
        $this->thumb->sharpen(0);
        $after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();

        self::assertSame((int) $before['r'], (int) $after['r']);
        self::assertSame((int) $before['g'], (int) $after['g']);
        self::assertSame((int) $before['b'], (int) $after['b']);
    }

    public function testSharpenInvalidAmountThrowsLow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->thumb->sharpen(-10);
    }

    public function testSharpenInvalidAmountThrowsHigh(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->thumb->sharpen(150);
    }

    public function testSharpenPreservesDimensions(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->sharpen(75);

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
    }

    public function testSharpenIsChainable(): void
    {
        $result = $this->thumb
            ->resize(200, 0)
            ->sharpen(60)
            ->sharpen(60);

        self::assertInstanceOf(Imagick::class, $result);
        self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
    }
}
