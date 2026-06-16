<?php
namespace PHPThumb\Tests;

use PHPThumb\Imagick;
use PHPUnit\Framework\TestCase;

class ImagickTest extends TestCase
{
	protected Imagick $avif;
	protected Imagick $gif;
	protected Imagick $jpg;
	protected Imagick $png;
	protected Imagick $webp;

	protected function setUp(): void
	{
		if (!extension_loaded('imagick'))
		{
			$this->markTestSkipped('ext-imagick is not available');
		}

		$this->avif	= new Imagick(__DIR__ . '/../../resources/test.avif');
		$this->gif	= new Imagick(__DIR__ . '/../../resources/test.gif');
		$this->jpg	= new Imagick(__DIR__ . '/../../resources/test.jpg');
		$this->png	= new Imagick(__DIR__ . '/../../resources/test.png');
		$this->webp	= new Imagick(__DIR__ . '/../../resources/test.webp');
	}

	public function testLoadFileTypes()
	{
		// Imagick normalises 'jpg' to 'JPEG' for the internal format string.
		self::assertSame('AVIF',	$this->avif->getFormat());
		self::assertSame('GIF',		$this->gif->getFormat());
		self::assertSame('JPEG',	$this->jpg->getFormat());
		self::assertSame('PNG',		$this->png->getFormat());
		self::assertSame('WEBP',	$this->webp->getFormat());
	}

	/**
	 * This test might seem pointless but it runs the __destruct and gets us to
	 * 100% code coverage.
	 */
	public function testImageDestroy()
	{
		$testImage = new Imagick(__DIR__ . '/../../resources/test.gif');
		unset($testImage);
		self::assertFalse(isset($testImage));
	}

	/**
	 * This test first resize a webp image and then save it in a temp file.
	 * Load the image file and test if the resulting image have a width of 200 px.
	 */
	public function testWebp()
	{
		$this->webp->adaptiveResize(200, 200);

		$tempFile = __DIR__ . '/../../resources/imagick_resize.webp';
		file_put_contents($tempFile, $this->webp->getImageAsString());

		$testing = new Imagick($tempFile);
		self::assertSame(200, $testing->getCurrentDimensions()['width']);

		unlink($tempFile);
	}
}