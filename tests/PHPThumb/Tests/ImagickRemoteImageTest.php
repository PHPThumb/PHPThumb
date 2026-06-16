<?php
namespace PHPThumb\Tests;

use PHPThumb\Imagick;
use PHPUnit\Framework\TestCase;

class ImagickRemoteImageTest extends TestCase
{
	protected function setUp(): void
	{
		if (!extension_loaded('imagick'))
		{
			$this->markTestSkipped('ext-imagick is not available');
		}

		if (!getenv('RUN_NETWORK_TESTS'))
		{
			$this->markTestSkipped('Network tests are disabled (set RUN_NETWORK_TESTS=1 to enable)');
		}
	}

	public function testRemoteImageLoad()
	{
		$thumb = new Imagick('https://www.php.net/images/logos/php-logo.svg');

		self::assertTrue($thumb->getIsRemoteImage());
		self::assertNotEmpty($thumb->getCurrentDimensions());
	}

	public function testRemoteImageResize()
	{
		$thumb = new Imagick('https://www.php.net/images/logos/php-logo.svg');
		$thumb->resize(100, 100);

		self::assertSame(100, $thumb->getCurrentDimensions()['width']);
		self::assertSame(100, $thumb->getCurrentDimensions()['height']);
	}
}