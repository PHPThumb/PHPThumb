<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\GD;
use PHPUnit\Framework\TestCase;

class GDGammaTest extends TestCase
{
    protected GD $thumb;

    protected function setUp(): void
    {
        $this->thumb = new GD(__DIR__ . '/../../resources/test.jpg');
    }

    // ---------------- gamma() ----------------

    public function testGammaReturnsSelf(): void
    {
        $result = $this->thumb->gamma(1.0);
        self::assertInstanceOf(GD::class, $result);
    }

    public function testGammaOneIsNoOp(): void
    {
        $before = imagecolorat($this->thumb->getOldImage(), 50, 50);
        $this->thumb->gamma(1.0);

        $after = imagecolorat($this->thumb->getOldImage(), 50, 50);
        self::assertEqualsWithDelta(
            ($before >> 16) & 0xFF, ($after >> 16) & 0xFF, 5
            );
        self::assertEqualsWithDelta(
            ($before >> 8) & 0xFF, ($after >> 8) & 0xFF, 5
            );
        self::assertEqualsWithDelta(
            $before & 0xFF, $after & 0xFF, 5
            );
    }

    public function testGammaAboveOneBrightens(): void
    {
        $beforeRgb = imagecolorat($this->thumb->getOldImage(), 250, 187);
        $beforeSum = ($beforeRgb >> 16 & 0xFF) + ($beforeRgb >> 8 & 0xFF) + ($beforeRgb & 0xFF);

        $this->thumb->gamma(2.0);

        $afterRgb = imagecolorat($this->thumb->getOldImage(), 250, 187);
        $afterSum = ($afterRgb >> 16 & 0xFF) + ($afterRgb >> 8 & 0xFF) + ($afterRgb & 0xFF);

        self::assertGreaterThan($beforeSum, $afterSum);
    }

    public function testGammaBelowOneDarkens(): void
    {
        $beforeRgb = imagecolorat($this->thumb->getOldImage(), 250, 187);
        $beforeSum = ($beforeRgb >> 16 & 0xFF) + ($beforeRgb >> 8 & 0xFF) + ($beforeRgb & 0xFF);

        $this->thumb->gamma(0.5);

        $afterRgb = imagecolorat($this->thumb->getOldImage(), 250, 187);
        $afterSum = ($afterRgb >> 16 & 0xFF) + ($afterRgb >> 8 & 0xFF) + ($afterRgb & 0xFF);

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
        $result = $this->thumb->sharpen();
        self::assertInstanceOf(GD::class, $result);
    }

    public function testSharpenChangesPixels(): void
    {
        $before = imagecolorat($this->thumb->getOldImage(), 250, 187);
        $this->thumb->sharpen(50);
        $after = imagecolorat($this->thumb->getOldImage(), 250, 187);

        $beforeChannels = [($before >> 16) & 0xFF, ($before >> 8) & 0xFF, $before & 0xFF];
        $afterChannels  = [($after >> 16) & 0xFF, ($after >> 8) & 0xFF, $after & 0xFF];

        $differs = false;
        for ($i = 0; $i < 3; $i++) {
            if (abs($beforeChannels[$i] - $afterChannels[$i]) >= 1) {
                $differs = true;
                break;
            }
        }
        self::assertTrue($differs, 'sharpen() must change at least one color channel');
    }

    public function testSharpenZeroIsNoOp(): void
    {
        $before = imagecolorat($this->thumb->getOldImage(), 250, 187);
        $this->thumb->sharpen(0);
        $after = imagecolorat($this->thumb->getOldImage(), 250, 187);
        self::assertSame($before, $after);
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

        self::assertInstanceOf(GD::class, $result);
        self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
    }

    /**
     * Builds a GD instance backed by a synthetic uniform-color image.
     * The returned object's working image is $w × $h filled with the given
     * RGB value.
     */
    private function makeUniformGd(int $w, int $h, int $r, int $g, int $b): GD
    {
    	$im = imagecreatetruecolor($w, $h);
    	if ($im === false) {
    		self::fail('imagecreatetruecolor() failed');
    	}
    	$fill = imagecolorallocate($im, $r, $g, $b);
    	if ($fill === false) {
    		self::fail('imagecolorallocate() failed');
    	}
    	imagefilledrectangle($im, 0, 0, $w, $h, $fill);

    	// Inject the synthetic image into a fresh GD instance. We bypass
    	// the file-loading constructor entirely so the test is hermetic.
    	$thumb = new GD(__DIR__ . '/../../resources/test.jpg');
    	$thumb->setOldImage($im);
    	$thumb->setCurrentDimensions([
    		'width'  => $w,
    		'height' => $h,
    	]);

    	return $thumb;
    }

