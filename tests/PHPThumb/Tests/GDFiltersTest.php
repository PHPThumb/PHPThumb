<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\GD;
use PHPUnit\Framework\TestCase;

class GDFiltersTest extends TestCase
{
	protected GD $thumb;

	protected function setUp(): void
	{
		$this->thumb = new GD(__DIR__ . '/../../resources/test.jpg');
	}

	// ---------------- grayscale() ----------------

	public function testGrayscaleReturnsSelf(): void
	{
		self::assertInstanceOf(GD::class, $this->thumb->grayscale());
	}

	public function testGrayscaleChangesPixels(): void
	{
		$before = imagecolorat($this->thumb->getOldImage(), 250, 187);
		$beforeR = ($before >> 16) & 0xFF;
		$beforeG = ($before >> 8)  & 0xFF;
		$beforeB = $before & 0xFF;
		self::assertNotSame($beforeR, $beforeG, 'fixture should have unequal channels for this test');

		$this->thumb->grayscale();

		$after = imagecolorat($this->thumb->getOldImage(), 250, 187);
		$afterR = ($after >> 16) & 0xFF;
		$afterG = ($after >> 8)  & 0xFF;
		$afterB = $after & 0xFF;

		self::assertEqualsWithDelta($afterR, $afterG, 2);
		self::assertEqualsWithDelta($afterG, $afterB, 2);
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
		$before = imagecolorat($this->thumb->getOldImage(), 100, 100);
		$beforeSum = ($before >> 16 & 0xFF) + ($before >> 8 & 0xFF) + ($before & 0xFF);

		$this->thumb->brightness(50);

		$after = imagecolorat($this->thumb->getOldImage(), 100, 100);
		$afterSum = ($after >> 16 & 0xFF) + ($after >> 8 & 0xFF) + ($after & 0xFF);

		self::assertGreaterThan($beforeSum, $afterSum);
	}

	public function testBrightnessNegativeDarkens(): void
	{
		$before = imagecolorat($this->thumb->getOldImage(), 100, 100);
		$beforeSum = ($before >> 16 & 0xFF) + ($before >> 8 & 0xFF) + ($before & 0xFF);

		$this->thumb->brightness(-50);

		$after = imagecolorat($this->thumb->getOldImage(), 100, 100);
		$afterSum = ($after >> 16 & 0xFF) + ($after >> 8 & 0xFF) + ($after & 0xFF);

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

	public function testContrastIncreasesRange(): void
	{
		$img = $this->thumb->getOldImage();
		$beforeDist = $this->sampleDistanceFrom128($img);

		$this->thumb->contrast(30);

		$img = $this->thumb->getOldImage();
		$afterDist = $this->sampleDistanceFrom128($img);

		self::assertGreaterThan($beforeDist, $afterDist);
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
		$before = imagecolorat($this->thumb->getOldImage(), 250, 187);
		$this->thumb->blur(5);
		$after = imagecolorat($this->thumb->getOldImage(), 250, 187);
		self::assertNotSame($before, $after);
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
		$before = imagecolorat($this->thumb->getOldImage(), 250, 187);
		$this->thumb->pixelate(20);
		$after = imagecolorat($this->thumb->getOldImage(), 250, 187);
		self::assertNotSame($before, $after);
	}

	public function testPixelateOneIsNoOp(): void
	{
		$before = imagecolorat($this->thumb->getOldImage(), 250, 187);
		$this->thumb->pixelate(1);
		$after = imagecolorat($this->thumb->getOldImage(), 250, 187);
		self::assertSame($before, $after);
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
		self::assertInstanceOf(GD::class, $this->thumb->edgeDetect());
	}

	public function testEdgeDetectChangesPixels(): void
	{
		$before = imagecolorat($this->thumb->getOldImage(), 250, 187);
		$this->thumb->edgeDetect();
		$after = imagecolorat($this->thumb->getOldImage(), 250, 187);
		self::assertNotSame($before, $after);
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
		self::assertInstanceOf(GD::class, $this->thumb->emboss());
	}

	public function testEmbossChangesPixels(): void
	{
		$before = imagecolorat($this->thumb->getOldImage(), 250, 187);
		$this->thumb->emboss();
		$after = imagecolorat($this->thumb->getOldImage(), 250, 187);
		self::assertNotSame($before, $after);
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
		$before = imagecolorat($this->thumb->getOldImage(), 250, 187);
		$this->thumb->smooth(5);
		$after = imagecolorat($this->thumb->getOldImage(), 250, 187);
		self::assertNotSame($before, $after);
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

		self::assertInstanceOf(GD::class, $result);
		self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
	}

	/**
	 * Average absolute distance from 128 (mid-grey) across sampled pixels.
	 */
	private function sampleDistanceFrom128(\GdImage $img): float
	{
		$samples = [];
		$w = imagesx($img);
		$h = imagesy($img);
		for ($y = 50; $y < $h; $y += 50) {
			for ($x = 50; $x < $w; $x += 50) {
				$rgb = imagecolorat($img, $x, $y);
				$samples[] = abs((($rgb >> 16) & 0xFF) - 128);
			}
		}
		return array_sum($samples) / count($samples);
	}
}
