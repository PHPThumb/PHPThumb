<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\GD;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Geometry operations for the GD backend. This mirrors ImagickOperationsTest
 * so both backends are held to the same behavioural contract.
 */
class GDOperationsTest extends TestCase
{
	protected GD $thumb;

	protected function setUp(): void
	{
		$this->thumb = new GD(__DIR__ . '/../../resources/test.jpg');
	}

	// ---------------- resize() ----------------

	#[DataProvider('resizeProvider')]
	public function testResize(int $maxWidth, int $maxHeight, array $expected): void
	{
		$result = $this->thumb->resize($maxWidth, $maxHeight);

		// calcHeight() uses ceil() (a float in PHP 8.0+), so a delta of 1
		// absorbs the float/int difference without weakening the assertion.
		self::assertEqualsWithDelta($expected['width'],  $this->thumb->getCurrentDimensions()['width'],  1);
		self::assertEqualsWithDelta($expected['height'], $this->thumb->getCurrentDimensions()['height'], 1);
		self::assertInstanceOf(GD::class, $result);
	}

	public static function resizeProvider(): array
	{
		return [
			'resize by width'  => [200, 0, ['width' => 200, 'height' => 150]],
			'resize by height' => [0, 200, ['width' => 267, 'height' => 200]],
			'resize both'	  => [100, 100, ['width' => 100, 'height' => 74]],
			'no resize'		=> [0, 0, ['width' => 500, 'height' => 375]],
		];
	}

	public function testResizeDoesNotUpscaleByDefault(): void
	{
		$this->thumb->resize(600, 600);

		self::assertEqualsWithDelta(500, $this->thumb->getCurrentDimensions()['width'], 1);
		self::assertEqualsWithDelta(375, $this->thumb->getCurrentDimensions()['height'], 1);
	}

	public function testResizeUpOptionAllowsUpscaling(): void
	{
		$this->thumb->setOptions(['resizeUp' => true]);
		$this->thumb->resize(600, 600);

		self::assertEqualsWithDelta(600, $this->thumb->getCurrentDimensions()['width'], 1);
		self::assertEqualsWithDelta(450, $this->thumb->getCurrentDimensions()['height'], 1);
	}

	// ---------------- adaptiveResize() ----------------

	#[DataProvider('adaptiveResizeProvider')]
	public function testAdaptiveResize(int $width, int $height, array $expected): void
	{
		$this->thumb->adaptiveResize($width, $height);

		self::assertEqualsWithDelta($expected['width'],  $this->thumb->getCurrentDimensions()['width'], 1);
		self::assertEqualsWithDelta($expected['height'], $this->thumb->getCurrentDimensions()['height'], 1);
	}

	public static function adaptiveResizeProvider(): array
	{
		return [
			'square resize'	=> [200, 200, ['width' => 200, 'height' => 200]],
			'landscape resize' => [400, 200, ['width' => 400, 'height' => 200]],
			'portrait resize'  => [200, 400, ['width' => 200, 'height' => 375]],
			'width only'	   => [300, 0,   ['width' => 300, 'height' => 225]],
			'height only'	  => [0, 300,   ['width' => 400, 'height' => 300]],
		];
	}

	public function testAdaptiveResizeRejectsZeroArguments(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->thumb->adaptiveResize(0, 0);
	}

	// ---------------- adaptiveResize(): zero axes ----------------