    /**
     * Reads the RGB channels from a GdImage at ($x, $y) and returns them as
     * a 3-element array of ints.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function readRgb(\GdImage $im, int $x, int $y): array
    {
    	$px = imagecolorat($im, $x, $y);
    	return [
    		($px >> 16) & 0xFF,
    		($px >> 8)  & 0xFF,
    		$px         & 0xFF,
    	];
    }

    /**
     * Core regression test: sharpening a uniform-color image must not change
     * the color of any pixel (only edges should respond to the kernel).
     */
    public function testSharpenDoesNotDarkenFlatRegions(): void
    {
    	$thumb = $this->makeUniformGd(100, 100, 200, 100, 50);

    	$thumb->sharpen(50);

    	// Sample the center pixel. With the bug, it would be ~(67, 33, 17).
    	// With the fix it must stay at (200, 100, 50) within ±2 rounding.
    	[$r, $g, $b] = $this->readRgb($thumb->getOldImage(), 50, 50);

    	self::assertEqualsWithDelta(200, $r, 2, 'R channel must be preserved');
    	self::assertEqualsWithDelta(100, $g, 2, 'G channel must be preserved');
    	self::assertEqualsWithDelta(50,  $b, 2, 'B channel must be preserved');
    }

    /**
     * The pre-fix bug scaled flat regions by (c − 8) / c. Verify across the
     * full 0..100 range that flat regions remain flat (modulo rounding).
     */
    public function testSharpenFlatRegionsAtEveryAmount(): void
    {
    	foreach ([10, 25, 50, 75, 100] as $amount) {
    		$thumb = $this->makeUniformGd(50, 50, 255, 255, 255);
    		$thumb->sharpen($amount);

    		[$r, $g, $b] = $this->readRgb($thumb->getOldImage(), 25, 25);

    		self::assertEqualsWithDelta(255, $r, 2, "R at amount=$amount");
    		self::assertEqualsWithDelta(255, $g, 2, "G at amount=$amount");
    		self::assertEqualsWithDelta(255, $b, 2, "B at amount=$amount");
    	}
    }

    /**
     * Verify the divisor is positive for all valid amounts (including the
     * lowest non-zero ones). The fix clamps center to max(9, …) precisely
     * so that divisor = center − 8 is at least 1.
     */
    public function testSharpenDoesNotProduceZeroDivisor(): void
    {
    	// Amount = 1 used to round center down to 8, making divisor zero
    	// (and triggering division-by-zero warnings in imageconvolution()).
    	$thumb = $this->makeUniformGd(20, 20, 128, 128, 128);

    	// No assertion needed — the test is that this call doesn't throw or
    	// emit a warning. PHPUnit 11's failOnWarning="true" makes the
    	// implicit assertion explicit.
    	$thumb->sharpen(1);

    	self::assertTrue(true);
    }

    /**
     * Sharpen a non-uniform region: edge pixels must change (sharpening
     * actually happened), but the mean color of a uniform region must not.
     * This is the canonical "the fix doesn't kill sharpening" test.
     */
    public function testSharpenStillSharpensEdges(): void
    {
    	// Build an image with a sharp vertical edge: left half white,
    	// right half black. The center column is the edge; pixels far
    	// from the edge are flat.
    	$w = 50;
    	$h = 50;
    	$im = imagecreatetruecolor($w, $h);
    	$white = imagecolorallocate($im, 255, 255, 255);
    	$black = imagecolorallocate($im, 0, 0, 0);
    	imagefilledrectangle($im, 0,  0, $w / 2, $h, $white);
    	imagefilledrectangle($im, $w / 2, 0, $w - 1,  $h, $black);

    	$thumb = new GD(__DIR__ . '/../../resources/test.jpg');
    	$thumb->setOldImage($im);
    	$thumb->setCurrentDimensions(['width' => $w, 'height' => $h]);

    	// Capture the edge column (x = $w / 2) before and after.
    	$before = $this->readRgb($thumb->getOldImage(), (int) ($w / 2), (int) ($h / 2));

    	$thumb->sharpen(50);

    	$after = $this->readRgb($thumb->getOldImage(), (int) ($w / 2), (int) ($h / 2));

    	// Edge pixel must change — sharpening did something.
    	self::assertNotSame($before, $after, 'sharpen() must still modify edge pixels');

    	// Far-from-edge pixels must remain at their original flat color.
    	// With the bug, they'd be ~85 (white side) and ~0 (black side) is
    	// unchanged, but the white side would be visibly darkened.
    	[$leftR, $leftG, $leftB] = $this->readRgb($thumb->getOldImage(), 5, (int) ($h / 2));
    	self::assertEqualsWithDelta(255, $leftR, 2, 'left flat region must stay white');
    	self::assertEqualsWithDelta(255, $leftG, 2, 'left flat region must stay white');
    	self::assertEqualsWithDelta(255, $leftB, 2, 'left flat region must stay white');

    	[$rightR, $rightG, $rightB] = $this->readRgb($thumb->getOldImage(), $w - 5, (int) ($h / 2));
    	self::assertEqualsWithDelta(0, $rightR, 2, 'right flat region must stay black');
    	self::assertEqualsWithDelta(0, $rightG, 2, 'right flat region must stay black');
    	self::assertEqualsWithDelta(0, $rightB, 2, 'right flat region must stay black');
    }
}
