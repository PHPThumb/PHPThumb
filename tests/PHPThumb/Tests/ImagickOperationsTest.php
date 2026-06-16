<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\Imagick;
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

	/**
	 * @dataProvider resizeProvider
	 */
	public function testResize(int $maxWidth, int $maxHeight, array $expected): void
	{
		$result = $this->thumb->resize($maxWidth, $maxHeight);

		self::assertSame($expected['width'],	$this->thumb->getCurrentDimensions()['width']);
		self::assertSame($expected['height'],	$this->thumb->getCurrentDimensions()['height']);
		self::assertInstanceOf(Imagick::class,	$result);
	}

	public static function resizeProvider(): array
	{
		return [
			'resize by width'  => [200, 0, ['width' => 200, 'height' => 150]],
			'resize by height' => [0, 200, ['width' => 267, 'height' => 200]],
			'resize both'      => [100, 100, ['width' => 100, 'height' => 75]],
			'no resize'        => [0, 0, ['width' => 500, 'height' => 375]],
		];
	}

	/**
	 * @dataProvider adaptiveResizeProvider
	 */
	public function testAdaptiveResize(int $width, int $height, array $expected): void
	{
		$this->thumb->adaptiveResize($width, $height);

		self::assertSame($expected['width'],	$this->thumb->getCurrentDimensions()['width']);
		self::assertSame($expected['height'],	$this->thumb->getCurrentDimensions()['height']);
	}

	public static function adaptiveResizeProvider(): array
	{
		return [
			'square resize'    => [200, 200, ['width' => 200, 'height' => 200]],
			'landscape resize' => [400, 200, ['width' => 400, 'height' => 200]],
			'portrait resize'  => [200, 400, ['width' => 200, 'height' => 400]],
			'width only'       => [300, 0,   ['width' => 300, 'height' => 225]],
			'height only'      => [0, 300,   ['width' => 400, 'height' => 300]],
		];
	}

	public function testAdaptiveResizeInvalidArguments()
	{
		$this->expectException(InvalidArgumentException::class);
		$this->thumb->adaptiveResize(0, 0);
	}

	/**
	 * @dataProvider adaptiveResizeQuadrantProvider
	 */
	public function testAdaptiveResizeQuadrant(int $width, int $height, string $quadrant, array $expected): void
	{
		$this->thumb->adaptiveResizeQuadrant($width, $height, $quadrant);

		self::assertSame($expected['width'],	$this->thumb->getCurrentDimensions()['width']);
		self::assertSame($expected['height'],	$this->thumb->getCurrentDimensions()['height']);
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

	public function testResizePercent()
	{
		$this->thumb->resizePercent(50);

		// 500 * 0.5 = 250, 375 * 0.5 = 187.5 → ceil = 188
		self::assertSame(250, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(188, $this->thumb->getCurrentDimensions()['height']);
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
		self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(375, $this->thumb->getCurrentDimensions()['height']);
	}
}