	public function testAdaptiveResizeDerivesAZeroHeightFromTheAspectRatio(): void
	{
		// A height of 0 means "derive it" — the same convention the
		// Imagick backend uses.
		$this->thumb->adaptiveResize(200, 0);

		self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(150, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testAdaptiveResizeWithAZeroHeightProducesAnImageOfTheRequestedSize(): void
	{
		$this->thumb->adaptiveResize(200, 0);

		self::assertSame(200, imagesx($this->thumb->getOldImage()));
		self::assertSame(150, imagesy($this->thumb->getOldImage()));
	}

	public function testAdaptiveResizeDerivesAZeroWidthFromTheAspectRatio(): void
	{
		$this->thumb->adaptiveResize(0, 200);

		self::assertSame(267, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(200, $this->thumb->getCurrentDimensions()['height']);
	}

	// ---------------- adaptiveResizePercent(): zero axes ----------------

	public function testAdaptiveResizePercentDerivesAZeroHeightFromTheAspectRatio(): void
	{
		// Previously this reached imagecreatetruecolor() with a zero
		// height, which GD rejects outright.
		$this->thumb->adaptiveResizePercent(200, 0, 25);

		self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(150, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testAdaptiveResizePercentWithAZeroHeightProducesAnImageOfTheRequestedSize(): void
	{
		$this->thumb->adaptiveResizePercent(200, 0, 25);

		self::assertSame(200, imagesx($this->thumb->getOldImage()));
		self::assertSame(150, imagesy($this->thumb->getOldImage()));
	}

	// ---------------- adaptiveResizeQuadrant(): zero axes ----------------

	public function testAdaptiveResizeQuadrantDerivesAZeroHeightFromTheAspectRatio(): void
	{
		$this->thumb->adaptiveResizeQuadrant(200, 0, 'C');

		self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(150, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testAdaptiveResizeQuadrantWithAZeroHeightProducesAnImageOfTheRequestedSize(): void
	{
		$this->thumb->adaptiveResizeQuadrant(200, 0, 'C');

		self::assertSame(200, imagesx($this->thumb->getOldImage()));
		self::assertSame(150, imagesy($this->thumb->getOldImage()));
	}

	// ---------------- adaptiveResizePercent() ----------------

	public function testAdaptiveResizePercent(): void
	{
		$this->thumb->adaptiveResizePercent(200, 200, 25);

		self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(200, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testAdaptiveResizePercentRejectsZeroWidth(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->thumb->adaptiveResizePercent(0, 200, 25);
	}

	// ---------------- adaptiveResizeQuadrant() ----------------

	#[DataProvider('adaptiveResizeQuadrantProvider')]
	public function testAdaptiveResizeQuadrant(int $width, int $height, string $quadrant, array $expected): void
	{
		$this->thumb->adaptiveResizeQuadrant($width, $height, $quadrant);

		self::assertEqualsWithDelta($expected['width'],  $this->thumb->getCurrentDimensions()['width'], 1);
		self::assertEqualsWithDelta($expected['height'], $this->thumb->getCurrentDimensions()['height'], 1);
	}

	public static function adaptiveResizeQuadrantProvider(): array
	{
		return [
			'center quadrant' => [200, 200, 'C', ['width' => 200, 'height' => 200]],
			'left quadrant'   => [200, 200, 'L', ['width' => 200, 'height' => 200]],
			'right quadrant'  => [200, 200, 'R', ['width' => 200, 'height' => 200]],
			'top quadrant'	=> [200, 200, 'T', ['width' => 200, 'height' => 200]],
			'bottom quadrant' => [200, 200, 'B', ['width' => 200, 'height' => 200]],
		];
	}

	public function testAdaptiveResizeQuadrantRejectsZeroWidth(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->thumb->adaptiveResizeQuadrant(0, 200, 'C');
	}

	// ---------------- resizePercent() ----------------

	public function testResizePercent(): void
	{
		$this->thumb->resizePercent(50);

		// calcPercent() uses ceil() (375 * 0.5 = 187.5 → 188) but the
		// final calcImageSize() pass floors the height to 187.
		self::assertEqualsWithDelta(250, $this->thumb->getCurrentDimensions()['width'], 1);
		self::assertEqualsWithDelta(188, $this->thumb->getCurrentDimensions()['height'], 1);
	}

	// ---------------- crop() ----------------

	public function testCrop(): void
	{
		$this->thumb->crop(100, 50, 200, 150);

		self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(150, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testCropFromCenter(): void
	{
		$this->thumb->cropFromCenter(200);

		self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(200, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testCropFromCenterWithHeight(): void
	{
		$this->thumb->cropFromCenter(200, 100);

		self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(100, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testCropIsClampedToTheImageBounds(): void
	{
		// A crop wider/taller than the source must be clamped, not fatal.
		$this->thumb->crop(0, 0, 900, 900);

		self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(375, $this->thumb->getCurrentDimensions()['height']);
	}

	// ---------------- rotation ----------------

	public function testRotateImageCW(): void
	{
		$this->thumb->rotateImage('CW');

		self::assertSame(375, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(500, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testRotateImageCCW(): void
	{
		$this->thumb->rotateImage('CCW');

		self::assertSame(375, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(500, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testRotateImageNDegrees(): void
	{
		$this->thumb->rotateImageNDegrees(180);

		self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(375, $this->thumb->getCurrentDimensions()['height']);
	}

	// ---------------- pad() ----------------

	public function testPad(): void
	{
		$this->thumb->pad(600, 500);

		self::assertSame(600, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(500, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testPadNoResizeIsANoOp(): void
	{
		$result = $this->thumb->pad(500, 375);

		self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(375, $this->thumb->getCurrentDimensions()['height']);
		self::assertInstanceOf(GD::class, $result);
	}

	public function testPadFillsTheRemainderWithTheGivenColour(): void
	{
		$this->thumb->pad(600, 500, [0, 0, 255]);

		$rgb = imagecolorat($this->thumb->getOldImage(), 0, 0);

		self::assertSame(0,   ($rgb >> 16) & 0xFF, 'R');
		self::assertSame(0,   ($rgb >> 8) & 0xFF,  'G');
		self::assertSame(255, $rgb & 0xFF,		'B');
	}

	public function testPadCentresTheOriginalImage(): void
	{
		$before = imagecolorat($this->thumb->getOldImage(), 250, 187);

		$this->thumb->pad(600, 500);

		// The source is 500x375 inside a 600x500 canvas → offset (50, 62).
		$after = imagecolorat($this->thumb->getOldImage(), 300, 249);

		self::assertEqualsWithDelta(($before >> 16) & 0xFF, ($after >> 16) & 0xFF, 5);
		self::assertEqualsWithDelta(($before >> 8) & 0xFF,  ($after >> 8) & 0xFF,  5);
		self::assertEqualsWithDelta($before & 0xFF,		$after & 0xFF,		5);
	}

	// ---------------- chaining ----------------

	public function testOperationsAreChainable(): void
	{
		$result = $this->thumb
			->resize(200, 0)
			->crop(0, 0, 100, 100)
			->border(5, '#000000')
			->pad(200, 200);

		self::assertInstanceOf(GD::class, $result);
		self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(200, $this->thumb->getCurrentDimensions()['height']);
	}
}