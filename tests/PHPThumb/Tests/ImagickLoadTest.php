<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\Imagick;
use PHPUnit\Framework\TestCase;

class ImagickLoadTest extends TestCase
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

	public function testLoadFile()
	{
		self::assertSame(['width' => 500, 'height' => 375], $this->thumb->getCurrentDimensions());

		// Imagick has avifQuality + webpQuality in its defaults; GD does not.
		self::assertSame([
			'resizeUp'              => false,
			'avifQuality'           => 100,
			'jpegQuality'           => 100,
			'webpQuality'           => 100,
			'correctPermissions'    => false,
			'preserveAlpha'         => true,
			'alphaMaskColor'        => [255, 255, 255],
			'preserveTransparency'  => true,
			'transparencyMaskColor' => [0, 0, 0],
			'interlace'             => null,
		], $this->thumb->getOptions());

		// Imagick normalises 'jpg' → 'JPEG'.
		self::assertSame('JPEG', $this->thumb->getFormat());
		self::assertSame(__DIR__ . '/../../resources/test.jpg', $this->thumb->getFileName());
	}

	public function testSetFormat()
	{
		$this->thumb->setFormat('PNG');
		self::assertSame('PNG', $this->thumb->getFormat());
	}

	public function testSetFilename()
	{
		$this->thumb->setFilename('mytest.jpg');
		self::assertSame('mytest.jpg', $this->thumb->getFilename());
	}

	public function testLoadExternalImage()
	{
		$gravatarThumb = new Imagick('https://en.gravatar.com/userimage/1132703/2ccbcfbea4a1b3b8d955c1e7746b882b.jpg');
		self::assertTrue($gravatarThumb->getIsRemoteImage());
	}

	public function testNonexistentFile()
	{
		$this->expectException(InvalidArgumentException::class);
		new Imagick('nosuchimage.jpg');
	}

	public function testIsRemoteImage()
	{
		$remoteThumb = new Imagick('https://example.com/image.jpg');
		self::assertTrue($remoteThumb->getIsRemoteImage());

		$localThumb = new Imagick(__DIR__ . '/../../resources/test.jpg');
		self::assertFalse($localThumb->getIsRemoteImage());
	}
}