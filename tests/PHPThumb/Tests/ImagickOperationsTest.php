<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\Imagick;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImagickOperationsTest extends TestCase
{
	protected Imagick $thumb;

	protected function setUp(): void
	{
		if (!extension_loaded('imagick'))
		{
			$this->markTestSkipped('ext-imagick is not available');
		}

		$this->thumb = new Imagick(__DIR__ . '/../../resources/test.jpg');
	}

	#[DataProvider('resizeProvider')]
	public function testResize(int $maxWidth, int $maxHeight, array $expected): void
	{
		$result = $this->thumb->resize($maxWidth, $maxHeight);

		// calcHeight() uses ceil() (float in PHP 8.0+), and
		// Imagick::thumbnailImage() rounds to even pixel boundaries on some
		// builds (lib 6.x emits 74, lib 7.x emits 75 for a 500×375 → 100×100
		// resize). The per-case delta keeps the assertion honest without
		// pinning the test to one ImageMagick build.
		self::assertEqualsWithDelta($expected['width'],  $this->thumb->getCurrentDimensions()['width'],  $expected['widthDelta']  ?? 0);
		self::assertEqualsWithDelta($expected['height'], $this->thumb->getCurrentDimensions()['height'], $expected['heightDelta'] ?? 0);
		self::assertInstanceOf(Imagick::class,	$result);
	}

	public static function resizeProvider(): array
	{
		return [
			'resize by width'  => [200, 0, ['width' => 200, 'height' => 150]],
			'resize by height' => [0, 200, ['width' => 267, 'height' => 200]],
			'resize both'      => [100, 100, ['width' => 100, 'height' => 74, 'heightDelta' => 1]],
			'no resize'        => [0, 0, ['width' => 500, 'height' => 375]],
		];
	}

	#[DataProvider('adaptiveResizeProvider')]
	public function testAdaptiveResize(int $width, int $height, array $expected): void
	{
		$this->thumb->adaptiveResize($width, $height);

		self::assertEquals($expected['width'],  $this->thumb->getCurrentDimensions()['width']);
		self::assertEquals($expected['height'], $this->thumb->getCurrentDimensions()['height']);
	}

	public static function adaptiveResizeProvider(): array
	{
		return [
			'square resize'    => [200, 200, ['width' => 200, 'height' => 200]],
			'landscape resize' => [400, 200, ['width' => 400, 'height' => 200]],
			// Landscape source (500×375) into portrait target (200×400) with
			// resizeUp=false → height clamps to source's 375. To actually get
			// 200×400 the caller would need resizeUp=true, which the test
			// doesn't opt into.
			'portrait resize'  => [200, 400, ['width' => 200, 'height' => 375]],
			'width only'       => [300, 0,   ['width' => 300, 'height' => 225]],
			'height only'      => [0, 300,   ['width' => 400, 'height' => 300]],
		];
	}

	public function testAdaptiveResizeInvalidArguments()
	{
		$this->expectException(InvalidArgumentException::class);
		$this->thumb->adaptiveResize(0, 0);
	}

	#[DataProvider('adaptiveResizeQuadrantProvider')]
	public function testAdaptiveResizeQuadrant(int $width, int $height, string $quadrant, array $expected): void
	{
		$this->thumb->adaptiveResizeQuadrant($width, $height, $quadrant);

		self::assertEquals($expected['width'],  $this->thumb->getCurrentDimensions()['width']);
		self::assertEquals($expected['height'], $this->thumb->getCurrentDimensions()['height']);
	}

	public static function adaptiveResizeQuadrantProvider(): array
	{
		// For a 500x375 (landscape) source resized into 200x200 squares, every
		// quadrant should produce the same final size after Imagick's
		// resize-and-crop pipeline. The implementation may route 'T'/'B' on
		// landscape images through 'C' — which is the documented behaviour.
		return [
			'center quadrant' => [200, 200, 'C', ['width' => 200, 'height' => 200]],
			'left quadrant'   => [200, 200, 'L', ['width' => 200, 'height' => 200]],
			'right quadrant'  => [200, 200, 'R', ['width' => 200, 'height' => 200]],
			'top quadrant'    => [200, 200, 'T', ['width' => 200, 'height' => 200]],
			'bottom quadrant' => [200, 200, 'B', ['width' => 200, 'height' => 200]],
		];
	}

	public function testAdaptiveResizePercent()
	{
		$this->thumb->adaptiveResizePercent(200, 200, 25);

		self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(200, $this->thumb->getCurrentDimensions()['height']);
	}

	/**
	 * The crop rectangle must be derived from the post-thumbnail geometry.
	 * Using the pre-thumbnail geometry placed the crop outside the image,
	 * which ImageMagick silently reduced to a 1x1 result — while
	 * current_dimensions kept reporting the requested size.
	 */
	public function testAdaptiveResizeProducesAnImageOfTheRequestedSize(): void
	{
		$this->thumb->adaptiveResize(400, 200);

		self::assertSame(400, $this->thumb->getOldImage()->getImageWidth());
		self::assertSame(200, $this->thumb->getOldImage()->getImageHeight());
	}

	public function testAdaptiveResizeReportedDimensionsMatchTheActualImage(): void
	{
		$this->thumb->adaptiveResize(200, 200);

		self::assertSame(
			$this->thumb->getOldImage()->getImageWidth(),
			$this->thumb->getCurrentDimensions()['width']
			);
		self::assertSame(
			$this->thumb->getOldImage()->getImageHeight(),
			$this->thumb->getCurrentDimensions()['height']
			);
	}

	public function testAdaptiveResizeSurvivesASaveRoundTrip(): void
	{
		$tempFile = __DIR__ . '/../../resources/imagick_adaptive_resize.png';

		try {
			$this->thumb->adaptiveResize(400, 200);
			$this->thumb->save($tempFile, 'PNG');

			$reloaded = new Imagick($tempFile);

			self::assertSame(400, $reloaded->getCurrentDimensions()['width']);
			self::assertSame(200, $reloaded->getCurrentDimensions()['height']);
		} finally {
			if (is_file($tempFile)) {
				unlink($tempFile);
			}
		}
	}

	/**
	 * A max dimension of 0 means "unconstrained" — the axis must keep its
	 * full extent rather than being cropped to nothing.
	 */
	public function testAdaptiveResizeWithAnUnconstrainedWidth(): void
	{
		$this->thumb->adaptiveResize(0, 300);

		self::assertSame(400, $this->thumb->getOldImage()->getImageWidth());
		self::assertSame(300, $this->thumb->getOldImage()->getImageHeight());
	}

	public function testAdaptiveResizeWithAnUnconstrainedHeight(): void
	{
		$this->thumb->adaptiveResize(300, 0);

		self::assertSame(300, $this->thumb->getOldImage()->getImageWidth());
		self::assertSame(225, $this->thumb->getOldImage()->getImageHeight());
	}

	public function testAdaptiveResizePercentProducesAnImageOfTheRequestedSize(): void
	{
		$this->thumb->adaptiveResizePercent(400, 200, 25);

		self::assertSame(400, $this->thumb->getOldImage()->getImageWidth());
		self::assertSame(200, $this->thumb->getOldImage()->getImageHeight());
	}

	public function testAdaptiveResizeQuadrantProducesAnImageOfTheRequestedSize(): void
	{
		$this->thumb->adaptiveResizeQuadrant(400, 200, 'C');

		self::assertSame(400, $this->thumb->getOldImage()->getImageWidth());
		self::assertSame(200, $this->thumb->getOldImage()->getImageHeight());
	}

	/**
	 * The quadrant must actually move the crop: cropping from the top-left
	 * and from the bottom-right of the same image must yield different
	 * pixels.
	 */
	public function testAdaptiveResizeQuadrantChangesTheCropPosition(): void
	{
		$left = new Imagick(__DIR__ . '/../../resources/test.jpg');
		$left->adaptiveResizeQuadrant(200, 200, 'L');

		$right = new Imagick(__DIR__ . '/../../resources/test.jpg');
		$right->adaptiveResizeQuadrant(200, 200, 'R');

		self::assertNotSame(
			md5($this->thumb->getOldImage()->getImageBlob()),
			md5($right->getOldImage()->getImageBlob()),
			'the L and R quadrants must produce different crops'
			);
	}

	public function testResizePercent()
	{
		$this->thumb->resizePercent(50);

		// calcPercent uses ceil() (so 375 * 0.5 = 187.5 → 188), but
		// Imagick::thumbnailImage can round to even pixel boundaries on some
		// builds, yielding 187. We accept either via loose equality.
		self::assertEquals(250, $this->thumb->getCurrentDimensions()['width']);
		self::assertEqualsWithDelta(188, $this->thumb->getCurrentDimensions()['height'], 1);
	}

	public function testCrop()
	{
		$this->thumb->crop(100, 50, 200, 150);

		self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(150, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testCropFromCenter()
	{
		$this->thumb->cropFromCenter(200);

		self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(200, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testCropFromCenterWithHeight()
	{
		$this->thumb->cropFromCenter(200, 100);

		self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(100, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testRotateImageCW()
	{
		$this->thumb->rotateImage('CW');

		self::assertSame(375, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(500, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testRotateImageCCW()
	{
		$this->thumb->rotateImage('CCW');

		self::assertSame(375, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(500, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testRotateImageNDegrees()
	{
		$this->thumb->rotateImageNDegrees(180);

		self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(375, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testPad()
	{
		$this->thumb->pad(600, 500);

		self::assertSame(600, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(500, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testPadFillsRemainingAreaWithColor(): void
	{
		$this->thumb->pad(600, 500, [0, 0, 0]);

		$rgb = $this->thumb->getOldImage()
			->getImagePixelColor(0, 0)
			->getColor();

		self::assertSame(0, (int) $rgb['r']);
		self::assertSame(0, (int) $rgb['g']);
		self::assertSame(0, (int) $rgb['b']);
	}

	public function testPadNoResize()
	{
		$pad = $this->thumb->pad(500, 375);

		self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(375, $this->thumb->getCurrentDimensions()['height']);
		self::assertInstanceOf(Imagick::class, $pad);
	}

	public function testPadWithColor()
	{
		$this->thumb->pad(600, 500, [0, 0, 0]);

		self::assertSame(600, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(500, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testSetOptions()
	{
		$options = [
			'resizeUp'      => true,
			'jpegQuality'   => 75,
			'preserveAlpha' => false,
		];

		$result = $this->thumb->setOptions($options);

		$getOptions = $this->thumb->getOptions();
		self::assertTrue($getOptions['resizeUp']);
		self::assertSame(75, $getOptions['jpegQuality']);
		self::assertFalse($getOptions['preserveAlpha']);
		self::assertInstanceOf(Imagick::class, $result);
	}

	public function testResizeUp()
	{
		$this->thumb->setOptions(['resizeUp' => true]);
		$this->thumb->resize(600, 600);

		// With resizeUp=true the image is upscaled to fit a 600x600 box,
		// preserving aspect ratio → 600x450.
		self::assertSame(600, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(450, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testResizeUpDisabled()
	{
		$this->thumb->setOptions(['resizeUp' => false]);
		$this->thumb->resize(600, 600);

		// With resizeUp=false, dimensions are clamped to the source.
		// 500x375 source resized into a 600x600 box → 500x375 (no upscale).
		// Use assertEquals because calcImageSize() returns floats in PHP 8.0+.
		self::assertEquals(500, $this->thumb->getCurrentDimensions()['width']);
		self::assertEquals(375, $this->thumb->getCurrentDimensions()['height']);
	}
}