<?php
namespace PHPThumb\Tests;

use PHPThumb\Imagick;
use PHPUnit\Framework\TestCase;

/**
 * These tests verify the extended Imagick format detection (BMP, HEIC, TIFF)
 * that the GD branch doesn't support. They skip silently if the fixture
 * files are absent — you can drop real BMP/HEIC/TIFF samples into
 * tests/resources/ to enable them.
 */
class ImagickFormatCoverageTest extends TestCase
{
	protected function setUp(): void
	{
		if (!extension_loaded('imagick'))
		{
			$this->markTestSkipped('ext-imagick is not available');
		}
	}

	public function testBmp()
	{
		$path = __DIR__ . '/../../resources/test.bmp';

		if (!file_exists($path))
		{
			$this->markTestSkipped('test.bmp fixture missing');
		}

		$thumb = new Imagick($path);
		self::assertSame('BMP', $thumb->getFormat());
	}

	public function testHeic()
	{
		$path = __DIR__ . '/../../resources/test.heic';

		if (!file_exists($path))
		{
			$this->markTestSkipped('test.heic fixture missing');
		}

		$thumb = new Imagick($path);
		self::assertSame('HEIC', $thumb->getFormat());
	}

	public function testTiff()
	{
		$path = __DIR__ . '/../../resources/test.tiff';

		if (!file_exists($path))
		{
			$this->markTestSkipped('test.tiff fixture missing');
		}

		$thumb = new Imagick($path);
		self::assertSame('TIFF', $thumb->getFormat());
	}
}