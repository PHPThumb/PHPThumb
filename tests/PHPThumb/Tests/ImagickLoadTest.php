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
			'sharpenAmount'         => 50,
			'textFont'              => null,
			'textDefaultSize'       => 12,
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
		if (!getenv('RUN_NETWORK_TESTS'))
		{
			$this->markTestSkipped(
				'Network tests are disabled (set RUN_NETWORK_TESTS=1 to enable).'
				);
		}

		$thumb = new Imagick(
			'https://raw.githubusercontent.com/PHPThumb/PHPThumb/master/tests/resources/test.jpg'
			);
		self::assertTrue($thumb->getIsRemoteImage());
		self::assertNotEmpty($thumb->getCurrentDimensions());
	}

	public function testNonexistentFile()
	{
		$this->expectException(InvalidArgumentException::class);
		new Imagick('nosuchimage.jpg');
	}

	public function testIsRemoteImage()
	{
		// Local part: always available, always green.
		$localThumb = new Imagick(__DIR__ . '/../../resources/test.jpg');
		self::assertFalse($localThumb->getIsRemoteImage());

		// Remote part: requires network and a real reachable URL. The php.net
		// logo URL previously used here has been retired upstream, and the
		// example.com placeholder doesn't return a valid image — so we just
		// unit-test the URL-parse path locally and skip the network round-trip.
		if (!getenv('RUN_NETWORK_TESTS'))
		{
			$this->markTestSkipped(
				'Remote-image portion requires RUN_NETWORK_TESTS=1.'
				);
		}

		$remoteThumb = new Imagick(
			'https://raw.githubusercontent.com/PHPThumb/PHPThumb/master/tests/resources/test.jpg'
			);
		self::assertTrue($remoteThumb->getIsRemoteImage());
	}
}